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
 *   - Define the default authorization gate
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

        // 4. Publish Assets (for production deployment)
        // Copies built assets from the package's dist/build folder
        // to the application's public/vendor folder
        $this->publishes([
            __DIR__ . '/../dist/build' => public_path('vendor/papyrus-docs'),
        ], 'papyrus-assets');

        // 5. Define Default Gate
        // Override this gate in your AuthServiceProvider for production
        Gate::define('viewPapyrusDocs', function ($user = null) {
            return app()->environment('local', 'testing', 'development');
        });

        // 6. Register Commands
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
