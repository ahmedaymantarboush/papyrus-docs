# The Validation Engine

Papyrus automatically parses your Laravel `FormRequest` validation rules and transforms them into a rich, interactive UI — no annotations, no YAML, no manual work.

---

## Zero-Config Magic

Simply type-hint a `FormRequest` in your controller method:

```php
public function store(StoreProductRequest $request)
{
    // Papyrus automatically reads StoreProductRequest::rules()
}
```

Papyrus will:
1. Detect the type-hinted `FormRequest` via PHP Reflection
2. Instantiate it and call the configured rules method(s) (default: `rules()`)
3. Parse every rule through the Validation Engine pipeline
4. Transform the result into a nested, interactive UI with correct input types

### Custom Rules Methods

If your FormRequest uses non-standard method names:

```php
// config/papyrus.php
'rules_methods' => ['rules', 'createRules', 'updateRules'],
```

Papyrus calls each configured method and merges all results.

---

## The Validation Pipeline

Each field's rules go through a 4-stage pipeline:

```
Rules → TypeDiscovery → ConstraintExtractor → OptionsExtractor → Schema
```

### Stage 1: Universal Rule Parser (`ValidationParser`)

Normalizes all rule formats into a flat string array:

| Input Format | Example | Handling |
|---|---|---|
| Pipe-separated string | `'required\|string\|max:255'` | Split on `\|` |
| Array of strings | `['required', 'string', 'max:255']` | Flatten |
| Rule objects | `Rule::in(['a', 'b'])` | Stringify via `__toString()` |
| PHP Enums | `new Enum(StatusEnum::class)` | Extract via Reflection |
| Closures | `function ($attr, $val, $fail) {}` | **Safely ignored** |
| Custom Rule objects | `new MyCustomRule()` | Class name or `$papyrus_name` |

### Stage 2: Smart Type Discovery (`TypeDiscovery`)

Maps rules to HTML5 input types with a priority-based resolver:

| Priority | Rule(s) | Schema Type | UI Component |
|:---:|---|---|---|
| 1 | `file`, `image`, `mimes:*`, `extensions:*` | `file` | File picker with accept filter |
| 2 | `email` | `email` | Email input |
| 3 | `url`, `active_url` | `url` | URL input |
| 4 | `integer`, `numeric`, `decimal` | `number` | Number input with min/max |
| 5 | `boolean` | `boolean` | Toggle switch |
| 6 | `date`, `date_format:*`, `before:*`, `after:*` | `date` | Date picker |
| 7 | `password`, `Password::class` | `password` | Password input |
| 8 | `hex_color` | `color` | Native color picker |
| 9 | `json` | `json` | JSON textarea |
| 10 | `array` | `array` / `object` | Recursive builder |
| — | *(none of the above)* | `text` | Standard text input |

### Dynamic Type Discovery (Critical Feature)

Rules that represent specific data formats but have no HTML5 equivalent are **dynamically registered** as custom types:

| Rule | Schema Type | UI Behavior |
|---|---|---|
| `uuid` | `uuid` | Text input with `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` placeholder |
| `ulid` | `ulid` | Text input with `01ARZ3NDEKTSV4RRFFQ69G5FAV` placeholder |
| `ip` | `ip` | Text input with `192.168.1.1` placeholder |
| `ipv4` | `ipv4` | Text input with `192.168.1.1` placeholder |
| `ipv6` | `ipv6` | Text input with `::1` placeholder |
| `mac_address` | `mac_address` | Text input with `00:1B:44:11:3A:B7` placeholder |

These types appear in the TypeSelector dropdown labeled as `uuid (dynamic)`, giving developers full visibility into the expected format.

> **Key insight:** Papyrus never silently falls back to `text`. If a rule represents a known data format, the UI reflects it.

### Stage 3: Constraint Extraction (`ConstraintExtractor`)

Extracts structural metadata from every rule:

| Rule | Schema Property | Usage |
|---|---|---|
| `required` | `schema.required = true` | Red asterisk indicator |
| `nullable` | `schema.nullable = true` | "Nullable" badge |
| `min:X` | `schema.min = X` | Input `min` attribute + badge |
| `max:Y` | `schema.max = Y` | Input `max`/`maxlength` + badge |
| `size:Z` | `schema.min = Z, schema.max = Z` | Fixed-length badge |
| `between:X,Y` | `schema.min = X, schema.max = Y` | Range badge |
| `regex:/pattern/` | `schema.pattern = /pattern/` | Pattern badge |
| `confirmed` | `schema.confirmed = true` | Auto-generates `_confirmation` field |
| `extensions:jpg,png` | `schema.accept = '.jpg,.png'` | File input `accept` attribute |
| `mimes:jpg,png` | `schema.accept = '.jpg,.png'` | File input `accept` attribute |
| `dimensions:min_width=100` | `schema.dimensions = {...}` | Dimension constraints badge |

#### Conditional Rules

Conditional rules **never** set `required = true` on the field. Instead, they are added to a `schema.conditionals[]` array:

```php
'backup_email' => 'required_if:email_verified,false|email',
```

Produces:

```json
{
  "key": "backup_email",
  "type": "email",
  "required": false,
  "conditionals": [
    { "rule": "required_if", "field": "email_verified", "value": "false" }
  ]
}
```

Supported conditionals: `required_if`, `required_unless`, `required_with`, `required_with_all`, `required_without`, `required_without_all`, `prohibited`, `prohibited_if`, `prohibited_unless`, `exclude_if`, `exclude_unless`, `exclude_with`, `exclude_without`.

#### Raw Rules Array

The original stringified rules are always passed through to `schema.rules[]` so the frontend can display them as documentation badges:

```json
{
  "rules": ["required", "email", "max:255", "unique"]
}
```

### Stage 4: Options Extraction (`OptionsExtractor`)

Detects restricted value sets and extracts them into `schema.options[]`:

#### String `in:` rules

```php
'status' => 'required|in:active,inactive,pending',
```

→ `options: ['active', 'inactive', 'pending']` → Rendered as a Select dropdown.

#### `Rule::in()` objects

```php
'role' => ['required', Rule::in(['admin', 'editor', 'viewer'])],
```

→ `options: ['admin', 'editor', 'viewer']` → Select dropdown.

#### PHP 8.1+ Enums

```php
'status' => ['required', new Enum(OrderStatus::class)],
```

Papyrus uses `ReflectionEnum` to extract all case values:

```php
enum OrderStatus: string {
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
}
```

→ `options: ['pending', 'shipped', 'delivered']` → Select dropdown with all enum cases.

#### Security Exception

`exists:table,column` and `unique:table,column` rules **never** trigger database queries. They are treated as standard text/number fields.

---

## Nested Schemas

Papyrus fully supports Laravel's dot-notation nested validation:

### Simple Nested Objects

```php
'address.street' => 'required|string|max:255',
'address.city'   => 'required|string',
'address.zip'    => 'required|digits:5',
```

Produces a nested `address` object with three child fields, rendered as an expandable Object Builder in the UI.

### Array of Scalars

```php
'tags'   => 'required|array|min:1',
'tags.*' => 'string|max:50',
```

Produces an Array Builder where users can add/remove string items.

### Array of Objects

```php
'items'         => 'required|array',
'items.*.name'  => 'required|string',
'items.*.price' => 'required|numeric|min:0',
'items.*.qty'   => 'required|integer|min:1',
```

Produces a repeatable Object Builder. Each item has `name`, `price`, and `qty` fields — all with correct types and validation badges.

### Deep Nesting

```php
'order.billing.address.line1' => 'required|string',
'order.billing.address.line2' => 'nullable|string',
'order.billing.address.country.code' => 'required|string|size:2',
```

Papyrus handles unlimited nesting depth. The recursive `DynamicField` → `ObjectBuilder` → `DynamicField` loop renders every level.

---

## Custom Validation Rules

For custom Rule classes, Papyrus extracts a readable name in two ways:

### 1. Explicit `$papyrus_name` Property (Recommended)

```php
class PhoneNumber implements ValidationRule
{
    public string $papyrus_name = 'phone_number';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // ...
    }
}
```

The field's type will be set to `phone_number`, and it will appear in the TypeSelector dropdown.

### 2. Automatic Fallback

If `$papyrus_name` is not defined, Papyrus uses `Str::snake(class_basename($rule))`:

```php
class ValidCreditCard implements ValidationRule { ... }
// → type: "valid_credit_card"
```

> Framework rules (`Illuminate\*`) are excluded from custom name extraction to avoid conflicts.
