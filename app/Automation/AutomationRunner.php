<?php

namespace App\Automation;

use App\Actions\License\QueueLicenseActions;
use App\Actions\License\SafetyCap;
use App\Enums\Bucket;
use App\Models\DowngradeNotice;
use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Notifications\DowngradeWarning;
use App\Notifications\LicenseKept;
use App\Scan\Classifier;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Runs after each scheduled scan (brief §8):
 *  1. warn: every eligible member in an included bucket with no open notice gets a
 *     warning email and a notice scheduled warningDays ahead;
 *  2. cancel: notices whose member is no longer eligible are cancelled;
 *  3. execute: due notices are downgraded (or dry-run logged) after a fresh guardrail check.
 * Never exceeds the safety cap in one run; the rest waits for the next day.
 */
class AutomationRunner
{
    public function __construct(private readonly QueueLicenseActions $queue) {}

    /** @return array{warned: int, cancelled: int, executed: int, deferred: int} */
    public function run(Organization $organization, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $settings = AutomationSettings::for($organization);
        $result = ['warned' => 0, 'cancelled' => 0, 'executed' => 0, 'deferred' => 0];

        if (! $settings->enabled || ! Features::allows($organization, Features::AUTOMATION)) {
            return $result;
        }

        $connection = ZoomConnection::query()->first();
        if ($connection === null || ! $connection->isActive()) {
            return $result;
        }

        $result['cancelled'] = $this->cancelStale($settings, $now);
        $result['warned'] = $this->warn($organization, $settings, $now);
        [$result['executed'], $result['deferred']] = $this->execute($organization, $settings, $now);

        Log::info('automation.run', ['organization' => $organization->id] + $result);

        return $result;
    }

    private function cancelStale(AutomationSettings $settings, CarbonImmutable $now): int
    {
        $cancelled = 0;

        DowngradeNotice::query()->open()->with('member')->each(function (DowngradeNotice $notice) use ($settings, $now, &$cancelled): void {
            $member = $notice->member;
            $reason = match (true) {
                $member === null || ! $member->isPresent() => 'user left the account',
                ! $member->isLicensed() => 'user is no longer Licensed',
                ! $member->eligible_for_downgrade => 'a guardrail now applies: '.implode('; ', $member->protected_reasons ?? []),
                ! in_array($member->bucket->value, $settings->buckets, true) => 'user is no longer in an automated bucket ('.$member->bucket->label().')',
                default => null,
            };

            if ($reason !== null) {
                $notice->forceFill(['cancelled_at' => $now, 'cancel_reason' => $reason])->save();
                $cancelled++;
            }
        });

        return $cancelled;
    }

    private function warn(Organization $organization, AutomationSettings $settings, CarbonImmutable $now): int
    {
        $warned = 0;

        $candidates = ZoomMember::query()->present()->licensed()
            ->where('eligible_for_downgrade', true)
            ->whereIn('bucket', $settings->buckets)
            ->whereDoesntHave('notices', fn ($q) => $q->open())
            ->orderBy('id')
            ->get();

        foreach ($candidates as $member) {
            $notice = DowngradeNotice::query()->create([
                'zoom_member_id' => $member->id,
                'scheduled_for' => $now->addDays($settings->warningDays),
                'sent_at' => $member->status === 'pending' ? null : $now,
            ]);

            // Pending invites have never signed in; a warning email to an unaccepted invite would only
            // confuse. Admins see them in the digest instead.
            if ($member->status !== 'pending') {
                Notification::route('mail', [$member->email => $member->name ?? $member->email])
                    ->notify(new DowngradeWarning($organization, $member, $notice, $settings));
            }

            $warned++;
        }

        return $warned;
    }

    /** @return array{0: int, 1: int} [executed, deferred] */
    private function execute(Organization $organization, AutomationSettings $settings, CarbonImmutable $now): array
    {
        $due = DowngradeNotice::query()->open()->where('scheduled_for', '<=', $now)->with('member')->orderBy('scheduled_for')->get();
        $limit = SafetyCap::limit();
        $members = collect();
        $notices = [];

        foreach ($due as $notice) {
            if ($members->count() >= $limit) {
                break;
            }
            $member = $notice->member;
            if ($member === null) {
                continue;
            }
            $members->push($member);
            $notices[$member->id] = $notice;
        }

        if ($members->isEmpty()) {
            return [0, 0];
        }

        $result = $this->queue->downgrade($organization, $members, LicenseAction::SOURCE_RULE, null, $settings->dryRun);

        foreach ($result['actions'] as $action) {
            $notice = $notices[$action->zoom_member_id] ?? null;
            $notice?->forceFill(['executed_action_id' => $action->id])->save();
        }

        return [$members->count(), max(0, $due->count() - $members->count())];
    }

    /** The user clicked "Keep my license". */
    public static function keep(DowngradeNotice $notice, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();

        if (! $notice->isOpen()) {
            return;
        }

        $member = $notice->member;
        if ($member === null) {
            return;
        }

        $notice->forceFill(['kept_at' => $now])->save();

        $member->forceFill([
            'excluded_until' => $now->addDays(AutomationSettings::KEEP_DAYS),
            'eligible_for_downgrade' => false,
            'bucket' => Bucket::Protected,
            'protected_reasons' => array_values(array_unique([...($member->protected_reasons ?? []), sprintf(Classifier::REASON_EXCLUDED_UNTIL, $now->addDays(AutomationSettings::KEEP_DAYS)->toDateString())])),
        ])->save();

        Notification::send($notice->organization->users()->wherePivotIn('role', ['owner', 'admin'])->get(), new LicenseKept($notice->organization, $member));
    }
}
