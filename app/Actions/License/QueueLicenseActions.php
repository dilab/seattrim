<?php

namespace App\Actions\License;

use App\Jobs\DowngradeMember;
use App\Jobs\RestoreMember;
use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\User;
use App\Models\ZoomMember;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * Creates queued audit rows and dispatches one job per member, as a batch so the
 * UI can show live progress. Callers are responsible for authorization and the
 * safety-cap confirmation.
 */
class QueueLicenseActions
{
    /**
     * @param  Collection<int, ZoomMember>  $members
     * @return array{batch: ?Batch, actions: Collection<int, LicenseAction>}
     */
    public function downgrade(Organization $organization, Collection $members, string $source, ?User $performer, bool $dryRun = false): array
    {
        return $this->queue($organization, $members, LicenseAction::ACTION_DOWNGRADE, $source, $performer, $dryRun);
    }

    /**
     * @param  Collection<int, ZoomMember>  $members
     * @return array{batch: ?Batch, actions: Collection<int, LicenseAction>}
     */
    public function restore(Organization $organization, Collection $members, string $source, ?User $performer): array
    {
        return $this->queue($organization, $members, LicenseAction::ACTION_RESTORE, $source, $performer, false);
    }

    /**
     * @param  Collection<int, ZoomMember>  $members
     * @return array{batch: ?Batch, actions: Collection<int, LicenseAction>}
     */
    private function queue(Organization $organization, Collection $members, string $action, string $source, ?User $performer, bool $dryRun): array
    {
        if ($members->isEmpty()) {
            return ['batch' => null, 'actions' => collect()];
        }

        $batchId = (string) Str::uuid();
        $actions = collect();
        $jobs = [];

        foreach ($members as $member) {
            $row = LicenseAction::query()->create([
                'zoom_member_id' => $member->id,
                'member_email' => $member->email,
                'member_name' => $member->name,
                'action' => $action,
                'from_type' => $member->type,
                'to_type' => $action === LicenseAction::ACTION_DOWNGRADE ? 1 : 2,
                'source' => $source,
                'performed_by' => $performer?->getKey(),
                'status' => LicenseAction::STATUS_QUEUED,
                'batch_id' => $batchId,
                'dry_run' => $dryRun,
            ]);
            $actions->push($row);

            $jobs[] = $action === LicenseAction::ACTION_DOWNGRADE
                ? new DowngradeMember($organization->getKey(), $row->id)
                : new RestoreMember($organization->getKey(), $row->id);
        }

        $batch = Bus::batch($jobs)->name("license:{$action}:{$organization->getKey()}")->allowFailures()->dispatch();

        return ['batch' => $batch, 'actions' => $actions];
    }
}
