<?php

use AhmedTarboush\PapyrusDocs\Exporters\OpenApiGenerator;
use AhmedTarboush\PapyrusDocs\Exporters\PostmanGenerator;

// ═══════════════════════════════════════════════════════════════════════════
// Export Generators — Unit Tests
// ═══════════════════════════════════════════════════════════════════════════

// ── Helper: Build a mock schema collection ───────────────────────────────

function mockSchema(array $routes = []): \Illuminate\Support\Collection
{
    $defaultRoute = (object) [
        'uri'        => 'api/users',
        'methods'    => ['GET', 'HEAD'],
        'title'      => 'List Users',
        'description' => 'Returns a paginated list of users.',
        'group'      => 'Users',
        'routeName'  => 'users.index',
        'bodyParams' => [],
        'queryParams' => [],
        'headers'    => [],
        'responses'  => [],
        'response'   => null,
    ];

    $routeObjects = empty($routes) ? [$defaultRoute] : $routes;

    return collect([
        [
            'name'   => 'Users',
            'routes' => collect($routeObjects),
        ],
    ]);
}

function mockPostRoute(): object
{
    return (object) [
        'uri'        => 'api/users',
        'methods'    => ['POST'],
        'title'      => 'Create User',
        'description' => 'Creates a new user.',
        'group'      => 'Users',
        'routeName'  => 'users.store',
        'bodyParams' => [
            ['key' => 'name', 'type' => 'text', 'required' => true, 'description' => 'User name', 'min' => null, 'max' => 255, 'pattern' => null, 'accept' => null, 'options' => null],
            ['key' => 'email', 'type' => 'email', 'required' => true, 'description' => 'User email', 'min' => null, 'max' => null, 'pattern' => null, 'accept' => null, 'options' => null],
            ['key' => 'age', 'type' => 'number', 'required' => false, 'description' => '', 'min' => 0, 'max' => 150, 'pattern' => null, 'accept' => null, 'options' => null],
        ],
        'queryParams' => [],
        'headers'    => [],
        'responses'  => [],
        'response'   => null,
    ];
}

function mockFileRoute(): object
{
    return (object) [
        'uri'        => 'api/upload',
        'methods'    => ['POST'],
        'title'      => 'Upload File',
        'description' => '',
        'group'      => 'Uploads',
        'routeName'  => null,
        'bodyParams' => [
            ['key' => 'document', 'type' => 'file', 'required' => true, 'description' => '', 'accept' => '.pdf,.doc', 'min' => null, 'max' => null, 'pattern' => null, 'options' => null],
            ['key' => 'title', 'type' => 'text', 'required' => true, 'description' => '', 'min' => null, 'max' => null, 'pattern' => null, 'accept' => null, 'options' => null],
        ],
        'queryParams' => [],
        'headers'    => [],
        'responses'  => [],
        'response'   => null,
    ];
}

function mockRouteWithPathParams(): object
{
    return (object) [
        'uri'        => 'api/users/{user}/posts/{post?}',
        'methods'    => ['GET', 'HEAD'],
        'title'      => 'Get User Post',
        'description' => '',
        'group'      => 'Users',
        'routeName'  => null,
        'bodyParams' => [],
        'queryParams' => [],
        'headers'    => [],
        'responses'  => [],
        'response'   => null,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// OpenAPI Generator Tests
// ═══════════════════════════════════════════════════════════════════════════

describe('OpenApiGenerator', function () {

    beforeEach(function () {
        config()->set('papyrus.open_api', [
            'title'            => 'Test API',
            'description'      => 'Test API Docs',
            'version'          => '3.0.0',
            'document_version' => '1.0.0',
            'server_url'       => 'https://api.example.com',
            'license'          => 'MIT',
            'security'         => ['type' => 'bearer'],
            'responses'        => [],
            'exclude_http_methods' => [],
            'delete_with_body' => false,
        ]);
    });

    it('generates valid OpenAPI 3.0 structure', function () {
        $gen = new OpenApiGenerator();
        $spec = $gen->generate(mockSchema());

        expect($spec['openapi'])->toBe('3.0.0');
        expect($spec['info']['title'])->toBe('Test API');
        expect($spec['info']['description'])->toBe('Test API Docs');
        expect($spec['info']['version'])->toBe('1.0.0');
        expect($spec['servers'][0]['url'])->toBe('https://api.example.com');
    });

    it('generates paths from schema', function () {
        $gen = new OpenApiGenerator();
        $spec = $gen->generate(mockSchema());

        expect($spec['paths'])->toHaveKey('/api/users');
        expect($spec['paths']['/api/users'])->toHaveKey('get');
        expect($spec['paths']['/api/users']['get']['summary'])->toBe('List Users');
    });

    it('generates request body for POST endpoints', function () {
        $gen = new OpenApiGenerator();
        $spec = $gen->generate(mockSchema([mockPostRoute()]));

        expect($spec['paths']['/api/users'])->toHaveKey('post');
        expect($spec['paths']['/api/users']['post'])->toHaveKey('requestBody');

        $body = $spec['paths']['/api/users']['post']['requestBody'];
        expect($body['content'])->toHaveKey('application/json');

        $schema = $body['content']['application/json']['schema'];
        expect($schema['properties'])->toHaveKey('name');
        expect($schema['properties'])->toHaveKey('email');
        expect($schema['properties']['email']['format'])->toBe('email');
        expect($schema['required'])->toContain('name');
        expect($schema['required'])->toContain('email');
    });

    it('uses multipart/form-data when file fields are present', function () {
        $gen = new OpenApiGenerator();
        $schema = collect([['name' => 'Uploads', 'routes' => collect([mockFileRoute()])]]);
        $spec = $gen->generate($schema);

        $body = $spec['paths']['/api/upload']['post']['requestBody'];
        expect($body['content'])->toHaveKey('multipart/form-data');
    });

    it('extracts path parameters', function () {
        $gen = new OpenApiGenerator();
        $schema = collect([['name' => 'Users', 'routes' => collect([mockRouteWithPathParams()])]]);
        $spec = $gen->generate($schema);

        $params = $spec['paths']['/api/users/{user}/posts/{post}']['get']['parameters'];
        $names = collect($params)->pluck('name')->toArray();
        expect($names)->toContain('user');
        expect($names)->toContain('post');

        $userParam = collect($params)->firstWhere('name', 'user');
        expect($userParam['required'])->toBeTrue();

        $postParam = collect($params)->firstWhere('name', 'post');
        expect($postParam['required'])->toBeFalse();
    });

    it('generates bearer security scheme', function () {
        $gen = new OpenApiGenerator();
        $spec = $gen->generate(mockSchema());

        expect($spec['components']['securitySchemes']['papyrus_auth']['type'])->toBe('http');
        expect($spec['components']['securitySchemes']['papyrus_auth']['scheme'])->toBe('bearer');
        expect($spec['security'])->toBe([['papyrus_auth' => []]]);
    });

    it('generates apiKey security scheme', function () {
        config()->set('papyrus.open_api.security', [
            'type'     => 'apiKey',
            'name'     => 'X-API-KEY',
            'position' => 'header',
        ]);

        $gen = new OpenApiGenerator();
        $spec = $gen->generate(mockSchema());

        expect($spec['components']['securitySchemes']['papyrus_auth']['type'])->toBe('apiKey');
        expect($spec['components']['securitySchemes']['papyrus_auth']['name'])->toBe('X-API-KEY');
    });

    it('excludes configured HTTP methods', function () {
        config()->set('papyrus.open_api.exclude_http_methods', ['DELETE']);

        $deleteRoute = (object) [
            'uri' => 'api/users/{user}',
            'methods' => ['DELETE'],
            'title' => 'Delete User',
            'description' => '',
            'group' => 'Users',
            'routeName' => null,
            'bodyParams' => [],
            'queryParams' => [],
            'headers' => [],
            'responses' => [],
            'response' => null,
        ];

        $gen = new OpenApiGenerator();
        $schema = collect([['name' => 'Users', 'routes' => collect([$deleteRoute])]]);
        $spec = $gen->generate($schema);

        // DELETE should be excluded
        if (isset($spec['paths']['/api/users/{user}'])) {
            expect($spec['paths']['/api/users/{user}'])->not->toHaveKey('delete');
        }
    });

    it('maps node types to OpenAPI schema types correctly', function () {
        $gen = new OpenApiGenerator();
        $routeWithTypes = (object) [
            'uri' => 'api/test',
            'methods' => ['POST'],
            'title' => 'Test',
            'description' => '',
            'group' => 'Test',
            'routeName' => null,
            'queryParams' => [],
            'headers' => [],
            'responses' => [],
            'response' => null,
            'bodyParams' => [
                ['key' => 'count', 'type' => 'number', 'required' => false],
                ['key' => 'is_active', 'type' => 'boolean', 'required' => false],
                ['key' => 'email', 'type' => 'email', 'required' => false],
                ['key' => 'website', 'type' => 'url', 'required' => false],
                ['key' => 'birthday', 'type' => 'date', 'required' => false],
                ['key' => 'secret', 'type' => 'password', 'required' => false],
                ['key' => 'token', 'type' => 'uuid', 'required' => false],
                ['key' => 'avatar', 'type' => 'file', 'required' => false],
            ],
        ];

        $schema = collect([['name' => 'Test', 'routes' => collect([$routeWithTypes])]]);
        $spec = $gen->generate($schema);

        $props = $spec['paths']['/api/test']['post']['requestBody']['content']['multipart/form-data']['schema']['properties'];
        expect($props['count']['type'])->toBe('number');
        expect($props['is_active']['type'])->toBe('boolean');
        expect($props['email']['format'])->toBe('email');
        expect($props['website']['format'])->toBe('uri');
        expect($props['birthday']['format'])->toBe('date');
        expect($props['secret']['format'])->toBe('password');
        expect($props['token']['format'])->toBe('uuid');
        expect($props['avatar']['format'])->toBe('binary');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// Postman Generator Tests
// ═══════════════════════════════════════════════════════════════════════════

describe('PostmanGenerator', function () {

    beforeEach(function () {
        config()->set('papyrus.title', 'Test API');
        config()->set('papyrus.default_headers', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
        config()->set('papyrus.open_api.server_url', 'https://api.example.com');
    });

    it('generates valid Postman v2.1 structure', function () {
        $gen = new PostmanGenerator();
        $collection = $gen->generate(mockSchema());

        expect($collection['info']['name'])->toBe('Test API');
        expect($collection['info']['schema'])->toBe('https://schema.getpostman.com/json/collection/v2.1.0/collection.json');
        expect($collection['item'])->toBeArray();
    });

    it('groups endpoints into folders', function () {
        $gen = new PostmanGenerator();
        $collection = $gen->generate(mockSchema());

        expect($collection['item'][0]['name'])->toBe('Users');
        expect($collection['item'][0]['item'])->toBeArray();
    });

    it('includes default headers', function () {
        $gen = new PostmanGenerator();
        $collection = $gen->generate(mockSchema());

        $request = $collection['item'][0]['item'][0]['request'];
        $headerKeys = collect($request['header'])->pluck('key')->toArray();
        expect($headerKeys)->toContain('Accept');
        expect($headerKeys)->toContain('Content-Type');
    });

    it('generates request URL with Postman path format', function () {
        $gen = new PostmanGenerator();
        $schema = collect([['name' => 'Users', 'routes' => collect([mockRouteWithPathParams()])]]);
        $collection = $gen->generate($schema);

        $url = $collection['item'][0]['item'][0]['request']['url'];
        expect($url['protocol'])->toBe('https');
        expect($url['host'])->toBe(['api', 'example', 'com']);

        // Path params should use :param format
        $pathString = implode('/', $url['path']);
        expect($pathString)->toContain(':user');
        expect($pathString)->toContain(':post');
    });

    it('generates JSON body for POST endpoints', function () {
        $gen = new PostmanGenerator();
        $collection = $gen->generate(mockSchema([mockPostRoute()]));

        $request = $collection['item'][0]['item'][0]['request'];
        expect($request['method'])->toBe('POST');
        expect($request['body']['mode'])->toBe('raw');
        expect($request['body']['options']['raw']['language'])->toBe('json');

        $bodyJson = json_decode($request['body']['raw'], true);
        expect($bodyJson)->toHaveKey('name');
        expect($bodyJson)->toHaveKey('email');
        expect($bodyJson['email'])->toBe('user@example.com');
    });

    it('generates formdata body when file fields are present', function () {
        $gen = new PostmanGenerator();
        $schema = collect([['name' => 'Uploads', 'routes' => collect([mockFileRoute()])]]);
        $collection = $gen->generate($schema);

        $request = $collection['item'][0]['item'][0]['request'];
        expect($request['body']['mode'])->toBe('formdata');

        $formKeys = collect($request['body']['formdata'])->pluck('key')->toArray();
        expect($formKeys)->toContain('document');
        expect($formKeys)->toContain('title');

        $fileField = collect($request['body']['formdata'])->firstWhere('key', 'document');
        expect($fileField['type'])->toBe('file');
    });

    it('generates path parameter variables', function () {
        $gen = new PostmanGenerator();
        $schema = collect([['name' => 'Users', 'routes' => collect([mockRouteWithPathParams()])]]);
        $collection = $gen->generate($schema);

        $url = $collection['item'][0]['item'][0]['request']['url'];
        expect($url['variable'])->toBeArray();

        $varKeys = collect($url['variable'])->pluck('key')->toArray();
        expect($varKeys)->toContain('user');
        expect($varKeys)->toContain('post');
    });

    it('does not add body for GET requests', function () {
        $gen = new PostmanGenerator();
        $collection = $gen->generate(mockSchema());

        $request = $collection['item'][0]['item'][0]['request'];
        expect($request['method'])->toBe('GET');
        expect($request)->not->toHaveKey('body');
    });
});
