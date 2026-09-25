<?php

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\NoCurrentOrganization;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * A throwaway tenant model so the scope is proven independently of any real table.
 */
class TenantScopeFixture extends Model
{
    use BelongsToOrganization;

    protected $table = 'tenant_scope_fixtures';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('tenant_scope_fixtures', function (Blueprint $table) {
        $table->id();
        $table->foreignId('organization_id');
        $table->string('label');
        $table->timestamps();
    });
});

test('creating a tenant model without a current organization throws', function () {
    Organization::factory()->create();

    expect(fn () => TenantScopeFixture::create(['label' => 'orphan']))
        ->toThrow(NoCurrentOrganization::class);
});

test('tenant models are stamped with and scoped to the current organization', function () {
    $tenancy = app(Tenancy::class);
    $a = Organization::factory()->create();
    $b = Organization::factory()->create();

    $tenancy->runAs($a, fn () => TenantScopeFixture::create(['label' => 'belongs to a']));
    $tenancy->runAs($b, fn () => TenantScopeFixture::create(['label' => 'belongs to b']));

    $seenByA = $tenancy->runAs($a, fn () => TenantScopeFixture::query()->pluck('label')->all());
    $seenByB = $tenancy->runAs($b, fn () => TenantScopeFixture::query()->pluck('label')->all());

    expect($seenByA)->toBe(['belongs to a'])
        ->and($seenByB)->toBe(['belongs to b']);

    // Lookups by primary key are scoped too: A cannot find B's row.
    $idOfB = TenantScopeFixture::query()->allOrganizations()->where('label', 'belongs to b')->value('id');
    $found = $tenancy->runAs($a, fn () => TenantScopeFixture::query()->find($idOfB));
    expect($found)->toBeNull();

    // Updates and deletes through the scoped query cannot touch the other tenant.
    $affected = $tenancy->runAs($a, fn () => TenantScopeFixture::query()->whereKey($idOfB)->update(['label' => 'tampered']));
    expect($affected)->toBe(0)
        ->and(TenantScopeFixture::query()->allOrganizations()->where('label', 'tampered')->exists())->toBeFalse();

    // Outside any tenant the scope is off (console/tests) and both rows are visible.
    expect(TenantScopeFixture::query()->count())->toBe(2);
});

test('runAs restores the previous tenant even when the callback throws', function () {
    $tenancy = app(Tenancy::class);
    $a = Organization::factory()->create();
    $b = Organization::factory()->create();

    $tenancy->set($a);

    try {
        $tenancy->runAs($b, fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect($tenancy->current()?->is($a))->toBeTrue();
    $tenancy->forget();
});

test('the web middleware scopes requests to the current organization and clears it afterwards', function () {
    $user = User::factory()->create();
    $mine = Organization::factory()->withMember($user)->create();
    $other = Organization::factory()->withMember(User::factory()->create())->create();
    $user->switchToOrganization($mine);

    $tenancy = app(Tenancy::class);
    $tenancy->runAs($other, fn () => TenantScopeFixture::create(['label' => 'secret']));

    $seen = null;
    Route::middleware(['web', 'auth', 'organization'])->get('/_tenancy-probe', function () use (&$seen) {
        $seen = TenantScopeFixture::query()->pluck('label')->all();

        return 'ok';
    });

    $this->actingAs($user)->get('/_tenancy-probe')->assertOk();

    expect($seen)->toBe([])
        ->and($tenancy->has())->toBeFalse();
});
