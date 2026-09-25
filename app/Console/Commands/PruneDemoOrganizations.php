<?php

namespace App\Console\Commands;

use App\Actions\Zoom\PurgeZoomData;
use App\Models\Organization;
use Illuminate\Console\Command;

/** Deletes demo organizations (and their throw-away users) older than the given hours. */
class PruneDemoOrganizations extends Command
{
    protected $signature = 'seattrim:prune-demo {--hours=24}';

    protected $description = 'Delete public-demo organizations and users older than --hours';

    public function handle(PurgeZoomData $purge): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));
        $deleted = 0;

        Organization::query()->where('created_at', '<', $cutoff)->cursor()->each(function (Organization $organization) use ($purge, &$deleted): void {
            if (! $organization->setting('demo')) {
                return;
            }

            $purge->handle($organization);
            $organization->subscriptions()->delete();

            foreach ($organization->users as $user) {
                if (str_ends_with($user->email, '@demo.seattrim.invalid')) {
                    $user->delete();
                }
            }

            $organization->delete();
            $deleted++;
        });

        $this->info("Deleted {$deleted} demo organization(s).");

        return self::SUCCESS;
    }
}
