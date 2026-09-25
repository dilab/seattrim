<?php

use App\Http\Controllers\AuditExportController;
use App\Http\Controllers\KeepLicenseController;
use App\Http\Controllers\Zoom\OAuthController;
use App\Http\Controllers\Zoom\WebhookController;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

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
    Route::view('billing', 'billing-placeholder')->name('billing');
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
