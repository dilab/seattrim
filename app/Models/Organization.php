<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;

/**
 * @property int $id
 * @property string $name
 * @property string $timezone
 * @property int $seat_price_cents
 * @property Carbon|null $renewal_date
 * @property string|null $billing_cycle
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property-read Membership|null $pivot
 */
#[Fillable(['name', 'timezone', 'seat_price_cents', 'renewal_date', 'billing_cycle', 'settings'])]
class Organization extends Model
{
    use Billable;

    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    public const BILLING_CYCLES = ['annual', 'monthly'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'seat_price_cents' => 'integer',
            'renewal_date' => 'date',
            'settings' => 'array',
        ];
    }

    /** @return BelongsToMany<User, $this, Membership> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot(['id', 'role'])
            ->withTimestamps();
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function roleOf(User $user): ?Role
    {
        $membership = $this->memberships()->where('user_id', $user->getKey())->first();

        return $membership?->role;
    }

    public function hasMember(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->getKey())->exists();
    }

    public function addMember(User $user, Role $role): Membership
    {
        return $this->memberships()->create([
            'user_id' => $user->getKey(),
            'role' => $role,
        ]);
    }

    /** @return array<int, User> */
    public function owners(): array
    {
        return $this->users()->wherePivot('role', Role::Owner->value)->get()->all();
    }

    /**
     * Typed accessor for a settings key with a default.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    /** Cashier: customer name/email come from the first owner. */
    public function stripeEmail(): ?string
    {
        return $this->owners()[0]->email ?? null;
    }

    public function stripeName(): ?string
    {
        return $this->name;
    }

    /** Annual price of one seat, in cents, normalised from the billing cycle. */
    public function annualSeatPriceCents(): int
    {
        return $this->billing_cycle === 'monthly'
            ? $this->seat_price_cents * 12
            : $this->seat_price_cents;
    }
}
