<?php

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
});

require __DIR__.'/settings.php';
