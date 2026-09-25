<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Viewer => 'Viewer',
        };
    }

    /** Owners and admins may change Zoom users, settings and rules. */
    public function canManage(): bool
    {
        return $this !== self::Viewer;
    }

    /** Only owners may delete the organization, disconnect Zoom, or change billing. */
    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
