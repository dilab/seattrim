<?php

namespace App\Providers;

use App\Zoom\Contracts\ZoomApi;
use App\Zoom\FakeZoomClient;
use App\Zoom\FixtureSet;
use App\Zoom\TokenManager;
use App\Zoom\ZoomClient;
use Illuminate\Support\ServiceProvider;

class ZoomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TokenManager::class);

        $this->app->singleton(FakeZoomClient::class, fn () => new FakeZoomClient(FixtureSet::default()));

        $this->app->singleton(ZoomApi::class, function ($app) {
            return match (config('zoom.driver')) {
                'fake' => $app->make(FakeZoomClient::class),
                default => $app->make(ZoomClient::class),
            };
        });
    }
}
