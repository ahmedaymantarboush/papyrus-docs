# The Smart UI Guide

Papyrus ships with a premium React-based UI featuring a recursive form engine, two-way JSON sync, and Postman-like field management. This guide explains every interactive feature.

---

## Dark Mode & Theme Toggle

Papyrus includes a massive global **Dark/Light Theme**:
- By default, it adheres to your OS preference (`window.matchMedia('(prefers-color-scheme: dark)')`).
- Click the **Sun / Moon** icon in the Top Navbar to manually toggle the theme.
- Your preference is instantly saved to `localStorage` (`papyrus_theme`) and applied on all subsequent visits.
- All code editors (Headers, Raw JSON, Snippets) dynamically adjust their themes to match.

---

## Layout Overview

The UI is divided into three responsive panels:

| Panel | Position | Purpose |
|---|---|---|
| **Sidebar** | Left | Search, browse, and filter endpoints |
| **Documentation** | Center | View endpoint details, edit the request payload |
| **Playground** | Right | Execute requests, view responses, generate code snippets |

### Sidebar Features

- **Search** — Filter endpoints by URI, title, method, or body field names
- **Endpoint count** — Shows the total number of matching endpoints
- **Grouping** — Switch between grouping by Controller, Route Name, URI Patterns, or flat view via Settings
- **Resizable width** — Drag the right edge to resize
- **Mobile support** — Slides in/out with a smooth animation on mobile viewports

### Settings Modal

Access via the ⚙️ icon in the sidebar header:

- **Sort by** — Default, Route Name, or HTTP Method
- **Group by** — Default, API Name, Controller Name, or URI Patterns
- **Method filter** — Toggle which HTTP methods (GET, POST, PUT, PATCH, DELETE) are shown
- **Name regex** — Filter endpoints by route name using regex
- **Controller regex** — Filter endpoints by controller name using regex
- **Global headers** — When enabled, header changes apply across all endpoints
- **Save responses** — Persist API responses in localStorage

---

## The Visual Form Builder

The center panel shows the currently selected endpoint's documentation and an interactive form for building the request payload.

### DynamicField — The Recursive Engine

Every field in the form is rendered by the `DynamicField` component, which provides:

1. **Toggle Switch** — Enable/disable the field (see Postman Checkbox below)
2. **Inline Key Editor** — Click-to-edit field names
3. **Type Selector** — Dropdown to change the field's data type
4. **Required Indicator** — Red asterisk for required fields
5. **Remove Button** — Red pill button to delete the field
6. **Smart Input** — Type-appropriate input widget
7. **Validation Badges** — Expandable rule display

For `object` and `array` types, `DynamicField` recursively nests `ObjectBuilder` and `ArrayBuilder` components, enabling **infinite depth**.

---

## Two-Way Sync: Visual Form ↔ Raw JSON

The Body section includes a tab switcher: **[Visual Form | Raw JSON]**.

### How It Works

```
Visual Form → compileTreeToPayload() → JSON (read-only snapshot)
JSON edits  → Apply button → hydrateFormTreeFromJson() → Visual Form
```

1. **Switching to Raw JSON** — Takes a snapshot of the current form state, pretty-printed as JSON
2. **Editing the JSON** — Uses the `json-edit-react` editor with a dark theme
3. **Switching back to Visual Form** — If you edited the JSON (dirty flag), changes are applied back to the form tree
4. **No edits?** — If you just viewed the JSON without editing, the form tree is preserved as-is

### Apply Changes Button

While in Raw JSON mode, click **"Apply Changes"** to immediately push your JSON edits back to the Visual Form — without switching tabs.

### The File Guard 🔒

If your form contains **any file upload fields**, the Raw JSON tab is **automatically disabled** with a lock icon and tooltip:

> *"JSON editing is disabled when the payload contains file uploads"*

This prevents data loss, since File objects cannot be represented in JSON.

### Error Handling

If the JSON becomes invalid during editing:
- An inline error banner appears
- The "Apply Changes" button is disabled
- Switching back to Visual Form preserves the original form tree

---

## The Postman Checkbox (Field Toggle)

Every field has a **toggle switch** on the left side.

### Behavior

| Toggle State | Visual | Payload | State |
|---|---|---|---|
| **ON** (default) | Full opacity | ✅ Included | Preserved |
| **OFF** | Dimmed (40% opacity) | ❌ Excluded | **Preserved** |

### Key Insight

Toggling a field OFF does **not** delete it. The field's key, value, type, and children remain in React state. It is simply excluded from:

- The compiled request payload (`compileTreeToPayload()`)
- The Raw JSON view
- Code snippets

This lets you temporarily remove a field from a request without losing your entered data.

---

## Inline Key Editor

Every field key is rendered as an `InlineKeyEditor` with two states:

### VIEW State (Default)

- Key displayed as text with a pencil (✏️) icon
- Click the pencil or the text to enter EDIT mode

### EDIT State

- Key displayed as a text input with a checkmark (✓) icon
- **Enter** → Save and return to VIEW
- **Escape** → Revert to previous value and return to VIEW
- **Blur** (click away) → Save and return to VIEW

---

## Adding & Removing Fields

### Add Property

- **Top-level:** An **[+ ADD PROPERTY]** button appears below all fields
- **Inside objects:** An **[+ Add Property]** button appears inside each Object Builder

Clicking adds a new field with a default key (`new_prop_0`, `prop_0`, etc.), type `text`, and an empty value.

### Remove Field

Every field has a **red pill button** with a trash icon. Clicking it permanently removes the field from the form tree.

> **Note:** Schema-defined fields can be removed from the current session but will reappear when the page is refreshed (they're rebuilt from the backend schema).

---

## Type Selector

Every field has a dropdown to change its data type on the fly.

### Available Types

| Type | Input Widget |
|---|---|
| `text` | Standard text input |
| `string` | Standard text input |
| `number` | Number input |
| `email` | Email input |
| `url` | URL input |
| `date` | Date picker |
| `password` | Password input (masked) |
| `color` | Native color picker |
| `boolean` | Toggle switch |
| `file` | File picker |
| `json` | Textarea for JSON |
| `object` | Nested object builder |
| `array` | Repeatable array builder |
| `select` | Select dropdown |
| *Dynamic types* | Text input with format placeholder (e.g., `uuid (dynamic)`) |

### Smart Morphing

Changing types triggers intelligent data conversion:

| From | To | Behavior |
|---|---|---|
| Text → Object | Attempts to `JSON.parse()` the value into children |
| Object → Text | Serializes children to `JSON.stringify()` |
| Text → Array | Attempts to parse as JSON array |
| Any → Select | Opens the Options Modal to add selectable values |

---

## Select Fields & Options Modal

When a field's type is set to `select` (either from the backend schema or manually via the Type Selector), the UI provides:

### From the Backend

If the backend reports `options: ['active', 'inactive', 'pending']` (from `in:` rules or Enums), the field renders as a native `<select>` dropdown with those options.

### Adding Custom Options

When you change a field's type to `select` via the Type Selector, the **Select Options Modal** automatically opens. This modal lets you:

1. Add new options (text input + Add button)
2. Remove existing options (click ✕ next to each)
3. View all current options
4. Save and close

You can reopen the modal anytime by clicking the ⚙️ gear icon next to select fields.

---

## Validation Badges

Every field displays its validation rules as **non-truncated** badges:

### Display Rules

- Rules are shown as small, colored badges below the field
- The badges **wrap** to multiple lines — they are never truncated with `text-overflow: ellipsis`
- If there are 4+ rules, an expandable **"ⓘ +N more"** button appears
- Click to expand/collapse the full list

### Badge Colors

| Badge | Color |
|---|---|
| `required` | Rose/red |
| `nullable` | Blue |
| Conditional rules | Amber |
| All others | Slate/gray |

### Conditional Rules

Conditional rules from `schema.conditionals[]` are displayed as amber badges with structured text:

- `required_if:role,admin` → Badge: **required_if role = admin**
- `prohibited_unless:verified,true` → Badge: **prohibited_unless verified = true**

---

## Reset to Default

The **Reset** button (↻) in the Request Payload header resets the form to the original backend schema state:

- All values cleared to empty
- All toggles re-enabled
- All custom-added properties removed
- Array items removed
- Type changes reverted

This performs a deep clone of the original schema — your data is completely rebuilt from scratch.

---

## The Playground (Right Panel)

### Headers Tab

The Headers editor uses the **same DynamicField engine** as the Body form:

- Full recursive nesting support
- Per-header toggle switches
- Inline key editing
- Type selector
- Add / Remove buttons
- Form Builder ↔ Raw JSON toggle

Headers are pre-populated from `config('papyrus.default_headers')`.

### Snippets Tab

Auto-generates code snippets in 4 languages with full multipart support:

| Language | Library | File Support |
|---|---|---|
| **cURL** | Native CLI | `-F` flags for files, `-d` for JSON |
| **PHP** | GuzzleHTTP | `multipart` array for files, `json` for data |
| **JavaScript** | `fetch()` API | `FormData` for files, `JSON.stringify` for data |
| **Python** | `requests` | `files=` param for uploads, `json=` for data |

All snippets:
- Only include **enabled** fields (disabled toggles are excluded)
- Auto-detect file uploads and switch to multipart format
- Replace path parameters with entered values
- Use `{{base_url}}` placeholder for the server URL

### Response Tab

- Shows the HTTP status code with color coding
- Response time in milliseconds
- Response body as formatted JSON
- Collapsible response headers section
- Default response code badges from config

### Resizable Width

Drag the left edge of the Playground panel to resize it. Width is persisted in localStorage.

---

## The Request Execution Engine

When you click **Send** in the Playground, the request goes through a sophisticated preparation pipeline:

### Payload Compilation (`compileTreeToPayload`)

1. Recursively walks the formTree
2. **Skips** any node where `enabled === false` (Postman checkbox)
3. Compiles objects into nested key-value structures
4. Compiles arrays into indexed lists
5. File values are preserved as `File` objects

### Request Preparation (`prepareRequest`)

1. **Path parameter substitution** — `{id}` and `{id?}` are replaced with entered values; optional params without values are removed from the URL
2. **File detection** — Recursively walks the payload looking for `File` instances
3. **Three modes:**

| Condition | Behavior |
|---|---|
| GET/HEAD request | Serializes payload as URL query string (`?key[0]=val`) |
| POST/PUT with files | Builds `FormData` with recursive key flattening |
| POST/PUT without files | Sends as `application/json` with scalar casting |

### FormData Auto-Handling (Critical)

When files are detected:

```
╔═══════════════════════════════════════════════════╗
║  Content-Type header is STRIPPED entirely.        ║
║  The browser MUST set it with the multipart       ║
║  boundary. Hardcoding it will BREAK the request.  ║
╚═══════════════════════════════════════════════════╝
```

Both `Content-Type` and `content-type` are deleted from the headers map.

### Scalar Casting

For JSON payloads, string values are automatically cast:
- `"true"` → `true` (boolean)
- `"false"` → `false` (boolean)
- `"42"` → `42` (number)
- `"3.14"` → `3.14` (number)
- Empty strings and `null` are preserved

---

## The Array Builder

For fields with `type: 'array'`, Papyrus renders the `ArrayBuilder` component:

### Features

- **Indexed items** — Each item shows its array index (`[0]`, `[1]`, `[2]`)
- **Add Item** — Blue `[+ Add Item]` button appends a new element
- **Remove Item** — Each item has a trash button for removal
- **Schema-aware** — New items inherit the `childType` from the schema:
  - `childType: 'string'` → adds a text input
  - `childType: 'object'` → adds a nested Object Builder with schema-defined fields
- **Empty state** — Shows "Empty array..." when no items exist

### Example

Given this backend rule:
```php
'tags'   => 'required|array|min:1',
'tags.*' => 'string|max:50',
```

The UI renders:
```
tags (array)
├── [0] "javascript"    [🗑]
├── [1] "laravel"       [🗑]
├── [2] "react"         [🗑]
└── [+ Add Item]
```

---

## State Persistence

Papyrus persists the following to `localStorage`:

| Data | Storage Key |
|---|---|
| Form values per endpoint | `papyrus_state_{routeId}` |
| Path parameter values | *(included in state)* |
| Custom headers | *(per-route or global)* |
| Sidebar width | `papyrus_sidebar_w` |
| Playground width | `papyrus_playground_w` |
| User settings | `papyrus_settings` |
| Field type overrides | `papyrus_field_types` |

When you revisit an endpoint, your previously entered values and settings are restored.

### Global vs. Per-Route Headers

In Settings, the **"Global Headers"** toggle controls whether header changes apply to all endpoints or are saved individually per route.

---

## Hash Routing

Papyrus uses URL hash routing (`#routeId`) for navigation:

- Selecting an endpoint updates the URL hash
- Direct links work: share `http://your-app.test/papyrus-docs#GET-api-users` to link to a specific endpoint
- Browser back/forward navigation is supported

---

## Keyboard Shortcuts

| Context | Key | Action |
|---|---|---|
| Inline Key Editor | `Enter` | Save key and exit edit mode |
| Inline Key Editor | `Escape` | Revert and exit edit mode |
| Settings Modal | `Escape` | Close modal |
| Select Options Modal | `Escape` | Close modal |
