<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Title
    |--------------------------------------------------------------------------
    |
    | The title displayed in the Papyrus Docs UI header, browser tab,
    | and any exported OpenAPI documents.
    |
    */

    'title' => 'Papyrus - Laravel API Docs',

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Toggle the entire Papyrus Docs interface on or off.
    | When disabled, all Papyrus routes will return 404.
    |
    */

    'enabled' => env('PAPYRUS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the schema endpoint will include additional
    | metadata such as raw reflection data, timing info, and
    | PHP memory usage for development purposes.
    |
    */

    'debug' => env('PAPYRUS_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | URL Path
    |--------------------------------------------------------------------------
    |
    | The base URL path where Papyrus Docs will be accessible.
    | All Papyrus routes (UI, API, assets, favicon) are prefixed
    | with this path.
    |
    */

    'url' => 'papyrus-docs',

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL used for API requests in the frontend playground and snippets.
    | If not set, it defaults to the APP_URL. The frontend will also fallback
    | to the current domain if this is left empty.
    |
    */

    'base_url' => env('PAPYRUS_BASE_URL', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware stack applied to all Papyrus routes.
    | Add authentication or authorization middleware here
    | to protect the documentation from unauthorized access.
    |
    */

    'middlewares' => ['web', 'can:viewPapyrusDocs'],

    /*
    |--------------------------------------------------------------------------
    | Only Route URI Start With
    |--------------------------------------------------------------------------
    |
    | If set, only routes whose URI begins with this prefix will be
    | included in the documentation. Leave empty to include all routes.
    |
    | Example: "api/" — only document API routes
    |
    */

    'only_route_uri_start_with' => '',

    /*
    |--------------------------------------------------------------------------
    | Visible Matching Routes
    |--------------------------------------------------------------------------
    |
    | An array of regex patterns. If not empty, ONLY routes whose URI matches
    | one of these patterns will be included in the documentation.
    | Uses preg_match — patterns must include delimiters.
    |
    */

    'visible_matching' => [],

    /*
    |--------------------------------------------------------------------------
    | Hide Matching Routes
    |--------------------------------------------------------------------------
    |
    | An array of regex patterns. Any route whose URI matches
    | one of these patterns will be excluded from the documentation.
    | Uses preg_match — patterns must include delimiters.
    |
    */

    'hide_matching' => [
        '#^telescope#',
        '#^docs#',
        '#^papyrus-docs#',
        '#^api-docs#',
        '#^api/docs#',
        '#^sanctum#',
        '#^_ignition#',
        '#^_tt#',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rules Methods
    |--------------------------------------------------------------------------
    |
    | The method name(s) to call on FormRequest classes to extract
    | validation rules. Supports multiple methods for packages that
    | use custom method names (e.g., 'createRules', 'updateRules').
    |
    */

    'rules_methods' => ['rules'],

    /*
    |--------------------------------------------------------------------------
    | Default Responses
    |--------------------------------------------------------------------------
    |
    | The list of HTTP status codes displayed by default in the
    | Playground response section. These appear as reference badges
    | so developers know the expected response codes for each endpoint.
    |
    */

    'default_responses' => [
        '200',
        '400',
        '401',
        '403',
        '404',
        '405',
        '422',
        '429',
        '500',
        '503',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Headers
    |--------------------------------------------------------------------------
    |
    | Headers that are pre-populated in the Playground's headers editor
    | on initial load. Users can override or disable them at runtime.
    |
    | Note: When the request payload contains files and is sent as
    | FormData, the frontend will automatically strip Content-Type
    | to let the browser set it with the correct multipart boundary.
    |
    */

    'default_headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Accept-Language' => 'en',
        'Authorization' => 'Bearer ',
    ],

    /*
    |--------------------------------------------------------------------------
    | Group By
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic route grouping based on URI patterns.
    | Each entry in 'uri_patterns' is a regex (without delimiters).
    | Routes are grouped by the first matching pattern; unmatched
    | routes fall into a "General" group.
    |
    | Example: '^api/v[\d]+/' groups versioned API routes together.
    |
    */

    'group_by' => [
        'uri_patterns' => [
            '^api/v[\d]+/',
            '^api/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Export
    |--------------------------------------------------------------------------
    |
    | Configuration for generating an OpenAPI 3.0 specification document
    | from your scanned routes. Used when exporting via artisan command
    | or the export endpoint.
    |
    */

    'open_api' => [

        /*
        |----------------------------------------------------------------------
        | Document Title
        |----------------------------------------------------------------------
        |
        | The title of the OpenAPI specification document.
        |
        */

        'title' => 'Papyrus - Laravel API Docs',

        /*
        |----------------------------------------------------------------------
        | Document Description
        |----------------------------------------------------------------------
        |
        | A brief description of the API for the OpenAPI spec.
        |
        */

        'description' => 'API documentation auto-generated by Papyrus Docs.',

        /*
        |----------------------------------------------------------------------
        | OpenAPI Specification Version
        |----------------------------------------------------------------------
        |
        | The version of the OpenAPI specification to target.
        |
        | Options: "3.0.0", "3.1.0"
        |
        */

        'version' => '3.0.0',

        /*
        |----------------------------------------------------------------------
        | Document Version
        |----------------------------------------------------------------------
        |
        | The version of your API documentation itself.
        |
        */

        'document_version' => '1.0.0',

        /*
        |----------------------------------------------------------------------
        | License
        |----------------------------------------------------------------------
        |
        | The license name for the API documentation.
        |
        */

        'license' => 'Apache 2.0',

        /*
        |----------------------------------------------------------------------
        | Server URL
        |----------------------------------------------------------------------
        |
        | The base server URL used in the OpenAPI spec.
        |
        */

        'server_url' => env('APP_URL', 'http://localhost'),

        /*
        |----------------------------------------------------------------------
        | DELETE With Body
        |----------------------------------------------------------------------
        |
        | Whether DELETE requests should include a request body
        | in the OpenAPI specification.
        |
        */

        'delete_with_body' => false,

        /*
        |----------------------------------------------------------------------
        | Exclude HTTP Methods
        |----------------------------------------------------------------------
        |
        | HTTP methods to exclude from the OpenAPI export entirely.
        |
        | Example: ["HEAD", "OPTIONS"]
        |
        */

        'exclude_http_methods' => [],

        /*
        |----------------------------------------------------------------------
        | Default Response Definitions
        |----------------------------------------------------------------------
        |
        | Predefined response descriptions for common HTTP status codes
        | included in the OpenAPI specification.
        |
        */

        'responses' => [
            '200' => ['description' => 'OK'],
            '401' => ['description' => 'Unauthorized'],
            '403' => ['description' => 'Forbidden'],
            '404' => ['description' => 'Not Found'],
            '422' => ['description' => 'Validation Error'],
            '500' => ['description' => 'Server Error'],
        ],

        /*
        |----------------------------------------------------------------------
        | Security Scheme
        |----------------------------------------------------------------------
        |
        | The default security scheme for the OpenAPI specification.
        |
        | Options for type: "bearer", "apiKey", "basic", "oauth2"
        | Options for position: "header", "query", "cookie"
        |
        */

        'security' => [
            'type' => 'bearer',
            'name' => 'api_key',
            'position' => 'header',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Path
    |--------------------------------------------------------------------------
    |
    | The file path (relative to the project root) where the OpenAPI
    | JSON specification will be written when using the export command.
    |
    */

    'export_path' => 'api.json',

];
