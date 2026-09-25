<?php

use App\Http\Controllers\AuditExportController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\KeepLicenseController;
use App\Http\Controllers\Zoom\OAuthController;
use App\Http\Controllers\Zoom\WebhookController;
use App\Models\Organization;
use App\Support\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- Public site
Route::view('/', 'public.landing')->name('home');
Route::view('pricing', 'public.pricing')->name('pricing');
Route::view('zoom-license-audit', 'public.audit')->name('free-audit');
Route::view('support', 'public.support')->name('support');
Route::view('privacy', 'public.legal.privacy')->name('privacy');
Route::view('terms', 'public.legal.terms')->name('terms');
Route::view('docs', 'public.docs.index')->name('docs.index');
Route::get('docs/{page}', function (string $page) {
    abort_unless(in_array($page, ['add-the-app', 'using-seattrim', 'remove-the-app', 'troubleshooting', 'faq'], true), 404);

    return view('public.docs.'.$page);
})->name('docs.show');
Route::get('blog', fn () => view('public.blog.index', ['posts' => Blog::posts()]))->name('blog.index');
Route::get('blog/{slug}', function (string $slug) {
    $post = Blog::find($slug);
    abort_if($post === null, 404);

    return view('public.blog.show', ['slug' => $slug, 'post' => $post]);
})->name('blog.show');
Route::get('demo', [DemoController::class, 'enter'])->name('demo')->middleware('throttle:10,1');
Route::get('sitemap.xml', function () {
    $urls = [route('home'), route('pricing'), route('free-audit'), route('support'), route('privacy'), route('terms'), route('docs.index'),
        ...array_map(fn ($p) => route('docs.show', $p), ['add-the-app', 'using-seattrim', 'remove-the-app', 'troubleshooting', 'faq']),
        route('blog.index'), ...array_map(fn ($slug) => route('blog.show', $slug), array_keys(Blog::posts()))];
    $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        .implode('', array_map(fn ($u) => '<url><loc>'.e($u).'</loc></url>', $urls)).'</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('organizations/create', 'pages::organizations.create')->name('organizations.create');

    Route::post('organizations/{organization}/switch', function (Request $request, Organization $organization) {
        abort_unless($request->user()->switchToOrganization($organization), 403);

        return redirect()->route('dashboard');
    })->name('organizations.switch');
});

Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('onboarding', 'pages::onboarding')->name('onboarding');
    Route::livewire('members', 'pages::members')->name('members');
    Route::livewire('audit', 'pages::audit')->name('audit');
    Route::livewire('automation', 'pages::automation')->name('automation');
    Route::livewire('exclusions', 'pages::exclusions')->name('exclusions');
    Route::livewire('billing', 'pages::billing')->name('billing');
    Route::post('billing/checkout/{tier}', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
    Route::get('audit/export.csv', AuditExportController::class)->name('audit.export');

    Route::livewire('connection', 'pages::connection')->name('connection.edit');
    Route::post('zoom/connect', [OAuthController::class, 'connect'])->name('zoom.connect');
    Route::get('zoom/callback', [OAuthController::class, 'callback'])->name('zoom.callback');
});

// "Keep my license" from the warning email: signed, expiring, no login.
Route::middleware('signed')->group(function () {
    Route::get('keep/{notice}', [KeepLicenseController::class, 'show'])->name('keep-license');
    Route::post('keep/{notice}', [KeepLicenseController::class, 'store'])->name('keep-license.store');
});

// Signature-verified in the controller; no session, no CSRF (see bootstrap/app.php).
Route::post('zoom/webhook', WebhookController::class)->name('zoom.webhook');

require __DIR__.'/settings.php';
