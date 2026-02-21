<?php

namespace AhmedTarboush\PapyrusDocs\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use AhmedTarboush\PapyrusDocs\PapyrusServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            PapyrusServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('app.env', 'testing');
        config()->set('database.default', 'testing');
        config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }
}
