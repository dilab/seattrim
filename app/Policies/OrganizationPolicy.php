<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->canManage($organization);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->canManage($organization);
    }

    /** Destructive: delete org, disconnect Zoom and wipe data, change billing. */
    public function delete(User $user, Organization $organization): bool
    {
        return $user->isOwnerOf($organization);
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        return $user->isOwnerOf($organization);
    }
}
