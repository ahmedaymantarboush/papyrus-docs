<?php

namespace AhmedTarboush\PapyrusDocs;

use Illuminate\Support\Facades\Route;
use AhmedTarboush\PapyrusDocs\Validation\ValidationParser;

/**
 * PapyrusGenerator — Scans application routes and produces a structured
 * API documentation schema with enriched validation metadata.
 *
 * This generator:
 *   1. Collects all registered routes
 *   2. Filters them using config-driven rules (hide_matching, only_route_uri_start_with)
 *   3. Extracts DocBlock metadata (summary, description, group)
 *   4. CHECK: If @papyrus-bodyParam directives exist → use manual schema (bypass FormRequest)
 *   5. Otherwise: Extract FormRequest validation rules via configurable method names
 *   6. Delegates rule parsing to the ValidationParser engine
 *   7. Builds a nested schema tree from dot-notation field keys
 *   8. Attempts to extract response structure from JsonResource return types
 */
class PapyrusGenerator
{
    protected ValidationParser $parser;

    public function __construct()
    {
        $this->parser = new ValidationParser();
    }

    /**
     * Scan all application routes and return a grouped, structured schema.
     *
     * @return \Illuminate\Support\Collection
     */
    public function scan()
    {
        $prefix          = config('papyrus.only_route_uri_start_with', '');
        $hidePatterns    = config('papyrus.hide_matching', []);
        $visiblePatterns = config('papyrus.visible_matching', []);
        $docPath         = config('papyrus.url', config('papyrus.path', 'papyrus-docs'));

        $routes = collect(Route::getRoutes())->map(function ($route) {
            return $this->processRoute($route);
        })->filter(function ($route) use ($prefix, $hidePatterns, $visiblePatterns, $docPath) {
            if (! $route) return false;

            $uri = $route->uri;

            // Prefix filter: only include routes starting with the configured prefix
            if ($prefix && ! str_starts_with($uri, $prefix)) return false;

            // Always hide our own documentation routes
            if (str_starts_with($uri, $docPath)) return false;

            // If visible_matching is defined, the route MUST match at least one pattern
            if (! empty($visiblePatterns)) {
                $isVisible = false;
                foreach ($visiblePatterns as $pattern) {
                    try {
                        if (preg_match($pattern, $uri)) {
                            $isVisible = true;
                            break;
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
                if (! $isVisible) return false;
            }

            // Dynamic regex exclusions from config
            foreach ($hidePatterns as $pattern) {
                try {
                    if (preg_match($pattern, $uri)) return false;
                } catch (\Throwable $e) {
                    // Invalid regex pattern in config — skip silently
                    continue;
                }
            }

            return true;
        })->values();

        return $this->groupRoutes($routes);
    }

    /**
     * Process a single route using Reflection to extract metadata.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return object|null
     */
    protected function processRoute($route)
    {
        $action = $route->getActionName();

        if ($action === 'Closure') {
            return null;
        }

        if (! str_contains($action, '@')) {
            if (class_exists($action) && method_exists($action, '__invoke')) {
                $controller = $action;
                $method = '__invoke';
            } else {
                return null;
            }
        } else {
            [$controller, $method] = array_pad(explode('@', $action), 2, null);
        }

        try {
            $reflection = new \ReflectionMethod($controller, $method);
        } catch (\ReflectionException $e) {
            return null;
        }

        $docBlock = $this->parseDocBlock($reflection);

        // ── THE BYPASS SWITCH ─────────────────────────────────────────
        // If @papyrus-bodyParam directives exist, they become the SOLE
        // source of truth. FormRequest rules are completely ignored.
        $manualBodyParams = $this->extractDocBlockBodyParams($reflection);

        if (! empty($manualBodyParams)) {
            $bodyParams = $this->buildManualSchema($manualBodyParams);
        } else {
            $rawRules   = $this->extractRawRules($reflection);
            $bodyParams = $this->getNestedSchema($rawRules);
        }

        // ── NEW DIRECTIVES ─────────────────────────────────────────────
        $manualDescription = $this->extractDocBlockDescription($reflection);
        $description = $manualDescription ?: ($docBlock['description'] ?? '');

        $queryParamsRaw = $this->extractDocBlockQueryParams($reflection);
        $queryParams = $this->buildManualSchema($queryParamsRaw);

        $headers = $this->extractDocBlockHeaders($reflection);

        $responseExamples = $this->extractDocBlockResponseExamples($reflection);
        $responseParamsRaw = $this->extractDocBlockResponseParams($reflection);

        $responses = [];
        // Extract legacy response (fallback)
        $legacyResponse = $this->extractResponse($reflection);
        if ($legacyResponse) {
            $responses['200'] = [
                'responseExample' => $legacyResponse,
                'schema' => [],
            ];
        }

        // Merge response schemas
        foreach ($responseParamsRaw as $statusCode => $params) {
            if (!isset($responses[$statusCode])) {
                $responses[$statusCode] = ['responseExample' => null, 'schema' => []];
            }
            $responses[$statusCode]['schema'] = $this->buildManualSchema($params);
        }

        // Merge response examples
        foreach ($responseExamples as $statusCode => $example) {
            if (!isset($responses[$statusCode])) {
                $responses[$statusCode] = ['responseExample' => null, 'schema' => []];
            }
            // Parse JSON if possible to return an object/array, otherwise treat as string
            $decoded = json_decode($example, true);
            $responses[$statusCode]['responseExample'] = json_last_error() === JSON_ERROR_NONE ? $decoded : $example;

            // Optional: Auto-generate schema from example if one wasn't explicitly provided via @papyrus-responseParam
            if (empty($responses[$statusCode]['schema']) && is_array($decoded)) {
                $responses[$statusCode]['schema'] = $this->buildSchemaFromJson($decoded);
            }
        }

        return (object) [
            'uri'                 => $route->uri(),
            'methods'             => $route->methods(),
            'title'               => $docBlock['summary'] ?? $method,
            'description'         => $description,
            'group'               => $docBlock['group'] ?? class_basename($controller),
            'routeName'           => $route->getName(),
            'controllerName'      => class_basename($controller),
            'controllerNamespace' => $controller,
            'bodyParams'          => $bodyParams,
            'queryParams'         => $queryParams,
            'headers'             => $headers,
            'responses'           => $responses,
        ];
    }

    /**
     * Group the mapped routes by their group names.
     *
     * @param  \Illuminate\Support\Collection  $routes
     * @return \Illuminate\Support\Collection
     */
    protected function groupRoutes($routes)
    {
        return $routes->groupBy('group')->map(function ($items, $groupName) {
            return [
                'name'   => $groupName,
                'routes' => $items,
            ];
        })
            ->sortKeys()
            ->values();
    }

    /**
     * Parse the DocBlock of the controller method.
     *
     * Extracts @group, summary, and description.
     *
     * @param  \ReflectionMethod  $reflection
     * @return array
     */
    protected function parseDocBlock(\ReflectionMethod $reflection)
    {
        $factory = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
        $comment = $reflection->getDocComment();

        if (! $comment) {
            return [];
        }

        try {
            $docBlock = $factory->create($comment);

            $groupTag = $docBlock->getTagsByName('group');
            $group = ! empty($groupTag) ? (string) $groupTag[0] : null;

            return [
                'summary'     => $docBlock->getSummary(),
                'description' => (string) $docBlock->getDescription(),
                'group'       => $group,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract @papyrus-bodyParam directives from the controller method's DocBlock.
     *
     * Syntax: @papyrus-bodyParam {type} {key} {description}
     *
     * Examples:
     *   @papyrus-bodyParam string email The user's login email
     *   @papyrus-bodyParam file avatar The profile picture
     *   @papyrus-bodyParam string user.address.street The street address
     *   @papyrus-bodyParam integer age User's age (required)
     *
     * @param  \ReflectionMethod  $reflection
     * @return array  Array of ['type' => ..., 'key' => ..., 'description' => ...]
     */
    protected function extractDocBlockBodyParams(\ReflectionMethod $reflection): array
    {
        $comment = $reflection->getDocComment();
        if (! $comment) return [];

        $params = [];

        // Match: @papyrus-bodyParam {type} {key} {optional description}
        // The regex captures:
        //   1. type       — word chars (string, integer, file, boolean, object, array, etc.)
        //   2. key        — word chars + dots for nesting (user.address.street)
        //   3. description — everything after, up to end-of-line (optional)
        if (preg_match_all(
            '/@papyrus-bodyParam\s+(\w+)\s+([\w.*]+)\s*(.*?)\s*$/m',
            $comment,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $params[] = [
                    'type'        => strtolower(trim($match[1])),
                    'key'         => trim($match[2]),
                    'description' => trim($match[3]),
                ];
            }
        }

        return $params;
    }

    /**
     * Extract @papyrus-description-start / @papyrus-description-end block.
     * Keeps all internal formatting and newlines.
     */
    protected function extractDocBlockDescription(\ReflectionMethod $reflection): ?string
    {
        $comment = $reflection->getDocComment();
        if (! $comment) return null;

        if (preg_match('/@papyrus-description-start\s*\n(.*?)\s*@papyrus-description-end/s', $comment, $match)) {
            // Strip the leading `* ` from docblock lines
            $lines = explode("\n", $match[1]);
            $cleaned = array_map(function ($line) {
                return preg_replace('/^\s*\*\s?/', '', $line);
            }, $lines);
            return trim(implode("\n", $cleaned));
        }

        return null;
    }

    /**
     * Extract @papyrus-responseExample-start {statusCode} / @papyrus-responseExample-end blocks.
     * Returns an array keyed by status code.
     */
    protected function extractDocBlockResponseExamples(\ReflectionMethod $reflection): array
    {
        $comment = $reflection->getDocComment();
        if (! $comment) return [];

        $examples = [];
        if (preg_match_all('/@papyrus-responseExample-start\s+(\d+)[\s]+(.*?)\s*@papyrus-responseExample-end/s', $comment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statusCode = $match[1];
                $lines = explode("\n", $match[2]);
                $cleaned = array_map(function ($line) {
                    return preg_replace('/^\s*\*\s?/', '', $line);
                }, $lines);
                $examples[$statusCode] = trim(implode("\n", $cleaned));
            }
        }

        return $examples;
    }

    /**
     * Extract @papyrus-responseParam {statusCode} {type} {key} {description}
     * Returns an array keyed by status code, containing params for buildManualSchema().
     */
    protected function extractDocBlockResponseParams(\ReflectionMethod $reflection): array
    {
        $comment = $reflection->getDocComment();
        if (! $comment) return [];

        $params = [];
        if (preg_match_all('/@papyrus-responseParam\s+(\d+)\s+(\w+)\s+([\w.*]+)\s*(.*?)\s*$/m', $comment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statusCode = $match[1];
                if (!isset($params[$statusCode])) {
                    $params[$statusCode] = [];
                }
                $params[$statusCode][] = [
                    'type'        => strtolower(trim($match[2])),
                    'key'         => trim($match[3]),
                    'description' => trim($match[4]),
                ];
            }
        }

        return $params;
    }

    /**
     * Extract @papyrus-queryParam {type} {key} {description}
     */
    protected function extractDocBlockQueryParams(\ReflectionMethod $reflection): array
    {
        $comment = $reflection->getDocComment();
        if (! $comment) return [];

        $params = [];
        if (preg_match_all('/@papyrus-queryParam\s+(\w+)\s+([\w.*]+)\s*(.*?)\s*$/m', $comment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $params[] = [
                    'type'        => strtolower(trim($match[1])),
                    'key'         => trim($match[2]),
                    'description' => trim($match[3]),
                ];
            }
        }

        return $params;
    }

    /**
     * Extract @papyrus-header {key} {description}
     */
    protected function extractDocBlockHeaders(\ReflectionMethod $reflection): array
    {
        $comment = $reflection->getDocComment();
        if (! $comment) return [];

        $headers = [];
        if (preg_match_all('/@papyrus-header\s+([\w.-]+)\s*(.*?)\s*$/m', $comment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $headers[] = [
                    'key'         => trim($match[1]),
                    'description' => trim($match[2]),
                ];
            }
        }

        return $headers;
    }

    /**
     * Build a nested schema from manually defined @papyrus-bodyParam directives.
     *
     * Produces the EXACT SAME schema structure as getNestedSchema() so the
     * React frontend works identically regardless of source.
     *
     * Handles dot-notation nesting:
     *   'user.address.street' → user (object) → address (object) → street (string)
     *
     * @param  array  $manualParams  Array of ['type', 'key', 'description'] from DocBlock
     * @return array  Nested schema tree
     */
    protected function buildManualSchema(array $manualParams): array
    {
        // Type normalization map: DocBlock type → schema type
        $typeMap = [
            'string'  => 'text',
            'str'     => 'text',
            'text'    => 'text',
            'int'     => 'number',
            'integer' => 'number',
            'float'   => 'number',
            'double'  => 'number',
            'numeric' => 'number',
            'number'  => 'number',
            'bool'    => 'boolean',
            'boolean' => 'boolean',
            'file'    => 'file',
            'image'   => 'file',
            'date'    => 'date',
            'datetime' => 'date',
            'email'   => 'email',
            'url'     => 'url',
            'json'    => 'json',
            'array'   => 'array',
            'object'  => 'object',
            'password' => 'password',
            'color'   => 'color',
            'select'  => 'select',
        ];

        // Build flat enriched array, then nest it the same way as getNestedSchema
        $tree = [];

        foreach ($manualParams as $param) {
            $parts   = explode('.', $param['key']);
            $current = &$tree;
            $schemaType = $typeMap[$param['type']] ?? $param['type']; // Dynamic discovery fallback

            // Check if this key contains a wildcard for an array
            $isWildcardArray = in_array('*', $parts, true);
            $parsedParts = [];

            // Pre-process parts to handle '*'. E.g. 'users.*.name' -> ['users', 'name'] with isList on 'users'.
            foreach ($parts as $part) {
                if ($part === '*') {
                    if (!empty($parsedParts)) {
                        $parsedParts[count($parsedParts) - 1]['isList'] = true;
                    }
                    continue;
                }
                $parsedParts[] = [
                    'key' => $part,
                    'isPattern' => str_ends_with($part, '*'),
                    'isList' => false,
                ];
            }

            foreach ($parsedParts as $i => $partData) {
                $isLast = ($i === count($parsedParts) - 1);
                $part = $partData['key'];

                if (! isset($current[$part])) { // @phpstan-ignore isset.offset
                    $current[$part] = [
                        'key'      => $part,
                        'type'     => $isLast ? $schemaType : 'object',
                        'isList'   => false,
                        'children' => [],
                    ];
                }

                // If this part was marked as a list by a subsequent '*', update it
                if ($partData['isList']) {
                    $current[$part]['type'] = 'object'; // Arrays of objects are 'object' + 'isList'
                    $current[$part]['isList'] = true;
                    if ($isLast && $schemaType !== 'object') {
                        // If it's a list of scalars (e.g. users.* => string), it's type=array, childType=scalar
                        $current[$part]['type'] = 'array';
                        $current[$part]['isList'] = false; // It's an array of scalars, not an array of objects
                        $current[$part]['childType'] = $schemaType;
                    }
                }

                if ($partData['isPattern']) {
                    $current[$part]['isPattern'] = true;
                }

                if ($isLast) {
                    // Populate with the same shape as ValidationParser output
                    $current[$part] = array_merge($current[$part], [
                        'key'          => $part,
                        'type'         => $current[$part]['type'], // Keep the type computed above
                        'required'     => str_contains(strtolower($param['description']), 'required'),
                        'nullable'     => str_contains(strtolower($param['description']), 'nullable'),
                        'description'  => $param['description'],
                    ]);
                }

                $current = &$current[$part]['children'];
            }
        }

        return $this->formatSchemaNodes($tree);
    }

    /**
     * Extract raw validation rules from FormRequest type-hinted parameters.
     *
     * Uses the configurable `rules_methods` array to support FormRequests
     * that define rules in methods other than `rules()` (e.g., `createRules()`).
     *
     * @param  \ReflectionMethod  $reflection
     * @return array  Flat associative array: ['field.name' => 'required|string', ...]
     */
    protected function extractRawRules(\ReflectionMethod $reflection): array
    {
        $params  = [];
        $methods = config('papyrus.rules_methods', ['rules']);

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();
            if (is_subclass_of($className, \Illuminate\Foundation\Http\FormRequest::class)) {
                if (class_exists($className)) {
                    foreach ($methods as $method) {
                        try {
                            $request = null;
                            try {
                                // Try normal instantiation (resolves dependencies)
                                $request = app()->make($className);
                            } catch (\Throwable $t) {
                                // Fallback to without constructor if DI fails
                                $reflectionClass = new \ReflectionClass($className);
                                $request = $reflectionClass->newInstanceWithoutConstructor();
                            }

                            if (method_exists($request, $method)) {
                                $rules = $request->$method();
                                if (is_array($rules)) {
                                    $params = array_merge($params, $rules);
                                }
                            }
                        } catch (\Throwable $t) {
                            // If execution fails (e.g., accesses $this->route() which is null),
                            // fallback to static regex parsing of the file
                            $fallbackRules = $this->parseRulesStatically($className, $method);
                            $params = array_merge($params, $fallbackRules);
                        }
                    }
                }
            }
        }

        return $params;
    }

    /**
     * Fallback method to extract rules using Regex if executing rules() throws an exception.
     * Parses the PHP file statically to find array keys and rule values.
     *
     * @param  class-string  $className
     * @param  string  $methodName
     * @return array
     */
    protected function parseRulesStatically(string $className, string $methodName): array
    {
        try {
            $reflection = new \ReflectionMethod($className, $methodName);
            $fileName = $reflection->getFileName();
            if (! $fileName || ! file_exists($fileName)) {
                return [];
            }

            $lines = file($fileName);
            $rules = [];

            $start = $reflection->getStartLine() - 1;
            $end = $reflection->getEndLine() - 1;

            if ($start < 0 || $end < 0 || $lines === false) {
                return [];
            }

            for ($i = $start; $i <= $end; $i++) {
                $line = trim($lines[$i]);

                // Skip comments
                if (\Illuminate\Support\Str::startsWith($line, ['//', '#'])) {
                    continue;
                }

                // Only pick up rules coded with arrow notation
                if (! \Illuminate\Support\Str::contains($line, '=>')) {
                    continue;
                }

                // Match strings inside single or double quotes
                if (preg_match_all("/(?:'|\")(.*?)(?:'|\")/", $line, $matches)) {
                    if (count($matches[1]) >= 2) {
                        $key = $matches[1][0];
                        // Get all subsequent matched strings on this line as the rules for this key
                        $fieldRules = array_slice($matches[1], 1);
                        // ValidationParser normalizes either string or array, so array is fine.
                        $rules[$key] = $fieldRules;
                    }
                }
            }

            return $rules;
        } catch (\Throwable $t) {
            return [];
        }
    }

    /**
     * Parse raw rules into a deeply nested schema tree.
     *
     * Transforms flat dot-notation keys (e.g., 'meta.tags.*') into
     * a hierarchical tree structure with enriched validation metadata
     * at each leaf node.
     *
     * @param  array  $rawRules  Flat associative array of field => rules
     * @return array  Nested schema tree
     */
    protected function getNestedSchema(array $rawRules): array
    {
        // Step 1: Enrich each field using the ValidationParser
        $enriched = [];
        foreach ($rawRules as $key => $rules) {
            $enriched[$key] = $this->parser->parse($key, $rules);
        }

        // Step 2: Build tree from dot-notation keys
        $tree = [];
        foreach ($enriched as $key => $meta) {
            $parts   = explode('.', $key);
            $current = &$tree;

            foreach ($parts as $i => $part) {
                $isLast = ($i === count($parts) - 1);

                if (! isset($current[$part])) {
                    $current[$part] = [
                        'key'      => $part,
                        'type'     => $isLast ? $meta['type'] : 'object',
                        'children' => [],
                    ];
                }

                if ($isLast) {
                    // Merge all enriched metadata into the leaf node
                    $current[$part] = array_merge($current[$part], $meta);
                }

                $current = &$current[$part]['children'];
            }
        }

        return $this->formatSchemaNodes($tree);
    }

    /**
     * Format the raw tree into a clean, frontend-consumable schema.
     *
     * Handles three structural patterns:
     *   1. Wildcard arrays: `items.*` → array type with childType
     *   2. Wildcard objects: `items.*.name` → list of objects with schema
     *   3. Named children: `address.street` → object with schema
     *
     * @param  array  $nodes  Raw tree nodes
     * @return array  Formatted schema array
     */
    protected function formatSchemaNodes(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            // Check if this node key is a pattern (contains * but is not exactly *)
            $isPattern = str_contains((string)$node['key'], '*') && $node['key'] !== '*';
            if ($isPattern) {
                $node['isPattern'] = true;
            }

            // ── Handle wildcard '*' arrays ────────────────────────────
            if (isset($node['children']['*'])) {
                // If '*' is present, it suggests an array of items or array of objects.
                // We prioritize it even if other keys are somehow present (like an explicit rule for index 0).
                $wildcard = $node['children']['*'];

                if (! empty($wildcard['children'])) {
                    // Wildcard with children → list of objects
                    $node['type']   = 'object';
                    $node['schema'] = $this->formatSchemaNodes($wildcard['children']);
                    $node['isList'] = true;
                } else {
                    // Wildcard without children → simple array of scalar type
                    $node['type']      = 'array';
                    $node['childType'] = $wildcard['type'] ?? 'string';
                    $node['isList']    = false;

                    // Heuristic: keys containing 'meta' default to object builder
                    if (str_contains(strtolower((string)$node['key']), 'meta')) {
                        $node['type']   = 'object';
                        $node['schema'] = [];
                        $node['isList'] = false;
                    }
                }

                // If there are other named children besides '*', we could append them to schema,
                // but standard Laravel convention treats `courses.*` as the primary type definition for an array.
                unset($node['children']);
            }
            // ── Pre-configured arrays/lists (e.g. from buildManualSchema or JSON examples)
            elseif ($node['isList'] ?? false) {
                $node['type']   = 'object';
                $node['schema'] = $this->formatSchemaNodes($node['children'] ?? []);
                unset($node['children']);
            }
            // ── Named children → object ──────────────────────────────
            elseif (! empty($node['children'])) {
                $node['type']   = 'object';
                $node['schema'] = $this->formatSchemaNodes($node['children']);
                $node['isList'] = false;
                unset($node['children']);
            }
            // ── Leaf node (no children) ──────────────────────────────
            else {
                unset($node['children']);
                if (isset($node['isList']) && $node['isList'] !== true) {
                    // Ensure isList is present for UI normalization
                    $node['isList'] = false;
                }
            }

            $result[] = $node;
        }

        return $result;
    }

    /**
     * Attempt to determine response structure from return type (JsonResource).
     *
     * @param  \ReflectionMethod  $reflection
     * @return mixed|null
     */
    protected function extractResponse(\ReflectionMethod $reflection)
    {
        $returnType = $reflection->getReturnType();

        if (! $returnType instanceof \ReflectionNamedType || $returnType->isBuiltin()) {
            return null;
        }

        $className = $returnType->getName();

        if (is_subclass_of($className, \Illuminate\Http\Resources\Json\JsonResource::class)) {
            try {
                if (class_exists($className)) {
                    $resource = new $className([]);
                    return $resource->resolve();
                }
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Recursively auto-generate a schema tree from a decoded JSON payload array.
     * 
     * @param array $data Decoded JSON
     * @return array Nested schema tree matching the output format of ValidationParser
     */
    protected function buildSchemaFromJson(array $data): array
    {
        $schema = [];

        // Check if the current payload is a sequential array (a list/collection)
        $isList = array_is_list($data);

        // If it's a list, we analyze the first item to define the array schema
        if ($isList) {
            if (empty($data)) {
                return []; // Can't determine schema of empty list
            }

            $firstItem = $data[0];
            $type = gettype($firstItem);

            if ($type === 'array') {
                return $this->buildSchemaFromJson($firstItem);
            }

            return []; // Primitives directly at root list level not fully supported as nodes yet, usually wrapped
        }

        // Iterate over associative array properties
        foreach ($data as $key => $value) {
            $type = gettype($value);

            // Map PHP types to UI schema types
            $uiType = match ($type) {
                'integer', 'double' => 'number',
                'boolean'           => 'boolean',
                'array'             => 'object',
                'NULL'              => 'text', // Fallback
                default             => 'text',
            };

            $node = [
                'key'          => (string) $key,
                'type'         => $uiType,
                'required'     => false,
                'nullable'     => $type === 'NULL',
                'description'  => 'Auto-extracted from example',
                'children'     => [],
                'isList'       => false,
                'isPattern'    => false,
            ];

            if ($type === 'array') {
                if (array_is_list($value)) {
                    // For lists (sequential arrays), we need to determine if it's a list of objects or a list of scalars
                    $node['isList'] = true;
                    if (!empty($value)) {
                        $firstChild = $value[0];
                        if (is_array($firstChild)) {
                            // Array of objects
                            $node['type'] = 'object';
                            $node['children'] = $this->buildSchemaFromJson($firstChild);
                        } else {
                            // Array of scalars
                            $node['type'] = 'array';
                            $node['isList'] = false; // The parent type is 'array' holding scalars, not 'object' holding multiple copies
                            $node['childType'] = match (gettype($firstChild)) {
                                'integer', 'double' => 'number',
                                'boolean'           => 'boolean',
                                default             => 'text',
                            };
                        }
                    } else {
                        // Empty list, default to array of strings
                        $node['type'] = 'array';
                        $node['isList'] = false;
                        $node['childType'] = 'text';
                    }
                } else {
                    // Associative array (object)
                    $node['type'] = 'object';
                    $node['children'] = $this->buildSchemaFromJson($value);
                }
            }

            $schema[] = $node;
        }

        // Must run through formatSchemaNodes to convert 'children' arrays into UI 'schema' format!
        return $this->formatSchemaNodes($schema);
    }
}
