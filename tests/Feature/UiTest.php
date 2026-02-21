<?php

use function Pest\Laravel\get;

it('loads the docs ui', function () {
    get(config('papyrus.path', 'papyrus-docs'))
        ->assertStatus(200)
        ->assertSee('<div id="papyrus-app">', false)
        ->assertSee('papyrus-docs/assets/', false);
});
