<p align="center">
  <strong>¶ PAPYRUS DOCS</strong>
</p>

<p align="center">
  <em>A Postman-like API documentation & testing client — natively inside Laravel.</em>
</p>

<p align="center">
  <img src="https://img.shields.io/packagist/v/ahmedtarboush/papyrus-docs?style=flat-square&label=packagist&color=f59e0b" alt="Packagist Version">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/React-18-61DAFB?style=flat-square&logo=react&logoColor=black" alt="React">
  <img src="https://img.shields.io/packagist/l/ahmedtarboush/papyrus-docs?style=flat-square" alt="License">
</p>

---

## What is Papyrus?

Papyrus Docs is an **enterprise-grade API documentation and playground** that lives inside your Laravel application. It automatically reads your `FormRequest` validation rules — including nested arrays, Enum options, file uploads, and conditional rules — and renders them as an interactive, Postman-like testing UI with zero configuration.

**No OpenAPI YAML. No manual annotations. Just write your FormRequests, and Papyrus does the rest.**

### Why Papyrus?

| Feature | Postman | Swagger UI | **Papyrus** |
|---|:---:|:---:|:---:|
| Zero-config from FormRequest | ❌ | ❌ | ✅ |
| Recursive nested objects/arrays | ❌ | Partial | ✅ |
| Two-Way Visual ↔ JSON sync | ❌ | ❌ | ✅ |
| Dynamic Enum / Select detection | ❌ | Manual | ✅ |
| Inline key editing & type morphing | ❌ | ❌ | ✅ |
| File upload + FormData auto-handling | ✅ | ❌ | ✅ |
| Lives inside your Laravel app | ❌ | ❌ | ✅ |

---

## Features at a Glance

- **🧠 Exhaustive Validation Engine** — Parses every Laravel 12 rule: `required`, `nullable`, `min`, `max`, `regex`, `between`, `confirmed`, `dimensions`, conditional rules (`required_if`, `prohibited_unless`), and more.
- **🔍 Dynamic Type Discovery** — Rules like `uuid`, `ulid`, `ip`, `mac_address` are dynamically registered as custom types with format-specific UI hints.
- **📋 Enum & Options Extraction** — Automatically extracts `in:a,b,c`, `Rule::in()`, and PHP 8.1+ Enum cases into selectable dropdowns.
- **🔄 Two-Way Sync** — Edit payload via Visual Form or Raw JSON — changes sync bidirectionally with a file-guard safety mechanism.
- **☑️ Postman Checkbox** — Toggle individual fields on/off. Disabled fields are excluded from the request but preserved in state.
- **✏️ Inline Key Editor** — Click any key to rename it. Add/remove custom properties dynamically.
- **🔁 Recursive Form Engine** — Infinite nesting via `DynamicField` → `ObjectBuilder` → `ArrayBuilder` → `DynamicField`.
- **📝 Non-Truncated Validation Badges** — All rules displayed as expandable badges, never truncated.
- **🔧 DocBlock Overrides** — Absolute manual control with powerful custom directives like `@papyrus-bodyParam`, `@papyrus-queryParam`, `@papyrus-header`, and multi-line markdown blocks for `@papyrus-description-start` and `@papyrus-responseExample-start`.
- **📡 Code Snippets** — Auto-generated cURL, PHP (Guzzle), JavaScript (fetch), and Python (requests) snippets for every endpoint.
- **📦 FormData Auto-Handling** — File uploads automatically switch to `multipart/form-data` with correct boundary headers.
- **⚙️ Configurable Grouping** — Group endpoints by controller, route name, or URI regex patterns.
- **🎨 Global Dark & Light Mode** — Fully responsive dark theme that defaults to your OS preference.
- **🎯 OpenAPI Export** — Export your documentation as OpenAPI 3.0 JSON specification.
- **📮 Postman Export** — Export as Postman Collection v2.1 for team sharing.
- **🛡️ Authorization Gate** — Built-in `viewPapyrusDocs` gate for access control.
- **🐛 Debug Mode** — Debug headers with execution time, memory usage, PHP and Laravel versions.
- **🔧 Artisan Commands** — `papyrus:install` for one-step setup.

---

## Installation

### 1. Require the package

```bash
composer require ahmedtarboush/papyrus-docs
```

### 2. Run the install command (recommended)

```bash
php artisan papyrus:install
```

This publishes both the config file and production assets in one step.

### 3. Or publish manually

```bash
# Config only
php artisan vendor:publish --tag=papyrus-config

# Assets only (for production)
php artisan vendor:publish --tag=papyrus-assets
```

### 4. Access the dashboard

Navigate to:

```
http://your-app.test/papyrus-docs
```

> **That's it.** No annotations required. Papyrus automatically scans your routes and FormRequests.

---

## Quick Start

### Step 1: Create a FormRequest

```php
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'age'      => 'nullable|integer|min:18|max:120',
            'role'     => ['required', Rule::in(['admin', 'editor', 'viewer'])],
            'avatar'   => 'nullable|image|max:5120|extensions:jpg,png,webp',
            'tags'     => 'array',
            'tags.*'   => 'string|max:50',
        ];
    }
}
```

### Step 2: Type-hint it in your controller

```php
/**
 * Register a new user.
 *
 * Creates a user account with the provided details,
 * profile picture, and role assignment.
 *
 * @group Users
 */
public function store(StoreUserRequest $request)
{
    // ...
}
```

### Step 3: Open Papyrus

Visit `/papyrus-docs` in your browser. The endpoint above will appear with:

- All fields rendered with correct input types (email field, number with min/max, file upload with `.jpg,.png,.webp` accept filter)
- `role` rendered as a Select dropdown with `admin`, `editor`, `viewer`
- `tags` rendered as a repeatable array builder
- Validation badges showing `required`, `string`, `max:255` on each field
- A fully functional playground to test the endpoint with real requests

---

## Documentation

| Doc | Description |
|---|---|
| [Configuration](docs/1-configuration.md) | Every config key explained |
| [Validation Engine](docs/2-validation-engine.md) | How FormRequest rules become UI components |
| [Custom Directives](docs/3-custom-directives.md) | DocBlock overrides & `@papyrus-bodyParam` |
| [Smart UI Guide](docs/4-smart-ui.md) | Two-Way Sync, Postman Checkbox, Inline Editor |

---

## Artisan Commands

### `papyrus:install`

One-step installation that publishes both config and assets:

```bash
php artisan papyrus:install
```

Equivalent to running:
```bash
php artisan vendor:publish --tag=papyrus-config
php artisan vendor:publish --tag=papyrus-assets
```

### `papyrus:export`

Export your API schema directly to a JSON file (uses `config('papyrus.export_path')`):

```bash
# Export OpenAPI 3.0 (default)
php artisan papyrus:export

# Export Postman Collection v2.1
php artisan papyrus:export --type=postman
```

---

## Exporters

### OpenAPI 3.0 Export

Papyrus includes an `OpenApiGenerator` that converts your scanned routes into an OpenAPI 3.0 specification:

```php
use AhmedTarboush\PapyrusDocs\Exporters\OpenApiGenerator;
use AhmedTarboush\PapyrusDocs\PapyrusGenerator;

$schema = app(PapyrusGenerator::class)->scan();
$openApi = (new OpenApiGenerator())->generate($schema);

// Returns a complete OpenAPI 3.0 spec array with:
// - info (title, version)
// - paths (with tags, summary, description, requestBody, responses)
```

Configure the export via `config/papyrus.php` under the `open_api` key.

### Postman Collection Export

The `PostmanGenerator` exports your API as a Postman Collection v2.1:

```php
use AhmedTarboush\PapyrusDocs\Exporters\PostmanGenerator;
use AhmedTarboush\PapyrusDocs\PapyrusGenerator;

$schema = app(PapyrusGenerator::class)->scan();
$postman = (new PostmanGenerator())->generate($schema);

// Returns a Postman v2.1 collection with:
// - info (name, schema URL)
// - item[] (grouped by API group, with name, request, body)
```

Import the resulting JSON directly into Postman to share with your team.

---

## Debug Mode

Enable debug mode to get execution metadata in the schema API response headers:

```env
PAPYRUS_DEBUG=true
```

The `/papyrus-docs/api/schema` endpoint will include:

| Header | Description |
|---|---|
| `X-Papyrus-Debug-Time-Ms` | Schema generation time in milliseconds |
| `X-Papyrus-Debug-Memory-Mb` | Memory consumed during generation |
| `X-Papyrus-Debug-PHP` | PHP version |
| `X-Papyrus-Debug-Laravel` | Laravel version |

---

## Authorization

By default, Papyrus is only accessible in `local` and `testing` environments via the `viewPapyrusDocs` gate.

The default gate definition:

```php
Gate::define('viewPapyrusDocs', function ($user = null) {
    return app()->environment('local', 'testing');
});
```

To customize access, override the gate in your `AuthServiceProvider`:

```php
Gate::define('viewPapyrusDocs', function ($user = null) {
    return $user && $user->isAdmin();
});
```

---

## Internal Routes

Papyrus registers these routes under the configured URL prefix:

| Route | Purpose |
|---|---|
| `GET /papyrus-docs` | The SPA UI (gated by `viewPapyrusDocs`) |
| `GET /papyrus-docs/api/schema` | JSON schema endpoint (used by the UI) |
| `GET /papyrus-docs/assets/{path}` | Static asset serving (JS, CSS, fonts) |
| `GET /papyrus-docs/favicon/{file?}` | Favicon serving |

All routes respect the `middlewares` config and are prefixed with the `url` config value.

---

## Disabling in Production

Set the environment variable:

```env
PAPYRUS_ENABLED=false
```

This prevents all Papyrus routes from being registered. The ServiceProvider short-circuits entirely.

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- `phpdocumentor/reflection-docblock` (auto-installed)

---

## Created By

**Ahmed Tarboush** — [LinkedIn](https://www.linkedin.com/in/ahmed-tarboush/)

---

## License

Papyrus Docs is open-source software licensed under the [MIT License](LICENSE).
