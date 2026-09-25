<?php

namespace App\Scan;

use App\Models\ZoomMember;
use App\Zoom\Data\ZoomUser;

/** Writes what a Zoom user payload tells us onto zoom_members without touching classification columns. */
class MemberUpserter
{
    public function upsert(ZoomUser $user): ZoomMember
    {
        $member = ZoomMember::query()->firstOrNew(['member_key' => $user->key()]);

        // A pending user who later activates gets an id: re-key by id once, keep history.
        if ($user->id !== null && $member->exists === false) {
            $byEmail = ZoomMember::query()->whereNull('zoom_user_id')->where('email', $user->email)->first();
            if ($byEmail !== null) {
                $member = $byEmail;
                $member->member_key = $user->key();
            }
        }

        $member->fill([
            'zoom_user_id' => $user->id ?? $member->zoom_user_id,
            'email' => $user->email,
            'name' => $user->displayName !== '' ? $user->displayName : $member->name,
            'status' => $user->status,
            'type' => $user->type,
            'dept' => $user->dept,
            'group_ids' => $user->groupIds,
            'role_id' => $user->roleId ?? $member->role_id,
            'role_name' => $user->roleName ?? $member->role_name,
            'created_at_zoom' => $user->userCreatedAt,
            'last_login_at' => $user->lastLoginAt,
            'raw' => $this->redact($user->raw),
            'last_seen_at' => now(),
            'removed_at' => null,
        ]);

        if ($user->hasBundleFields()) {
            $member->has_bundled_license = $user->hasBundle();
            $member->bundle_known = true;
        }

        $member->save();

        return $member;
    }

    /**
     * Keep only the fields we need for debugging; drop anything personal that the product does not use.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function redact(array $raw): array
    {
        $keep = ['id', 'type', 'status', 'role_id', 'role_name', 'dept', 'group_ids', 'user_created_at', 'created_at', 'last_login_time',
            'last_client_version', 'plan_united_type', 'zoom_one_type', 'license_info_list', 'login_types', 'verified', 'timezone'];

        return array_intersect_key($raw, array_flip($keep));
    }
}
