<?php

namespace AhmedTarboush\PapyrusDocs\Console\Commands;

use Illuminate\Console\Command;

class PapyrusInstallCommand extends Command
{
    protected $signature = 'papyrus:install';
    protected $description = 'Install Papyrus Docs';

    public function handle()
    {
        $this->info('Installing Papyrus Docs...');

        $this->call('vendor:publish', [
            '--provider' => "AhmedTarboush\PapyrusDocs\PapyrusServiceProvider",
            '--tag' => "papyrus-config"
        ]);

        $this->call('vendor:publish', [
            '--provider' => "AhmedTarboush\PapyrusDocs\PapyrusServiceProvider",
            '--tag' => "papyrus-provider"
        ]);

        $this->registerPapyrusServiceProvider();

        $this->info('Papyrus Docs installed successfully.');
    }

    /**
     * Register the Papyrus service provider in the application configuration file.
     *
     * @return void
     */
    protected function registerPapyrusServiceProvider()
    {
        $namespace = app()->getNamespace();

        if (file_exists(base_path('bootstrap/providers.php'))) {
            $providers = file_get_contents(base_path('bootstrap/providers.php'));

            if (! str_contains($providers, $namespace . 'Providers\\PapyrusServiceProvider::class')) {
                file_put_contents(base_path('bootstrap/providers.php'), str_replace(
                    '];',
                    "    {$namespace}Providers\PapyrusServiceProvider::class," . PHP_EOL . '];',
                    $providers
                ));
            }
        } else {
            $appConfig = file_get_contents(config_path('app.php'));

            if (! str_contains($appConfig, $namespace . 'Providers\\PapyrusServiceProvider::class')) {
                file_put_contents(config_path('app.php'), str_replace(
                    "{$namespace}Providers\RouteServiceProvider::class,",
                    "{$namespace}Providers\RouteServiceProvider::class," . PHP_EOL . "        {$namespace}Providers\PapyrusServiceProvider::class,",
                    $appConfig
                ));
            }
        }
    }
}
