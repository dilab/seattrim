<?php

namespace App\Scan;

use App\Billing\PlanResolver;
use App\Models\Exclusion;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Models\ZoomSeatsSnapshot;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\Data\PlanUsage;
use App\Zoom\Data\ZoomUser;
use App\Zoom\Exceptions\ConnectionRevokedException;
use App\Zoom\Exceptions\ZoomApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The scan (brief §6). Runs inside Tenancy::runAs($organization). Every Zoom
 * failure that is not fatal becomes a warning on the scan row; only "cannot
 * list active users" or a revoked connection fails the whole scan.
 */
class ScanRunner
{
    public const SCOPE_USERS = 'user:read:list_users:admin';

    public const SCOPE_USER = 'user:read:user:admin';

    public const SCOPE_SETTINGS = 'user:read:settings:admin';

    public const SCOPE_REPORT = 'report:read:list_users:admin';

    public const SCOPE_PLAN = 'billing:read:plan_usage:admin';

    public const SCOPE_MEETINGS = 'meeting:read:list_meetings:admin';

    private Scan $scan;

    private ZoomConnection $connection;

    private ScanSettings $settings;

    private Classifier $classifier;

    private ExclusionMatcher $exclusions;

    private CarbonImmutable $now;

    public function __construct(
        private readonly ZoomApi $zoom,
        private readonly MemberUpserter $upserter = new MemberUpserter,
    ) {}

    public function run(Organization $organization, Scan $scan): Scan
    {
        $this->scan = $scan;
        $this->now = CarbonImmutable::now();
        $this->settings = ScanSettings::for($organization);
        $this->classifier = new Classifier($this->settings);
        $this->exclusions = new ExclusionMatcher(Exclusion::query()->get());

        $scan->forceFill(['status' => Scan::STATUS_RUNNING, 'started_at' => $this->now, 'threshold_days' => $this->settings->thresholdDays, 'warnings' => []])->save();

        $connection = ZoomConnection::query()->first();

        if ($connection === null || ! $connection->isActive()) {
            return $this->fail('Zoom is not connected. Connect Zoom and run the scan again.');
        }

        $this->connection = $connection;

        try {
            $snapshot = $this->seats();
            $this->users();
            [$hosting, $hostingKnown, $reportOnly] = $this->hosting();
            $this->classifyAll($snapshot, $hosting, $hostingKnown);

            if ($reportOnly > 0) {
                $scan->addWarning('report_only_hosts', "{$reportOnly} host(s) in the report were not in the users list (often Zoom Rooms). They were ignored.");
            }

            $scan->forceFill([
                'status' => Scan::STATUS_DONE,
                'finished_at' => CarbonImmutable::now(),
                'totals' => Totals::compute($organization->refresh(), $snapshot),
            ])->save();

            $organization->setSetting('scan.last_completed_at', CarbonImmutable::now()->toIso8601String());
            $organization->save();

            PlanResolver::recordSeatCheck($organization);

            Log::info('scan.done', ['organization' => $organization->id, 'scan' => $scan->id, 'warnings' => count($scan->warnings ?? [])]);
        } catch (ConnectionRevokedException $e) {
            return $this->fail('Zoom access was revoked during the scan. Reconnect Zoom.');
        } catch (ZoomApiException $e) {
            return $this->fail('Zoom returned an error: '.$e->summary());
        } catch (Throwable $e) {
            Log::error('scan.crashed', ['organization' => $organization->id, 'scan' => $scan->id, 'error' => $e->getMessage()]);

            return $this->fail('The scan crashed: '.$e->getMessage());
        }

        return $scan;
    }

    private function fail(string $message): Scan
    {
        $this->scan->forceFill(['status' => Scan::STATUS_FAILED, 'finished_at' => CarbonImmutable::now(), 'error' => $message])->save();

        return $this->scan;
    }

    // ------------------------------------------------------------ 1. seats

    private function seats(): ?ZoomSeatsSnapshot
    {
        $usage = null;

        if ($this->connection->hasScope(self::SCOPE_PLAN)) {
            try {
                $usage = $this->zoom->planUsage($this->connection);
            } catch (ZoomApiException $e) {
                $this->scan->addWarning('plan_usage_failed', 'Could not read plan usage: '.$e->summary());
            }
        } else {
            $this->scan->addWarning('missing_scope', 'The connection lacks '.self::SCOPE_PLAN.'; purchased seats are unknown. Reconnect to grant it.');
        }

        if ($usage instanceof PlanUsage && $usage->available) {
            return ZoomSeatsSnapshot::query()->create([
                'scan_id' => $this->scan->id,
                'source' => 'plan_usage',
                'plan_names' => array_values(array_filter([$usage->planType, ...array_column($usage->bundlePlans, 'type')])),
                'purchased_seats' => $usage->totalPurchased(),
                'used_seats' => $usage->totalUsed(),
                'unassigned_seats' => $usage->unassigned(),
                'pending_seats' => $usage->pending,
                'raw' => $usage->raw,
            ]);
        }

        if ($usage instanceof PlanUsage) {
            $this->scan->addWarning('plan_usage_unavailable', 'Zoom did not expose plan usage for this account ('.((string) ($usage->raw['unavailable_reason'] ?? 'unknown')).'). Purchased seats are unknown; assigned seats come from the user summary.');
        }

        try {
            $summary = $this->zoom->userSummary($this->connection);

            return ZoomSeatsSnapshot::query()->create([
                'scan_id' => $this->scan->id,
                'source' => 'user_summary',
                'plan_names' => [],
                'purchased_seats' => null,
                'used_seats' => $summary->licensed + $summary->pending,
                'unassigned_seats' => null,
                'pending_seats' => $summary->pending,
                'raw' => $summary->raw,
            ]);
        } catch (ZoomApiException $e) {
            $this->scan->addWarning('user_summary_failed', 'Could not read the user summary: '.$e->summary());

            return null;
        }
    }

    // ------------------------------------------------------------ 2. users

    private function users(): void
    {
        $seen = [];
        $allListsOk = true;
        $licensedSeen = 0;

        foreach ([ZoomUser::STATUS_ACTIVE, ZoomUser::STATUS_INACTIVE, ZoomUser::STATUS_PENDING] as $status) {
            try {
                $list = $this->zoom->listUsers($this->connection, $status);
            } catch (ZoomApiException $e) {
                if ($status === ZoomUser::STATUS_ACTIVE) {
                    throw $e;
                }
                $allListsOk = false;
                $this->scan->addWarning('list_failed', "Could not list {$status} users: ".$e->summary());

                continue;
            }

            if ($list->isPartial()) {
                $this->scan->addWarning('pagination_mismatch', "Zoom reported {$list->totalRecords} {$status} users but returned {$list->count()} across {$list->pages} page(s). Some users may be missing from this scan.");
            }

            foreach ($list->items as $user) {
                $member = $this->upserter->upsert($user);
                $seen[] = $member->id;
                if ($user->isLicensed()) {
                    $licensedSeen++;
                }
            }
        }

        if ($allListsOk) {
            ZoomMember::query()->present()->whereNotIn('id', $seen)->update(['removed_at' => $this->now]);
        }

        try {
            $summary = $this->zoom->userSummary($this->connection);
            $expected = $summary->licensed + $summary->pending;
            if ($licensedSeen !== $expected && abs($licensedSeen - $expected) > $summary->pending) {
                $this->scan->addWarning('summary_mismatch', "Zoom's user summary counts {$summary->licensed} licensed and {$summary->pending} pending users, but the lists contained {$licensedSeen} licensed rows.");
            }
        } catch (ZoomApiException) {
            // Already warned in seats() if it failed there too.
        }
    }

    // ---------------------------------------------------------- 3. hosting

    /**
     * @return array{0: array<string, array<string, int>>, 1: bool, 2: int} [hosting by zoom id, hostingKnown, reportOnlyCount]
     */
    private function hosting(): array
    {
        $windows = Window::covering($this->settings->lookbackDays(), $this->now);
        $labels = array_map(fn (Window $w) => $w->label(), $windows);
        $hosting = [];
        $known = true;

        if (! $this->connection->hasScope(self::SCOPE_REPORT)) {
            $this->scan->addWarning('missing_scope', 'The connection lacks '.self::SCOPE_REPORT.'; hosting activity is unknown, so no user can be marked idle. Reconnect to grant it.');

            return [[], false, 0];
        }

        $memberIds = ZoomMember::query()->present()->whereNotNull('zoom_user_id')->pluck('zoom_user_id')->flip()->all();
        $reportOnly = [];

        foreach ($windows as $window) {
            try {
                $report = $this->zoom->hostReport($this->connection, 'active', $window->from, $window->to);
            } catch (ZoomApiException $e) {
                $known = false;
                $this->scan->addWarning('report_failed', "The active hosts report for {$window->label()} days ago failed: ".$e->summary().'. Hosting activity is treated as unknown for this scan.');

                continue;
            }

            if ($report->isPartial()) {
                $this->scan->addWarning('pagination_mismatch', "The hosts report for {$window->label()} days ago returned {$report->count()} rows but Zoom counted {$report->totalRecords}.");
            }

            foreach ($report->items as $row) {
                if (! isset($memberIds[$row->id])) {
                    $reportOnly[$row->id] = true;

                    continue;
                }
                $hosting[$row->id][$window->label()] = $row->meetings;
            }
        }

        foreach ($hosting as $id => $byWindow) {
            $hosting[$id] = array_merge(array_fill_keys($labels, 0), $byWindow);
        }

        return [$hosting, $known, count($reportOnly)];
    }

    // --------------------------------------------------------- 4–5. classify

    /** @param array<string, array<string, int>> $hosting */
    private function classifyAll(?ZoomSeatsSnapshot $snapshot, array $hosting, bool $hostingKnown): void
    {
        $labels = Window::labels($this->settings->lookbackDays());
        $accountHasBundlePlans = $this->accountHasBundlePlans($snapshot);
        $detailFailures = 0;

        ZoomMember::query()->present()->orderBy('id')->chunkById(200, function ($members) use ($labels, $hosting, $hostingKnown, $accountHasBundlePlans, &$detailFailures): void {
            foreach ($members as $member) {
                $byWindow = $member->zoom_user_id !== null
                    ? ($hosting[$member->zoom_user_id] ?? array_fill_keys($labels, 0))
                    : array_fill_keys($labels, 0);

                $facts = $this->baseFacts($member, $byWindow, $hostingKnown, $accountHasBundlePlans);

                if ($this->isCandidate($member, $facts)) {
                    $facts = $this->enrich($member, $facts, $detailFailures);
                }

                $result = $this->classifier->classify($facts, $this->now);

                $member->forceFill([
                    'meetings_by_window' => $byWindow,
                    'last_hosted_window' => $result->lastHostedWindow,
                    'bucket' => $result->bucket,
                    'protected_reasons' => $result->protectedReasons,
                    'eligible_for_downgrade' => $result->eligibleForDowngrade,
                ])->save();
            }
        });

        if ($detailFailures > 0) {
            $this->scan->addWarning('detail_failed', "{$detailFailures} per-user lookup(s) failed; those users were protected as a precaution.");
        }
    }

    /** @param array<string, int> $byWindow */
    private function baseFacts(ZoomMember $member, array $byWindow, bool $hostingKnown, bool $accountHasBundlePlans): MemberFacts
    {
        return new MemberFacts(
            status: $member->status,
            type: $member->type,
            isOwnerOrAdmin: in_array($member->role_id, ['0', '1'], true) || in_array(mb_strtolower((string) $member->role_name), ['owner', 'admin'], true),
            isRoom: $member->is_room,
            hasBundle: $member->has_bundled_license,
            bundleKnown: $member->bundle_known || ! $accountHasBundlePlans,
            addOns: $member->add_ons ?? [],
            upcomingMeetings: $member->upcoming_meetings_count,
            createdAt: $member->created_at_zoom?->toImmutable(),
            lastLoginAt: $member->last_login_at?->toImmutable(),
            exclusionReason: $this->exclusions->reasonFor($member->email, $member->group_ids ?? []),
            excludedUntil: $member->excluded_until?->toImmutable(),
            hostingKnown: $hostingKnown,
            meetingsByWindow: $byWindow,
        );
    }

    /** Only licensed members that could end up in a waste bucket earn extra API calls. */
    private function isCandidate(ZoomMember $member, MemberFacts $facts): bool
    {
        if (! $member->isLicensed() || $member->zoom_user_id === null) {
            return false;
        }

        if ($facts->isDeactivated()) {
            return true;
        }

        return $this->classifier->isIdle($facts);
    }

    private function enrich(ZoomMember $member, MemberFacts $facts, int &$failures): MemberFacts
    {
        $userId = (string) $member->zoom_user_id;
        $hasBundle = $facts->hasBundle;
        $bundleKnown = $facts->bundleKnown;
        $addOns = $facts->addOns;
        $upcoming = null;
        $isRoom = $member->is_room;

        if ($this->connection->hasScope(self::SCOPE_USER)) {
            try {
                $user = $this->zoom->getUser($this->connection, $userId);
                $hasBundle = $user->hasBundle();
                $bundleKnown = $user->hasBundleFields() || $bundleKnown;
                $member->fill(['type' => $user->type, 'status' => $user->status, 'role_id' => $user->roleId ?? $member->role_id, 'role_name' => $user->roleName ?? $member->role_name]);
            } catch (ZoomApiException $e) {
                $failures++;
                $bundleKnown = false;
            }
        } else {
            $bundleKnown = false;
        }

        if ($this->connection->hasScope(self::SCOPE_SETTINGS)) {
            try {
                $features = $this->zoom->userFeatures($this->connection, $userId);
                $addOns = $features->enabledAddOns;
                $member->fill(['has_phone' => $features->zoomPhone, 'has_webinar_addon' => $features->webinar, 'has_large_meeting_addon' => $features->largeMeeting]);
            } catch (ZoomApiException $e) {
                $failures++;
                $addOns = array_values(array_unique([...$addOns, 'unknown_add_ons']));
            }
        }

        if (! $facts->isDeactivated()) {
            if ($this->connection->hasScope(self::SCOPE_MEETINGS)) {
                try {
                    $upcoming = count($this->zoom->upcomingMeetings($this->connection, $userId));
                } catch (ZoomApiException $e) {
                    $failures++;
                    $upcoming = null;
                }
            }
        }

        $member->fill([
            'has_bundled_license' => $hasBundle,
            'bundle_known' => $bundleKnown,
            'add_ons' => $addOns,
            'upcoming_meetings_count' => $upcoming,
        ]);

        return new MemberFacts(
            status: $member->status,
            type: $member->type,
            isOwnerOrAdmin: $facts->isOwnerOrAdmin,
            isRoom: $isRoom,
            hasBundle: $hasBundle,
            bundleKnown: $bundleKnown,
            addOns: $addOns,
            upcomingMeetings: $upcoming,
            createdAt: $facts->createdAt,
            lastLoginAt: $facts->lastLoginAt,
            exclusionReason: $facts->exclusionReason,
            excludedUntil: $facts->excludedUntil,
            hostingKnown: $facts->hostingKnown,
            meetingsByWindow: $facts->meetingsByWindow,
        );
    }

    private function accountHasBundlePlans(?ZoomSeatsSnapshot $snapshot): bool
    {
        if ($snapshot === null || $snapshot->source !== 'plan_usage') {
            return true; // unknown → be careful
        }

        return PlanUsage::fromArray($snapshot->raw ?? [])->hasBundlePlans();
    }
}
