<?php

namespace App\Http\Controllers;

use App\Models\LicenseAction;
use App\Support\Features;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** GET /audit/export.csv — the audit log with the same filters as the page. Paid feature. */
class AuditExportController extends Controller
{
    public function __invoke(Request $request, Tenancy $tenancy): StreamedResponse
    {
        $organization = $tenancy->currentOrFail();
        Gate::authorize('view', $organization);
        abort_unless(Features::allows($organization, Features::CSV_EXPORT), 403, Features::deniedMessage(Features::CSV_EXPORT));

        $query = LicenseAction::query()->with('performer')->latest('id');

        foreach (['action', 'status', 'source'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, (string) $request->query($column));
            }
        }
        if ($request->filled('search')) {
            $term = '%'.trim((string) $request->query('search')).'%';
            $query->where(fn (Builder $q) => $q->where('member_email', 'like', $term)->orWhere('member_name', 'like', $term));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('to'));
        }

        $filename = 'seattrim-audit-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['id', 'created_at', 'performed_at', 'member_email', 'member_name', 'action', 'from_type', 'to_type', 'source', 'performed_by', 'status', 'dry_run', 'reason', 'zoom_tracking_id', 'batch_id']);

            $query->chunkById(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->id, $row->created_at?->toIso8601String(), $row->performed_at?->toIso8601String(), $row->member_email, $row->member_name,
                        $row->action, $row->from_type, $row->to_type, $row->source, $row->performer?->email, $row->status, $row->dry_run ? 'yes' : 'no',
                        $row->reason, $row->zoom_tracking_id, $row->batch_id,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
