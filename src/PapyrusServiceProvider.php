<?php

namespace AhmedTarboush\PapyrusDocs;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * PapyrusServiceProvider — Registers the Papyrus Docs package.
 *
 * Responsibilities:
 *   - Merge and publish configuration
 *   - Register routes (conditionally, based on `enabled` config)
 *   - Register views
 *   - Provide the initial stub for the Application Service Provider
 *   - Register the PapyrusGenerator as a singleton
 *   - Register artisan commands
 */
class PapyrusServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        // ── Guard: Skip all registration if disabled ─────────────────
        if (! config('papyrus.enabled', true)) {
            return;
        }

        // 1. Load Routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // 2. Load Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'papyrus');

        // 3. Publish Configuration
        $this->publishes([
            __DIR__ . '/../config/papyrus.php' => config_path('papyrus.php'),
        ], 'papyrus-config');

        // 4. Publish Service Provider
        $this->publishes([
            __DIR__ . '/../stubs/PapyrusServiceProvider.stub' => app_path('Providers/PapyrusServiceProvider.php'),
        ], 'papyrus-provider');

        // 5. Register Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\PapyrusInstallCommand::class,
                Console\Commands\PapyrusExportCommand::class,
            ]);
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        // ── Merge package config with application config ─────────────
        $this->mergeConfigFrom(
            __DIR__ . '/../config/papyrus.php',
            'papyrus'
        );

        // ── Register PapyrusGenerator as singleton ───────────────────
        $this->app->singleton(PapyrusGenerator::class, function ($app) {
            return new PapyrusGenerator();
        });
    }
}
