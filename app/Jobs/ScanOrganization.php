<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\Scan;
use App\Scan\ScanRunner;
use App\Tenancy\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Queued wrapper around ScanRunner. One scan per organization at a time.
 */
class ScanOrganization implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $organizationId, public int $scanId) {}

    /** Create the scan row immediately so the UI can show progress, then queue. */
    public static function start(Organization $organization, string $trigger = Scan::TRIGGER_MANUAL): Scan
    {
        $scan = app(Tenancy::class)->runAs($organization, fn () => Scan::query()->create([
            'trigger' => $trigger,
            'status' => Scan::STATUS_QUEUED,
        ]));

        self::dispatch($organization->getKey(), $scan->getKey());

        return $scan;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("scan:{$this->organizationId}"))->dontRelease()->expireAfter(1800)];
    }

    /** The job crashed outside ScanRunner's own error handling (e.g. timeout): leave a failed scan row, never a stuck "running" one. */
    public function failed(?\Throwable $e): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        app(Tenancy::class)->runAs($organization, function () use ($e): void {
            Scan::query()->whereKey($this->scanId)->whereIn('status', [Scan::STATUS_QUEUED, Scan::STATUS_RUNNING])
                ->update(['status' => Scan::STATUS_FAILED, 'finished_at' => now(), 'error' => 'The scan did not finish: '.($e?->getMessage() ?? 'unknown error')]);
        });
    }

    public function handle(Tenancy $tenancy, ScanRunner $runner): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $tenancy->runAs($organization, function (Organization $organization) use ($runner): void {
            $scan = Scan::query()->find($this->scanId);

            if ($scan === null || $scan->isFinished()) {
                return;
            }

            $scan = $runner->run($organization, $scan);

            if ($scan->status === Scan::STATUS_DONE && $scan->trigger === Scan::TRIGGER_SCHEDULED) {
                RunAutomation::dispatch($organization->getKey());
            }
        });
    }
}
