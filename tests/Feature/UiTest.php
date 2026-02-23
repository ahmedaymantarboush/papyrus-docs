<?php

use function Pest\Laravel\get;

it('loads the docs ui and prevents html caching', function () {
    get(config('papyrus.path', 'papyrus-docs'))
        ->assertStatus(200)
        ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT')
        ->assertSee('<div id="papyrus-app">', false);
});
