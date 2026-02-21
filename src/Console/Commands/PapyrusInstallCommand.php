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
            '--tag' => "papyrus-assets"
        ]);

        $this->info('Papyrus Docs installed successfully.');
    }
}
