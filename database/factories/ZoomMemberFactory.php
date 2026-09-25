<?php

namespace Database\Factories;

use App\Enums\Bucket;
use App\Models\ZoomMember;
use App\Zoom\Data\ZoomUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ZoomMember>
 */
class ZoomMemberFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $id = 'u_'.Str::random(10);

        return [
            'member_key' => $id,
            'zoom_user_id' => $id,
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'status' => ZoomUser::STATUS_ACTIVE,
            'type' => ZoomUser::TYPE_LICENSED,
            'dept' => fake()->randomElement(['Math', 'Science', 'IT']),
            'group_ids' => [],
            'role_id' => '2',
            'created_at_zoom' => now()->subYear(),
            'last_login_at' => now()->subDays(3),
            'last_hosted_window' => '0-30',
            'meetings_by_window' => ['0-30' => 3, '30-60' => 1, '60-90' => 0],
            'upcoming_meetings_count' => 0,
            'bundle_known' => true,
            'bucket' => Bucket::Healthy,
            'protected_reasons' => [],
            'eligible_for_downgrade' => false,
            'raw' => [],
            'last_seen_at' => now(),
        ];
    }

    public function idle(): static
    {
        return $this->state(fn () => [
            'last_hosted_window' => 'none',
            'meetings_by_window' => ['0-30' => 0, '30-60' => 0, '60-90' => 0],
            'bucket' => Bucket::IdleLicensed,
            'eligible_for_downgrade' => true,
        ]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => [
            'status' => ZoomUser::STATUS_INACTIVE,
            'last_login_at' => null,
            'last_hosted_window' => 'none',
            'bucket' => Bucket::DeactivatedLicensed,
            'eligible_for_downgrade' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(function () {
            $email = fake()->unique()->safeEmail();

            return [
                'zoom_user_id' => null,
                'member_key' => 'email:'.$email,
                'email' => $email,
                'status' => ZoomUser::STATUS_PENDING,
                'last_hosted_window' => 'none',
                'bucket' => Bucket::PendingLicensed,
                'eligible_for_downgrade' => false,
            ];
        });
    }

    public function basic(): static
    {
        return $this->state(fn () => ['type' => ZoomUser::TYPE_BASIC, 'bucket' => Bucket::Healthy, 'eligible_for_downgrade' => false]);
    }

    /** @param array<int, string> $reasons */
    public function protected(array $reasons = ['has Zoom Phone']): static
    {
        return $this->state(fn () => [
            'last_hosted_window' => 'none',
            'bucket' => Bucket::Protected,
            'protected_reasons' => $reasons,
            'eligible_for_downgrade' => false,
        ]);
    }
}
