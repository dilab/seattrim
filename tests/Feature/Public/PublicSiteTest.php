<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\ZoomConnection;
use App\Models\ZoomMember;
use App\Support\Blog;
use App\Support\Features;
use App\Tenancy\Tenancy;
use App\Zoom\Contracts\ZoomApi;
use App\Zoom\DelegatingZoomApi;
use App\Zoom\FakeZoomClient;

test('public pages render with titles, descriptions and Open Graph tags', function (string $route, array $params, string $title, string $text) {
    $this->get(route($route, $params))
        ->assertOk()
        ->assertSee('<title>'.$title.' · SeatTrim</title>', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee($text)
        ->assertSee('StaticMaker Pte Ltd');
})->with([
    ['home', [], 'Reclaim unused Zoom licenses', 'does not reduce your Zoom bill'],
    ['pricing', [], 'Pricing', '$290'],
    ['free-audit', [], 'Free Zoom license audit', 'Start the free audit'],
    ['support', [], 'Support', 'First response'],
    ['privacy', [], 'Privacy policy', 'meeting content'],
    ['terms', [], 'Terms of service', 'Singapore law'],
    ['docs.index', [], 'Documentation', 'user:update:user:admin'],
    ['docs.show', ['page' => 'add-the-app'], 'Adding SeatTrim to your Zoom account', 'Click Allow'],
    ['docs.show', ['page' => 'using-seattrim'], 'Using SeatTrim', 'Guardrails'],
    ['docs.show', ['page' => 'remove-the-app'], 'Removing SeatTrim and what happens to your data', 'Added Apps'],
    ['docs.show', ['page' => 'troubleshooting'], 'Troubleshooting', 'Zoom error 200'],
    ['docs.show', ['page' => 'faq'], 'FAQ', 'dry run'],
    ['blog.index', [], 'Blog', 'How to free up Zoom licenses'],
    ['blog.show', ['slug' => 'how-to-free-up-zoom-licenses'], 'How to free up Zoom licenses (without cutting anyone off)', 'The safe downgrade checklist'],
    ['blog.show', ['slug' => 'zoom-inactive-users-report-explained'], 'Zoom inactive users report, explained', 'Why last login time misleads'],
    ['blog.show', ['slug' => 'zoom-deactivated-user-still-using-a-license'], 'Zoom deactivated user still using a license? Here is why, and the fix', 'Deactivating removes the license, not the seat'],
]);

test('unknown docs and blog pages are 404', function () {
    $this->get('/docs/nope')->assertNotFound();
    $this->get('/blog/nope')->assertNotFound();
});

test('the sitemap lists every public page and robots hides the app', function () {
    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('content-type', 'application/xml');
    foreach (array_keys(Blog::posts()) as $slug) {
        $response->assertSee(route('blog.show', $slug));
    }
    $response->assertSee(route('free-audit'))->assertDontSee('/dashboard');

    expect(file_get_contents(public_path('robots.txt')))->toContain('Disallow: /members')->toContain('Sitemap:');
});

test('the demo creates a throw-away organization with a completed scan and signs the visitor in', function () {
    $response = $this->get(route('demo'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = auth()->user();
    $organization = $user->currentOrganization;

    expect($user->email)->toEndWith('@demo.seattrim.invalid')
        ->and($organization->setting('demo'))->toBeTrue()
        ->and($organization->name)->toContain('demo');

    $this->get(route('dashboard'))->assertOk()->assertSee('Idle licensed')->assertSee('Scan now');

    $members = app(Tenancy::class)->runAs($organization, fn () => ZoomMember::query()->present()->count());
    expect($members)->toBe(69)
        ->and(Features::allows($organization, Features::BULK_ACTIONS))->toBeTrue();

    // A second visitor gets their own organization and state.
    auth()->logout();
    $this->get(route('demo'))->assertRedirect(route('dashboard'));
    expect(Organization::query()->count())->toBe(2);
});

test('demo organizations are pruned after a day, real ones are not', function () {
    $this->get(route('demo'));
    $demo = auth()->user()->currentOrganization;
    $demo->forceFill(['created_at' => now()->subDays(2)])->save();
    $real = Organization::factory()->withMember(User::factory()->create())->create(['created_at' => now()->subDays(2)]);

    $this->artisan('seattrim:prune-demo')->assertSuccessful();

    expect(Organization::query()->find($demo->id))->toBeNull()
        ->and(Organization::query()->find($real->id))->not->toBeNull()
        ->and(User::query()->where('email', 'like', '%@demo.seattrim.invalid')->count())->toBe(0);
});

test('with the http driver, demo organizations still use the fake client', function () {
    config(['zoom.driver' => 'http']);
    app()->forgetInstance(ZoomApi::class);
    $api = app(ZoomApi::class);
    expect($api)->toBeInstanceOf(DelegatingZoomApi::class);

    $demoOrg = Organization::factory()->create(['settings' => ['demo' => true]]);
    $connection = app(Tenancy::class)->runAs($demoOrg, fn () => ZoomConnection::factory()->create());

    expect($api->userSummary($connection)->rooms)->toBe(2)
        ->and(app(FakeZoomClient::class)->callsTo('userSummary'))->not->toBeEmpty();
});
