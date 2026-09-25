<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'timezone' => 'UTC',
            'seat_price_cents' => 14900,
            'renewal_date' => null,
            'billing_cycle' => 'annual',
            'settings' => [],
        ];
    }

    /** Attach a user with the given role after creation. */
    public function withMember(User $user, Role $role = Role::Owner): static
    {
        return $this->afterCreating(function (Organization $organization) use ($user, $role): void {
            $organization->addMember($user, $role);
        });
    }
}
