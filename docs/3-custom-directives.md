# Custom Directives

Papyrus reads DocBlock comments on your controller methods to extract metadata. No special setup is required — just write standard PHP DocBlocks.

---

## Standard DocBlock Tags

### Summary (Title)

The **first line** of the DocBlock becomes the endpoint's title:

```php
/**
 * Register a new user.
 */
public function store(StoreUserRequest $request) { ... }
```

→ Title: **Register a new user.**

### Description

The **body text** (after the summary, before any tags) becomes the endpoint's description:

```php
/**
 * Register a new user.
 *
 * Creates a new user account and sends a welcome email.
 * The user will be automatically assigned the "viewer" role
 * unless otherwise specified.
 */
```

→ Description: *"Creates a new user account and sends a welcome email..."*

### `@group`

Overrides the default grouping (which uses the controller's class basename):

```php
/**
 * Register a new user.
 *
 * @group Authentication
 */
```

→ This endpoint appears under the "Authentication" group in the sidebar, regardless of which controller it belongs to.

---

## Multi-line Block Directives

Standard single-line DocBlocks are sometimes too limited for long markdown text or raw JSON files. Papyrus provides powerful multi-line block parsers.

### `@papyrus-description-start`

Use this block to override the default DocBlock description with a much longer, multi-line markdown description. It preserves all newlines and internal formatting.

```php
/**
 * @papyrus-description-start
 * # Welcome to the Create API
 * This endpoint allows you to create records dynamically.
 *
 * **Features:**
 * - Asynchronous processing
 * - Soft-delete support
 * @papyrus-description-end
 */
```

### `@papyrus-responseExample-start`

This block attaches a raw JSON payload directly to the generated schema for a specific HTTP status code.

**Syntax:** `@papyrus-responseExample-start {status_code}`

```php
/**
 * @papyrus-responseExample-start 200
 * {
 *   "success": true,
 *   "data": {
 *     "id": 1,
 *     "name": "Jane Doe"
 *   }
 * }
 * @papyrus-responseExample-end
 */
```
This JSON chunk will be automatically loaded and presented in the UI when selecting the `200` response badge.

---

## `@papyrus-bodyParam` — Manual Body Override

This is the most powerful directive. It gives developers **total control** over the payload schema when FormRequest-based auto-detection isn't suitable.

### Syntax

```
@papyrus-bodyParam {type} {key} {description}
```

| Part | Description | Examples |
|---|---|---|
| `{type}` | Data type | `string`, `integer`, `file`, `boolean`, `email`, `object`, `array`, `date`, `url`, `password`, `color`, `json` |
| `{key}` | Property name (supports dot-notation) | `email`, `user.address.street`, `tags` |
| `{description}` | Free-text description | `The user's login email (required)` |

### Basic Example

```php
/**
 * Create a user profile.
 *
 * @papyrus-bodyParam string name The user's display name (required)
 * @papyrus-bodyParam email email The login email address (required)
 * @papyrus-bodyParam password password Account password (required)
 * @papyrus-bodyParam file avatar Profile picture
 * @papyrus-bodyParam integer age User's age
 */
public function store(Request $request) { ... }
```

### The Absolute Override Rule ⚠️

> **CRITICAL:** If Papyrus detects **even one** `@papyrus-bodyParam` directive on a controller method, it **completely bypasses** the FormRequest validation parsing for that endpoint. The directives become the **sole source of truth** for the payload schema.

This means:

```php
/**
 * @papyrus-bodyParam string name The name
 * @papyrus-bodyParam string email The email
 */
public function store(StoreUserRequest $request)
{
    // StoreUserRequest::rules() is IGNORED by Papyrus.
    // Only 'name' and 'email' are shown in the UI.
}
```

If you want Papyrus to use the FormRequest, simply **don't add any `@papyrus-bodyParam` directives**.

### Dot-Notation Nesting

Dot-notation keys are automatically expanded into nested objects:

```php
/**
 * @papyrus-bodyParam string user.name The user's name
 * @papyrus-bodyParam email user.email The user's email
 * @papyrus-bodyParam string user.address.street Street address
 * @papyrus-bodyParam string user.address.city City name
 * @papyrus-bodyParam string user.address.zip ZIP code
 */
```

This produces the following nested schema:

```
user (object)
├── name (text)
├── email (email)
└── address (object)
    ├── street (text)
    ├── city (text)
    └── zip (text)
```

The UI renders this as nested Object Builders — identical to how FormRequest dot-notation rules are rendered.

### Type Aliases

The following type aliases are supported:

| Directive Type | Schema Type | UI Component |
|---|---|---|
| `string`, `str`, `text` | `text` | Text input |
| `int`, `integer`, `float`, `double`, `numeric`, `number` | `number` | Number input |
| `bool`, `boolean` | `boolean` | Toggle switch |
| `file`, `image` | `file` | File picker |
| `date`, `datetime` | `date` | Date picker |
| `email` | `email` | Email input |
| `url` | `url` | URL input |
| `password` | `password` | Password input |
| `color` | `color` | Color picker |
| `json` | `json` | JSON textarea |
| `array` | `array` | Array builder |
| `object` | `object` | Object builder |
| `select` | `select` | Select dropdown |
| *(any other value)* | *(as-is)* | Text input with dynamic type label |

### Smart Flags from Description

Papyrus parses the description text for keywords:

- Including **"required"** in the description → `schema.required = true` → Red asterisk in UI
- Including **"nullable"** in the description → `schema.nullable = true` → "Nullable" badge

```php
/**
 * @papyrus-bodyParam string name The name (required)
 * @papyrus-bodyParam string bio A short bio (nullable)
 */
```

### When to Use Manual Directives

| Scenario | Use Directives? |
|---|---|
| Standard FormRequest with `rules()` | ❌ Auto-detection handles it |
| Controller uses `$request->validate()` inline | ✅ Directives needed |
| Endpoint accepts `Request` (not FormRequest) | ✅ Directives needed |
| You want to show different fields than what FormRequest defines | ✅ Override |
| Third-party FormRequest you can't modify | ✅ Override |

---

## `@papyrus-responseParam` — Manual Response Schema

Just like the request body, developers can document the exact JSON structure returned by the API using `@papyrus-responseParam`.

**Syntax:** `@papyrus-responseParam {status_code} {type} {key} {description}`

```php
/**
 * @papyrus-responseParam 200 string data.user.name The user's name
 * @papyrus-responseParam 200 email data.user.email The login email address
 * @papyrus-responseParam 200 array data.user.tags Array of assigned tags
 * @papyrus-responseParam 422 string error.message The validation error
 */
public function store(Request $request) { ... }
```
Papyrus will automatically group these keys by their status code (`200`, `422`) and build deeply nested JSON schemas out of the dot-notation (`data.user.name` -> `data` -> `user` -> `name`).

---

## Query Parameters & Headers

You can also explicitly define custom Query Parameters and Headers on the API endpoint itself. These rules override the API defaults.

### `@papyrus-queryParam`

**Syntax:** `@papyrus-queryParam {type} {key} {description}`

```php
/**
 * Get active users.
 * 
 * @papyrus-queryParam integer page The page number for pagination
 * @papyrus-queryParam string search A keyword to search by
 */
```

### `@papyrus-header`

**Syntax:** `@papyrus-header {key} {description}`

```php
/**
 * @papyrus-header X-Transaction-Id The unique transaction ID for tracking
 * @papyrus-header X-Internal-Source If the request comes from an internal microservice
 */
```

---

## Combining Directives

All directives can be used together:

```php
/**
 * Upload a document with metadata.
 *
 * Accepts a document file along with classification metadata.
 * The document is processed asynchronously.
 *
 * @group Documents
 * @papyrus-bodyParam file document The document file (required)
 * @papyrus-bodyParam string title Document title (required)
 * @papyrus-bodyParam string description Optional description (nullable)
 * @papyrus-bodyParam string metadata.category The document category
 * @papyrus-bodyParam string metadata.tags Comma-separated tags
 */
public function upload(Request $request) { ... }
```

This endpoint will:
- Appear under the **"Documents"** group
- Show the title "Upload a document with metadata."
- Display the description paragraph
- Show 5 body parameters with correct types, nesting, and required indicators
- **Not** parse any FormRequest (because `@papyrus-bodyParam` is present)
