<?php

use AhmedTarboush\PapyrusDocs\PapyrusGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════
// DocBlock Directives — Tests via PapyrusGenerator
// ═══════════════════════════════════════════════════════════════════════════

// ── Test Controllers ─────────────────────────────────────────────────────

class DocBlockBodyParamController
{
    /**
     * Create User
     *
     * @papyrus-bodyParam string email The user login email (required)
     * @papyrus-bodyParam string name The user full name
     * @papyrus-bodyParam file avatar The profile picture
     * @papyrus-bodyParam integer age User age
     * @papyrus-bodyParam string address.street Street address
     * @papyrus-bodyParam string address.city City name
     */
    public function store() {}
}

class DocBlockDescriptionController
{
    /**
     * List Items
     *
     * @papyrus-description-start
     * # Welcome to the Items API
     * This endpoint returns a paginated list of items.
     *
     * **Features:**
     * - Filtering
     * - Sorting
     * @papyrus-description-end
     */
    public function index() {}
}

class DocBlockQueryParamController
{
    /**
     * Search Users
     *
     * @papyrus-queryParam string search Search query
     * @papyrus-queryParam integer page Page number
     * @papyrus-queryParam integer per_page Items per page
     */
    public function index() {}
}

class DocBlockHeaderController
{
    /**
     * Protected Resource
     *
     * @papyrus-header X-Custom-Token A custom auth token
     * @papyrus-header X-Request-ID Unique request identifier
     */
    public function show() {}
}

class DocBlockResponseExampleController
{
    /**
     * Get User
     *
     * @papyrus-responseExample-start 200
     * {
     *   "id": 1,
     *   "name": "John Doe",
     *   "email": "john@example.com"
     * }
     * @papyrus-responseExample-end
     *
     * @papyrus-responseExample-start 404
     * {
     *   "message": "User not found"
     * }
     * @papyrus-responseExample-end
     */
    public function show() {}
}

class DocBlockResponseParamController
{
    /**
     * Get Profile
     *
     * @papyrus-responseParam 200 string name The user name
     * @papyrus-responseParam 200 string email The user email
     * @papyrus-responseParam 200 integer age User age
     * @papyrus-responseParam 422 string message Error message
     */
    public function show() {}
}

class DocBlockBypassController
{
    /**
     * Create Item
     *
     * @papyrus-bodyParam string title Item title (required)
     * @papyrus-bodyParam number price Item price
     */
    public function store(DocBlockBypassRequest $request) {}
}

class DocBlockBypassRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name'  => 'required|string',
            'email' => 'required|email',
        ];
    }
}

// ── Tests ────────────────────────────────────────────────────────────────

describe('@papyrus-bodyParam directives', function () {

    it('extracts body params from docblock', function () {
        Route::post('/docblock-body', [DocBlockBodyParamController::class, 'store']);

        $generator = new PapyrusGenerator();
        $groups = $generator->scan();
        $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'docblock-body');

        expect($route)->not->toBeNull();
        expect($route->bodyParams)->toBeArray();
        expect($route->bodyParams)->not->toBeEmpty();

        // Check the flat params (email, name, age)
        $keys = collect($route->bodyParams)->pluck('key')->toArray();
        expect($keys)->toContain('email');
        expect($keys)->toContain('name');
        expect($keys)->toContain('avatar');
        expect($keys)->toContain('age');
    });

    it('builds nested schema from dot-notation', function () {
        Route::post('/docblock-body-nested', [DocBlockBodyParamController::class, 'store']);

        $generator = new PapyrusGenerator();
        $groups = $generator->scan();
        $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'docblock-body-nested');

        // address should be an object with schema containing street and city
        $address = collect($route->bodyParams)->firstWhere('key', 'address');
        expect($address)->not->toBeNull();
        expect($address['type'])->toBe('object');
        expect($address['schema'])->toBeArray();

        $schemaKeys = collect($address['schema'])->pluck('key')->toArray();
        expect($schemaKeys)->toContain('street');
        expect($schemaKeys)->toContain('city');
    });

    it('bypasses FormRequest rules when @papyrus-bodyParam is present', function () {
        Route::post('/docblock-bypass', [DocBlockBypassController::class, 'store']);

        $generator = new PapyrusGenerator();
        $groups = $generator->scan();
        $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'docblock-bypass');

        // Should have 'title' and 'price' from DocBlock, NOT 'name' and 'email' from FormRequest
        $keys = collect($route->bodyParams)->pluck('key')->toArray();
        expect($keys)->toContain('title');
        expect($keys)->toContain('price');
        expect($keys)->not->toContain('name');
        expect($keys)->not->toContain('email');
    });
});

describe('@papyrus-description directives', function () {

    it('extracts multi-line description from block directive', function () {
        Route::get('/docblock-desc', [DocBlockDescriptionController::class, 'index']);

        $generator = new PapyrusGenerator();
        $groups = $generator->scan();
        $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'docblock-desc');

        expect($route->description)->toContain('# Welcome to the Items API');
        expect($route->description)->toContain('**Features:**');
        expect($route->description)->toContain('- Filtering');
    });
});

describe('@papyrus-queryParam directives', function () {

    it('extracts query params from docblock', function () {
        Route::get('/docblock-query', [DocBlockQueryParamController::class, 'index']);

        $generator = new PapyrusGenerator();
        $groups = $generator->scan();
        $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'docblock-query');

        expect($route->queryParams)->toBeArray();
        $keys = collect($route->queryParams)->pluck('key')->toArray();
        expect($keys)->toContain('search');
        expect($keys)->toContain('page');
        expect($keys)->toContain('per_page');
    });
});

describe('@papyrus-header directives', function () {

    it('extracts headers from docblock', function () {
        Route::get('/docblock-header', [DocBlockHeaderController::class, 'show']);

        $generator = new PapyrusGenerator();
        $groups = $generator->scan();
        $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'docblock-header');

        expect($route->headers)->toBeArray();
        expect($route->headers)->toHaveCount(2);

        $headerKeys = collect($route->headers)->pluck('key')->toArray();
        expect($headerKeys)->toContain('X-Custom-Token');
        expect($headerKeys)->toContain('X-Request-ID');
    });
});

describe('@papyrus-responseExample directives', function () {

    it('extracts response examples by status code', function () {
        Route::get('/docblock-response-example', [DocBlockResponseExampleController::class, 'show']);

        $generator = new PapyrusGenerator();
        $groups = $generator->scan();
        $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'docblock-response-example');

        expect($route->responses)->toBeArray();
        expect($route->responses)->toHaveKey('200');
        expect($route->responses)->toHaveKey('404');

        // 200 response example should be decoded JSON
        expect($route->responses['200']['responseExample'])->toBeArray();
        expect($route->responses['200']['responseExample']['name'])->toBe('John Doe');

        // 404 response example
        expect($route->responses['404']['responseExample'])->toBeArray();
        expect($route->responses['404']['responseExample']['message'])->toBe('User not found');
    });
});

describe('@papyrus-responseParam directives', function () {

    it('extracts response params grouped by status code', function () {
        Route::get('/docblock-response-param', [DocBlockResponseParamController::class, 'show']);

        $generator = new PapyrusGenerator();
        $groups = $generator->scan();
        $route = $groups->pluck('routes')->flatten()->first(fn($r) => $r->uri === 'docblock-response-param');

        expect($route->responses)->toHaveKey('200');
        expect($route->responses['200']['schema'])->toBeArray();

        $keys200 = collect($route->responses['200']['schema'])->pluck('key')->toArray();
        expect($keys200)->toContain('name');
        expect($keys200)->toContain('email');
        expect($keys200)->toContain('age');

        expect($route->responses)->toHaveKey('422');
        $keys422 = collect($route->responses['422']['schema'])->pluck('key')->toArray();
        expect($keys422)->toContain('message');
    });
});
