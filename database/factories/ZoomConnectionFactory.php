<?php

namespace Database\Factories;

use App\Models\ZoomConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZoomConnection>
 */
class ZoomConnectionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'zoom_account_id' => 'acct_'.fake()->unique()->lexify('??????????'),
            'installer_zoom_user_id' => 'usr_'.fake()->lexify('??????????'),
            'installer_email' => fake()->safeEmail(),
            'access_token' => 'access-'.fake()->sha1(),
            'refresh_token' => 'refresh-'.fake()->sha1(),
            'expires_at' => now()->addHour(),
            'scopes' => config('zoom.scopes'),
            'status' => ZoomConnection::STATUS_ACTIVE,
            'connected_at' => now(),
        ];
    }

    public function expiringSoon(): static
    {
        return $this->state(fn () => ['expires_at' => now()->addMinutes(2)]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => ZoomConnection::STATUS_REVOKED, 'revoked_at' => now(), 'access_token' => null, 'refresh_token' => null]);
    }
}
