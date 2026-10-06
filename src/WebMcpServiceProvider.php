<?php

namespace Fosseva\WebMcp;

use Fosseva\WebMcp\Http\Controllers\ExecuteToolController;
use Fosseva\WebMcp\Http\Controllers\ManifestController;
use Fosseva\WebMcp\Http\Controllers\RuntimeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WebMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webmcp.php', 'webmcp');
        $this->app->bind(ExposureTokens::class);
        $this->app->scoped(PageTools::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/webmcp.php' => config_path('webmcp.php'),
            ], 'webmcp-config');

        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webmcp');

        if (! config('webmcp.enabled', true)) {
            return;
        }

        Route::middleware(config('webmcp.middleware', ['web']))
            ->prefix(trim((string) config('webmcp.path', '_webmcp'), '/'))
            ->group(function (): void {
                Route::get('/manifest', ManifestController::class)->name('webmcp.manifest');
                Route::post('/execute', ExecuteToolController::class)
                    ->name('webmcp.execute');
            });

        Route::get(trim((string) config('webmcp.path', '_webmcp'), '/').'/runtime.js', RuntimeController::class)
            ->withoutMiddleware(config('webmcp.middleware', ['web']))
            ->name('webmcp.runtime');
    }
}
