<?php

use Illuminate\Support\Facades\Route;
use AhmedTarboush\PapyrusDocs\Http\Controllers\PapyrusController;

/*
|--------------------------------------------------------------------------
| Papyrus Docs Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with the configured URL path (default: papyrus-docs)
| and wrapped in the configured middleware stack (default: web).
|
| Config keys used:
|   - papyrus.url (with fallback to papyrus.path for backward compatibility)
|   - papyrus.middlewares (with fallback to papyrus.middleware)
|
*/

Route::group([
    'prefix'      => config('papyrus.url', config('papyrus.path', 'papyrus-docs')),
    'middleware'  => config('papyrus.middlewares', config('papyrus.middleware', ['web'])),
], function () {

    // The UI
    Route::get('/', [PapyrusController::class, 'index'])
        ->name('papyrus.index');

    // The Internal API
    Route::get('/api/schema', [PapyrusController::class, 'schema'])
        ->name('papyrus.schema');

    // Asset Serving
    Route::get('/assets/{path}', [PapyrusController::class, 'assets'])
        ->where('path', '.*')
        ->name('papyrus.assets');

    // Favicon Serving
    Route::get('/favicon/{file?}', [PapyrusController::class, 'favicon'])
        ->name('papyrus.favicon');

    // Export Endpoints
    Route::get('/export/openapi', [PapyrusController::class, 'exportOpenApi'])
        ->name('papyrus.export.openapi');

    Route::get('/export/postman', [PapyrusController::class, 'exportPostman'])
        ->name('papyrus.export.postman');
});
