<?php

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
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('connection', 'pages::connection')->name('connection.edit');
    Route::post('zoom/connect', [OAuthController::class, 'connect'])->name('zoom.connect');
    Route::get('zoom/callback', [OAuthController::class, 'callback'])->name('zoom.callback');
});

// Signature-verified in the controller; no session, no CSRF (see bootstrap/app.php).
Route::post('zoom/webhook', WebhookController::class)->name('zoom.webhook');

require __DIR__.'/settings.php';
