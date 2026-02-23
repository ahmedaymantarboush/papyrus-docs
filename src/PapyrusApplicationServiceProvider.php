<?php

namespace AhmedTarboush\PapyrusDocs;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class PapyrusApplicationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->authorization();
    }

    /**
     * Configure the Papyrus Docs authorization services.
     *
     * @return void
     */
    protected function authorization()
    {
        $this->gate();
    }

    /**
     * Register the Papyrus Docs gate.
     *
     * This gate determines who can access Papyrus Docs in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewPapyrusDocs', function ($user = null) {
            return in_array(app()->environment(), ['local', 'testing']);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
