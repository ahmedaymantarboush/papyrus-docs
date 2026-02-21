<?php

use Illuminate\Support\Facades\Route;
use AhmedTarboush\PapyrusDocs\PapyrusGenerator;

class RouteScannerController
{
    public function index() {}
}

it('can find registered routes', function () {
    Route::get('/api/test-route', [RouteScannerController::class, 'index']);

    $generator = new PapyrusGenerator();
    $routes = $generator->scan();

    expect($routes)->not->toBeEmpty();

    // Flatten groups to find our specific route
    $allRoutes = $routes->pluck('routes')->flatten();
    $testRoute = $allRoutes->first(fn($r) => $r->uri === 'api/test-route');

    expect($testRoute)->not->toBeNull();
    expect($testRoute->uri)->toBe('api/test-route');
});
