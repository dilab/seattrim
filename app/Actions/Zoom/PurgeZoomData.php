<?php

namespace App\Actions\Zoom;

use App\Models\DowngradeNotice;
use App\Models\LicenseAction;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Models\ZoomSeatsSnapshot;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Deletes every Zoom-derived row for an organization (docs/zoom-api-notes.md §11,
 * brief §13). Keeps: the organization, memberships, billing, exclusions the
 * admin typed in, and an anonymised count of audit actions in settings.
 *
 * Each milestone that adds a Zoom-derived table appends its model here.
 */
class PurgeZoomData
{
    /** @var array<int, class-string<Model>> Deleted in this order (children first). */
    public const MODELS = [
        DowngradeNotice::class,
        LicenseAction::class,
        ZoomMember::class,
        ZoomSeatsSnapshot::class,
        Scan::class,
        ZoomConnection::class,
    ];

    public function __construct(private readonly Tenancy $tenancy) {}

    /** @return array<string, int> rows deleted per table */
    public function handle(Organization $organization): array
    {
        return $this->tenancy->runAs($organization, function (Organization $organization): array {
            return DB::transaction(function () use ($organization): array {
                $deleted = [];

                foreach (self::MODELS as $model) {
                    $query = $model::query();
                    $deleted[(new $model)->getTable()] = $query->count();
                    $query->delete();
                }

                // Anonymised audit trail: only the count survives.
                $organization->setSetting('zoom.audit_actions_purged_count', (int) $organization->setting('zoom.audit_actions_purged_count', 0) + ($deleted['license_actions'] ?? 0));
                $organization->setSetting('zoom.purged_at', now()->toIso8601String());
                $organization->save();

                return $deleted;
            });
        });
    }
}
