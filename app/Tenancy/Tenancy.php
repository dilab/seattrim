<?php

namespace App\Tenancy;

use App\Models\Organization;
use Closure;

/**
 * Holds the organization the current request or job is acting for.
 *
 * Web requests set it in EnsureCurrentOrganization middleware. Queue jobs must
 * call Tenancy::runAs($organization, fn () => ...) explicitly; nothing is
 * inherited from the session. Global scopes on tenant models read from here.
 */
class Tenancy
{
    private ?Organization $organization = null;

    public function current(): ?Organization
    {
        return $this->organization;
    }

    public function currentOrFail(): Organization
    {
        return $this->organization ?? throw new NoCurrentOrganization;
    }

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function forget(): void
    {
        $this->organization = null;
    }

    public function has(): bool
    {
        return $this->organization !== null;
    }

    /**
     * Run a callback as the given organization, restoring the previous tenant afterwards.
     *
     * @template T
     *
     * @param  Closure(Organization): T  $callback
     * @return T
     */
    public function runAs(Organization $organization, Closure $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;

        try {
            return $callback($organization);
        } finally {
            $this->organization = $previous;
        }
    }
}
