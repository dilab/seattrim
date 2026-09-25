<?php

namespace App\Jobs;

use App\Automation\AutomationRunner;
use App\Models\Organization;
use App\Tenancy\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/** Runs the organization's automation rule. Dispatched after each scheduled scan. */
class RunAutomation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $organizationId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("automation:{$this->organizationId}"))->dontRelease()->expireAfter(600)];
    }

    public function handle(Tenancy $tenancy, AutomationRunner $runner): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $tenancy->runAs($organization, fn (Organization $organization) => $runner->run($organization));
    }
}
