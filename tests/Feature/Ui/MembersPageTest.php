<?php

use App\Jobs\ScanOrganization;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Tenancy\Tenancy;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->withMember($this->user)->create();
    $this->user->switchToOrganization($this->organization);
    app(Tenancy::class)->runAs($this->organization, fn () => ZoomConnection::factory()->create());
    ScanOrganization::start($this->organization, Scan::TRIGGER_MANUAL);
});

test('the members page lists users and filters by bucket, status, department, window and search', function () {
    $this->actingAs($this->user)->get(route('members'))->assertOk()->assertSee('69 users');

    $component = actingAsMemberOf($this->organization, $this->user)->test('pages::members');

    $component->set('bucket', 'idle_licensed');
    expect($component->get('members')->total())->toBe(11);

    $component->set('bucket', '')->set('status', 'inactive');
    expect($component->get('members')->total())->toBe(7);

    $component->set('status', '')->set('dept', 'Athletics');
    expect($component->get('members')->total())->toBe(9);

    $component->set('dept', '')->set('window', 'none');
    expect($component->get('members')->total())->toBeGreaterThan(20);

    $component->set('window', '')->set('search', 'newhire');
    expect($component->get('members')->total())->toBe(2);

    $component->set('search', '')->set('eligible', true);
    expect($component->get('members')->total())->toBe(16);
});

test('sorting toggles direction and the drawer shows reasons and hosting', function () {
    $component = actingAsMemberOf($this->organization, $this->user)->test('pages::members');

    $component->call('sortBy', 'email');
    expect($component->get('sort'))->toBe('email')->and($component->get('dir'))->toBe('asc');
    $component->call('sortBy', 'email');
    expect($component->get('dir'))->toBe('desc');

    $member = app(Tenancy::class)->runAs($this->organization, fn () => ZoomMember::query()->where('zoom_user_id', 'u62_phone1')->firstOrFail());

    $component->call('show', $member->id)
        ->assertSee('Pat Phone')
        ->assertSee('Has Zoom Phone')
        ->assertSee('Hosting activity')
        ->assertSee('No actions yet');
});

test('members of another organization are invisible', function () {
    $other = User::factory()->create();
    $otherOrg = Organization::factory()->withMember($other)->create();
    $other->switchToOrganization($otherOrg);

    $this->actingAs($other)->get(route('members'))->assertOk()->assertSee('0 users');

    $foreign = app(Tenancy::class)->runAs($this->organization, fn () => ZoomMember::query()->firstOrFail());
    actingAsMemberOf($otherOrg, $other)->test('pages::members')->call('show', $foreign->id)->assertDontSee($foreign->email);
});
