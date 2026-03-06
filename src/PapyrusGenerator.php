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
        // Extract legacy response (fallback via JsonResource return type)
        $resourceClass = $this->resolveResourceClass($reflection);
        if ($resourceClass) {
            $predicted = $this->predictFromResource($resourceClass);
            if ($predicted) {
                $responses['200'] = $predicted;
            }
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

        // ── AUTO-PREDICTION: code-driven ────────────────────────────────
        // Detect response content type
        $responseType = $this->detectResponseContentType($reflection, $route);

        // Predict 200 success response if not already documented
        if (! isset($responses['200']) && ! isset($responses['201']) && ! isset($responses['204'])) {
            if ($responseType === 'void') {
                $responses['204'] = [
                    'responseExample' => null,
                    'schema'          => [],
                    'contentType'     => 'no-content',
                    'description'     => 'This endpoint does not return a response body.',
                ];
            } elseif ($responseType === 'html') {
                $responses['200'] = [
                    'responseExample' => null,
                    'schema'          => [],
                    'contentType'     => 'text/html',
                    'description'     => 'Returns an HTML view.',
                ];
            } elseif ($responseType === 'redirect') {
                $responses['302'] = [
                    'responseExample' => null,
                    'schema'          => [],
                    'contentType'     => 'redirect',
                    'description'     => 'Redirects to another URL.',
                ];
            } elseif ($responseType === 'file') {
                $responses['200'] = [
                    'responseExample' => null,
                    'schema'          => [],
                    'contentType'     => 'application/octet-stream',
                    'description'     => 'Returns a file download.',
                ];
            } else {
                // JSON response — use existing prediction engine
                $predicted = $this->predictResponse($reflection, $bodyParams);
                $methods = array_map('strtoupper', $route->methods());
                $successCode = in_array('POST', $methods) ? '201' : '200';

                if ($predicted) {
                    $responses[$successCode] = $predicted;
                } else {
                    // Fallback: always show success status code
                    $responses[$successCode] = [
                        'responseExample' => ['message' => 'Success'],
                        'schema'          => $this->buildSchemaFromJson(['message' => 'Success']),
                    ];
                }
            }
        }

        // Predict error responses from actual code analysis
        $potentialErrors = $this->detectPotentialStatusCodes($route, $reflection, $bodyParams);

        foreach ($potentialErrors as $statusCode) {
            if (isset($responses[$statusCode])) {
                continue;
            }

            $errorExample = $this->buildErrorExample($statusCode, $bodyParams);
            if ($errorExample) {
                $responses[$statusCode] = [
                    'responseExample' => $errorExample,
                    'schema'          => $this->buildSchemaFromJson($errorExample),
                ];
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
     * Detect which HTTP error status codes a specific route can actually produce.
     *
     * Inspects:
     *   - Middleware: auth → 401, can:/authorize → 403, throttle → 429
     *   - FormRequest type-hints → 422 (validation)
     *   - Route model binding (implicit/explicit) → 404
     *   - Source code patterns: findOrFail/firstOrFail → 404, authorize() → 403, abort() → various
     *   - HTTP method heuristics: API mutating routes usually need auth
     *
     * @param  \Illuminate\Routing\Route  $route
     * @param  \ReflectionMethod  $reflection
     * @param  array  $bodyParams
     * @return array<string>
     */
    protected function detectPotentialStatusCodes($route, \ReflectionMethod $reflection, array $bodyParams = []): array
    {
        $codes = [];
        $middlewares = $route->gatherMiddleware();
        $middlewareStr = implode(',', array_map(fn($m) => is_string($m) ? $m : '', $middlewares));

        // ── Known exception → status code map ────────────────────────
        $exceptionMap = [
            'AuthenticationException'        => '401',
            'AuthorizationException'         => '403',
            'AccessDeniedHttpException'      => '403',
            'UnauthorizedException'          => '403',
            'ModelNotFoundException'          => '404',
            'NotFoundHttpException'          => '404',
            'MethodNotAllowedHttpException'  => '405',
            'ValidationException'            => '422',
            'ThrottleRequestsException'      => '429',
            'TooManyRequestsHttpException'   => '429',
            'HttpException'                  => '500',
            'HttpResponseException'          => '500',
            'TokenMismatchException'         => '419',
            'MaintenanceModeException'       => '503',
            'ServiceUnavailableHttpException' => '503',
            'ConflictHttpException'          => '409',
            'GoneHttpException'              => '410',
            'BadRequestHttpException'        => '400',
            'UnprocessableEntityHttpException' => '422',
            'TranslatableException'          => '500',
        ];

        // ── Middleware-based detection ───────────────────────────────
        if (preg_match('/\bauth\b/', $middlewareStr)) {
            $codes[] = '401';
        }
        if (preg_match('/\b(can:|permission|role)\b/', $middlewareStr)) {
            $codes[] = '403';
        }
        if (preg_match('/\bthrottle\b/', $middlewareStr)) {
            $codes[] = '429';
        }
        if (preg_match('/\bverified\b/', $middlewareStr)) {
            $codes[] = '403';
        }

        // ── FormRequest → 422 + 403 ──────────────────────────────────
        if (! empty($bodyParams)) {
            $codes[] = '422';
        }
        foreach ($reflection->getParameters() as $param) {
            $paramType = $param->getType();
            if ($paramType instanceof \ReflectionNamedType && ! $paramType->isBuiltin()) {
                $className = $paramType->getName();
                if (class_exists($className) && is_subclass_of($className, \Illuminate\Foundation\Http\FormRequest::class)) {
                    $codes[] = '422';
                    $codes[] = '403';
                    break;
                }
            }
        }

        // ── Route model binding → 404 ────────────────────────────────
        if (preg_match('/\{[a-zA-Z_]+\}/', $route->uri())) {
            $codes[] = '404';
        }

        // ── @throws docblock analysis ────────────────────────────────
        $comment = $reflection->getDocComment();
        if ($comment && preg_match_all('/@throws\s+\\\\?([\w\\\\]+)/', $comment, $throwMatches)) {
            foreach ($throwMatches[1] as $exceptionClass) {
                $basename = class_basename($exceptionClass);
                if (isset($exceptionMap[$basename])) {
                    $codes[] = $exceptionMap[$basename];
                }
                // Try to resolve the full class to check for HttpException with status code
                $this->resolveExceptionCode($exceptionClass, $codes, $exceptionMap, $reflection);
            }
        }

        // ── Source code analysis ─────────────────────────────────────
        try {
            $filename = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if ($filename && file_exists($filename)) {
                $lines = array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1);
                $source = implode('', $lines);

                // findOrFail / firstOrFail / sole → 404
                if (preg_match('/\b(findOrFail|firstOrFail|sole|->findOrFail|->firstOrFail)\b/', $source)) {
                    $codes[] = '404';
                }

                // authorize → 403
                if (preg_match('/\b(->authorize\(|Gate::authorize|Gate::denies|Gate::allows|Gate::check)\b/', $source)) {
                    $codes[] = '403';
                }

                // abort(N)
                if (preg_match_all('/\babort\(\s*(\d{3})\s*[\),]/', $source, $abortMatches)) {
                    foreach ($abortMatches[1] as $code) {
                        $codes[] = $code;
                    }
                }

                // abort_if / abort_unless
                if (preg_match_all('/\b(abort_if|abort_unless)\(.+?,\s*(\d{3})\s*[\),]/s', $source, $abortMatches)) {
                    foreach ($abortMatches[2] as $code) {
                        $codes[] = $code;
                    }
                }

                // throw new ExceptionClass → map to status code
                if (preg_match_all('/throw\s+new\s+\\\\?([\w\\\\]+)/', $source, $throwMatches)) {
                    foreach ($throwMatches[1] as $exceptionClass) {
                        $basename = class_basename($exceptionClass);
                        if (isset($exceptionMap[$basename])) {
                            $codes[] = $exceptionMap[$basename];
                        }
                        $this->resolveExceptionCode($exceptionClass, $codes, $exceptionMap, $reflection);
                    }
                }

                // response()->json(... , 4xx/5xx) — explicit status returns
                if (preg_match_all('/->json\(.+?,\s*(4\d{2}|5\d{2})\s*\)/s', $source, $jsonStatusMatches)) {
                    foreach ($jsonStatusMatches[1] as $code) {
                        $codes[] = $code;
                    }
                }

                // return response(... , 4xx/5xx)
                if (preg_match_all('/\bresponse\(.+?,\s*(4\d{2}|5\d{2})\s*\)/s', $source, $respMatches)) {
                    foreach ($respMatches[1] as $code) {
                        $codes[] = $code;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently skip
        }

        // ── HTTP method heuristics ───────────────────────────────────
        $methods = array_map('strtoupper', $route->methods());
        if (array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (str_starts_with($route->uri(), 'api')) {
                $codes[] = '401';
            }
        }

        // 500 is always possible
        $codes[] = '500';

        return array_values(array_unique($codes));
    }

    /**
     * Attempt to resolve a thrown exception class to its HTTP status code.
     */
    protected function resolveExceptionCode(string $exceptionClass, array &$codes, array $exceptionMap, \ReflectionMethod $reflection): void
    {
        // Try full class name
        $fqcn = ltrim($exceptionClass, '\\');
        if (! class_exists($fqcn)) {
            // Try relative to controller namespace
            $ns = $reflection->getDeclaringClass()->getNamespaceName();
            $fqcn = $ns . '\\' . $exceptionClass;
        }

        if (class_exists($fqcn)) {
            // If it extends HttpException, it has a getStatusCode() method
            if (is_subclass_of($fqcn, \Symfony\Component\HttpKernel\Exception\HttpException::class)) {
                try {
                    $ref = new \ReflectionClass($fqcn);
                    $constructor = $ref->getConstructor();
                    // Try to instantiate with default message to get status code
                    if ($constructor && $constructor->getNumberOfRequiredParameters() === 0) {
                        $instance = new $fqcn();
                        $codes[] = (string) $instance->getStatusCode();
                    }
                } catch (\Throwable $e) {
                    // Skip
                }
            }
        }
    }

    /**
     * Detect the primary response content type of a controller method.
     *
     * @return string  'json' | 'html' | 'redirect' | 'file' | 'unknown'
     */
    protected function detectResponseContentType(\ReflectionMethod $reflection, $route): string
    {
        // ── 1. Check native return type hint ─────────────────────────
        $returnType = $reflection->getReturnType();

        // void → no response body
        if ($returnType instanceof \ReflectionNamedType && $returnType->isBuiltin() && $returnType->getName() === 'void') {
            return 'void';
        }

        // Handle union types (e.g., JsonResponse|RedirectResponse)
        $typeNames = [];
        if ($returnType instanceof \ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                if ($type instanceof \ReflectionNamedType) {
                    $typeNames[] = $type->getName();
                }
            }
        } elseif ($returnType instanceof \ReflectionNamedType && ! $returnType->isBuiltin()) {
            $typeNames[] = $returnType->getName();
        }

        foreach ($typeNames as $className) {
            if (! class_exists($className) && ! interface_exists($className)) {
                continue;
            }

            // JsonResponse or any subclass → json
            if (
                $className === \Illuminate\Http\JsonResponse::class
                || is_subclass_of($className, \Illuminate\Http\JsonResponse::class)
            ) {
                return 'json';
            }

            // JsonResource / ResourceCollection → json
            if (
                is_subclass_of($className, \Illuminate\Http\Resources\Json\JsonResource::class)
                || $className === \Illuminate\Http\Resources\Json\JsonResource::class
                || $className === \Illuminate\Http\Resources\Json\AnonymousResourceCollection::class
                || is_subclass_of($className, \Illuminate\Http\Resources\Json\ResourceCollection::class)
            ) {
                return 'json';
            }

            // Generic Response — could be JSON or HTML, we'll check source
            if (
                $className === \Illuminate\Http\Response::class
                || is_subclass_of($className, \Illuminate\Http\Response::class)
                || $className === \Symfony\Component\HttpFoundation\Response::class
            ) {
                // Don't return yet — let source analysis decide
                continue;
            }

            // RedirectResponse → redirect
            if (
                $className === \Illuminate\Http\RedirectResponse::class
                || is_subclass_of($className, \Illuminate\Http\RedirectResponse::class)
            ) {
                return 'redirect';
            }

            // BinaryFileResponse / StreamedResponse → file
            if (
                is_subclass_of($className, \Symfony\Component\HttpFoundation\BinaryFileResponse::class)
                || is_subclass_of($className, \Symfony\Component\HttpFoundation\StreamedResponse::class)
            ) {
                return 'file';
            }

            // Responsable interface (custom classes implementing toResponse) → json likely
            if (is_subclass_of($className, \Illuminate\Contracts\Support\Responsable::class)) {
                return 'json';
            }
        }

        // ── 2. Check @return docblock ────────────────────────────────
        $comment = $reflection->getDocComment();
        if ($comment && preg_match('/@return\s+([^\n]+)/', $comment, $returnMatch)) {
            $returnDoc = $returnMatch[1];
            $returnDocLower = strtolower($returnDoc);

            if (str_contains($returnDocLower, 'void') || str_contains($returnDocLower, 'never')) {
                return 'void';
            }
            if (preg_match('/(Resource|Collection|JsonResponse|JsonResource)/i', $returnDoc)) {
                return 'json';
            }
            if (preg_match('/(RedirectResponse|Redirect)/i', $returnDoc)) {
                return 'redirect';
            }
            if (preg_match('/(View|Renderable|Inertia)/i', $returnDoc)) {
                return 'html';
            }
            if (preg_match('/(BinaryFileResponse|StreamedResponse|SplFileInfo)/i', $returnDoc)) {
                return 'file';
            }
        }

        // ── 3. Check middleware → likely content type ─────────────────
        $middlewares = $route->gatherMiddleware();
        $middlewareStr = implode(',', array_map(fn($m) => is_string($m) ? $m : '', $middlewares));
        if (preg_match('/\bapi\b/', $middlewareStr) || str_starts_with($route->uri(), 'api')) {
            return 'json';
        }

        // ── 4. Scan source code patterns ─────────────────────────────
        try {
            $filename = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if ($filename && file_exists($filename)) {
                $lines = array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1);
                $source = implode('', $lines);

                // return view(... / Inertia::render → html
                if (preg_match('/\b(return\s+view\s*\(|Inertia::render\s*\(|->view\s*\()/', $source)) {
                    return 'html';
                }

                // return redirect(... / to_route → redirect
                if (preg_match('/\b(return\s+redirect\s*\(|->redirect\s*\(|to_route\s*\()/', $source)) {
                    return 'redirect';
                }

                // response()->noContent() → void
                if (preg_match('/->noContent\s*\(/', $source)) {
                    return 'void';
                }

                // JSON patterns: Resource, Collection, response()->json(), ResponseHelper, etc.
                if (preg_match('/(
                    ->json\s*\(                           # response()->json()
                    |new\s+\w+Resource\b                  # new UserResource(...)
                    |new\s+\w+Collection\b                # new UserCollection(...)
                    |\w+Resource::make\s*\(               # UserResource::make(...)
                    |\w+Resource::collection\s*\(         # UserResource::collection(...)
                    |\w+Collection::make\s*\(             # ResourceCollection::make(...)
                    |ResourceCollection\b                  # AnonymousResourceCollection
                    |ResponseHelper::                      # ResponseHelper::success(...)
                    |ApiResponse::                         # ApiResponse::success(...)
                    |JsonResponse\b                        # new JsonResponse(...)
                    |->toResponse\s*\(                    # Responsable::toResponse()
                    |response\(\)->json\s*\(              # response()->json()
                    |return\s+response\s*\(\s*\[          # return response([...])
                )/x', $source)) {
                    return 'json';
                }

                // File download patterns
                if (preg_match('/->(download|streamDownload|file)\s*\(/', $source)) {
                    return 'file';
                }
            }
        } catch (\Throwable $e) {
            // Skip
        }

        // ── 5. Web middleware → HTML ──────────────────────────────────
        if (preg_match('/\bweb\b/', $middlewareStr)) {
            return 'html';
        }

        return 'json'; // Default assumption for API docs
    }

    /**
     * Build a standard Laravel error example for a given HTTP status code.
     *
     * Produces the default Laravel exception handler JSON format.
     */
    protected function buildErrorExample(string $statusCode, array $bodyParams = []): ?array
    {
        $messages = [
            '400' => 'Bad Request.',
            '401' => 'Unauthenticated.',
            '403' => 'This action is unauthorized.',
            '404' => 'The requested resource was not found.',
            '405' => 'The method is not allowed for the requested URL.',
            '409' => 'Conflict.',
            '410' => 'The requested resource is no longer available.',
            '419' => 'Page Expired.',
            '422' => 'The given data was invalid.',
            '429' => 'Too Many Attempts.',
            '500' => 'Server Error.',
            '503' => 'Service Unavailable.',
        ];

        $message = $messages[$statusCode] ?? 'Error.';

        // 422 gets dynamic validation errors from body params
        if ($statusCode === '422' && ! empty($bodyParams)) {
            $base = ['message' => $message, 'errors' => []];
            return $this->build422FromBodyParams($base, $bodyParams);
        }

        return ['message' => $message];
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
    /**
     * Predict the response structure from the controller method.
     *
     * Strategy (in priority order):
     *   1. Check return type for JsonResource subclass → read `toArray()` source to extract keys
     *   2. Check `@return` docblock for JsonResource references
     *   3. Build a synthetic example from the body parameters (echo-back pattern)
     *
     * @param  \ReflectionMethod  $reflection
     * @param  array  $bodyParams  The already-parsed body parameter schema
     * @return array{responseExample: mixed, schema: array}|null
     */
    protected function predictResponse(\ReflectionMethod $reflection, array $bodyParams = []): ?array
    {
        // ── Strategy 1: Return type is a JsonResource ────────────────
        $resourceClass = $this->resolveResourceClass($reflection);

        if ($resourceClass) {
            $predicted = $this->predictFromResource($resourceClass);
            if ($predicted) {
                return $predicted;
            }
        }

        // ── Strategy 2: Scan source code for Resource/Collection usage ─
        try {
            $filename = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if ($filename && file_exists($filename)) {
                $allLines = file($filename);
                $methodLines = array_slice($allLines, $startLine - 1, $endLine - $startLine + 1);
                $source = implode('', $methodLines);

                // Look for: new XResource(, XResource::make(, XResource::collection(, new XCollection(
                if (preg_match('/(new\s+(\w+Resource)\s*\(|(\w+Resource)::(?:make|collection)\s*\(|new\s+(\w+Collection)\s*\()/', $source, $m)) {
                    $shortName = $m[2] ?: ($m[3] ?: $m[4]);

                    if ($shortName) {
                        // Resolve the short class name using the file's use imports
                        $resolvedClass = $this->resolveShortClassName($shortName, $allLines, $reflection);
                        if (
                            $resolvedClass && class_exists($resolvedClass)
                            && is_subclass_of($resolvedClass, \Illuminate\Http\Resources\Json\JsonResource::class)
                        ) {
                            $predicted = $this->predictFromResource($resolvedClass);
                            if ($predicted) {
                                return $predicted;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Skip
        }

        // ── Strategy 3: Extract keys from inline JSON response patterns ─
        try {
            $filename = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if ($filename && file_exists($filename)) {
                $lines = array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1);
                $source = implode('', $lines);

                $keys = $this->extractInlineResponseKeys($source);
                if (! empty($keys)) {
                    $example = [];
                    foreach ($keys as $key) {
                        $example[$key] = $this->guessValueForKey($key);
                    }

                    return [
                        'responseExample' => $example,
                        'schema'          => $this->buildSchemaFromJson($example),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Skip
        }

        // ── Strategy 4: Detect Model from route model binding ────────
        try {
            $predicted = $this->predictFromModelBinding($reflection);
            if ($predicted) {
                return $predicted;
            }
        } catch (\Throwable $e) {
            // Skip
        }

        // ── Strategy 5: Infer from body params (echo-back heuristic) ─
        if (! empty($bodyParams)) {
            $example = $this->generateExampleFromSchema($bodyParams);
            if (! empty($example)) {
                return [
                    'responseExample' => $example,
                    'schema'          => $this->buildSchemaFromJson($example),
                ];
            }
        }

        return null;
    }

    /**
     * Extract response keys from inline JSON patterns in source code.
     *
     * Detects patterns like:
     *   return response()->json(['key1' => ..., 'key2' => ...])
     *   return ['key1' => ..., 'key2' => ...]
     */
    protected function extractInlineResponseKeys(string $source): array
    {
        $keys = [];

        // Match: return response()->json([...]) or ResponseHelper/ApiResponse patterns
        if (preg_match_all("/return\s+(?:response\(\)->json|response\.json|response)\s*\(\s*\[/", $source, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = end($matches[0])[1];
            $keys = array_merge($keys, $this->extractArrayKeysFromOffset($source, $offset));
        }

        // Match: return ['key' => ...]  (direct array return)
        if (empty($keys) && preg_match_all("/return\s+\[/", $source, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = end($matches[0])[1];
            $keys = array_merge($keys, $this->extractArrayKeysFromOffset($source, $offset));
        }

        // Match: ResponseHelper::success([...]) / ApiResponse::success([...])
        if (empty($keys) && preg_match_all("/(ResponseHelper|ApiResponse)::\w+\s*\(\s*\[/", $source, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = end($matches[0])[1];
            $keys = array_merge($keys, $this->extractArrayKeysFromOffset($source, $offset));
        }

        return array_values(array_unique($keys));
    }

    /**
     * Extract top-level array keys from source starting at a bracket offset.
     */
    protected function extractArrayKeysFromOffset(string $source, int $offset): array
    {
        $bracketPos = strpos($source, '[', $offset);
        if ($bracketPos === false) {
            return [];
        }

        $depth = 0;
        $content = '';
        for ($i = $bracketPos; $i < strlen($source); $i++) {
            $char = $source[$i];
            if ($char === '[') {
                $depth++;
            }
            if ($char === ']') {
                $depth--;
            }
            if ($depth === 0) {
                $content = substr($source, $bracketPos + 1, $i - $bracketPos - 1);
                break;
            }
        }

        $keys = [];
        if (preg_match_all("/['\"](\w+)['\"]\s*=>/", $content, $m)) {
            $keys = $m[1];
        }

        return $keys;
    }

    /**
     * Predict response from Eloquent model type-hinted via route model binding.
     */
    protected function predictFromModelBinding(\ReflectionMethod $reflection): ?array
    {
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();

            // Skip framework types
            if (
                str_starts_with($className, 'Illuminate\\')
                || str_starts_with($className, 'Symfony\\')
                || str_starts_with($className, 'App\\Http\\Requests\\')
            ) {
                continue;
            }

            if (class_exists($className) && is_subclass_of($className, \Illuminate\Database\Eloquent\Model::class)) {
                return $this->predictFromModel($className);
            }
        }

        return null;
    }

    /**
     * Build a response example from an Eloquent model's visible/fillable attributes.
     */
    protected function predictFromModel(string $modelClass): ?array
    {
        try {
            $reflectionClass = new \ReflectionClass($modelClass);
            $model = $reflectionClass->newInstanceWithoutConstructor();

            $attributes = [];

            if (method_exists($model, 'getVisible') && ! empty($model->getVisible())) {
                $attributes = $model->getVisible();
            } elseif (method_exists($model, 'getFillable') && ! empty($model->getFillable())) {
                $attributes = $model->getFillable();
                $attributes = array_merge(['id'], $attributes, ['created_at', 'updated_at']);
            }

            if (method_exists($model, 'getHidden') && ! empty($model->getHidden())) {
                $attributes = array_diff($attributes, $model->getHidden());
            }

            if (empty($attributes)) {
                return null;
            }

            $example = [];
            foreach ($attributes as $key) {
                $example[$key] = $this->guessValueForKey($key);
            }

            if (method_exists($model, 'getCasts')) {
                $casts = $model->getCasts();
                foreach ($casts as $key => $castType) {
                    if (! isset($example[$key])) {
                        continue;
                    }
                    $example[$key] = $this->guessValueFromCast($key, $castType);
                }
            }

            return [
                'responseExample' => $example,
                'schema'          => $this->buildSchemaFromJson($example),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Guess a realistic example value for a response key based on naming conventions.
     */
    protected function guessValueForKey(string $key): mixed
    {
        $keyLower = strtolower($key);

        if ($keyLower === 'id' || str_ends_with($keyLower, '_id')) {
            return 1;
        }
        if (
            str_starts_with($keyLower, 'is_') || str_starts_with($keyLower, 'has_')
            || str_starts_with($keyLower, 'can_') || $keyLower === 'active'
            || $keyLower === 'verified' || $keyLower === 'enabled'
        ) {
            return true;
        }
        if (
            str_contains($keyLower, '_at') || str_contains($keyLower, 'date')
            || $keyLower === 'created_at' || $keyLower === 'updated_at'
        ) {
            return '2025-01-01T00:00:00.000000Z';
        }
        if (str_contains($keyLower, 'email')) {
            return 'user@example.com';
        }
        if (str_contains($keyLower, 'name') || $keyLower === 'title' || $keyLower === 'label') {
            return 'Example ' . str_replace('_', ' ', $key);
        }
        if (
            str_contains($keyLower, 'url') || str_contains($keyLower, 'link')
            || str_contains($keyLower, 'avatar') || str_contains($keyLower, 'image')
            || str_contains($keyLower, 'photo')
        ) {
            return 'https://example.com/' . $keyLower;
        }
        if (str_contains($keyLower, 'phone') || str_contains($keyLower, 'mobile')) {
            return '+1234567890';
        }
        if (
            str_contains($keyLower, 'count') || str_contains($keyLower, 'total')
            || str_contains($keyLower, 'amount') || str_contains($keyLower, 'quantity')
            || str_contains($keyLower, 'price') || str_contains($keyLower, 'balance')
        ) {
            return 0;
        }
        if ($keyLower === 'status' || $keyLower === 'state') {
            return 'active';
        }
        if ($keyLower === 'type' || $keyLower === 'role') {
            return 'default';
        }
        if (
            str_contains($keyLower, 'description') || str_contains($keyLower, 'content')
            || str_contains($keyLower, 'body') || str_contains($keyLower, 'text')
            || str_contains($keyLower, 'bio') || str_contains($keyLower, 'summary')
        ) {
            return null; // Don't fabricate description text, let it be null unless explicitly provided
        }
        if ($keyLower === 'message') {
            return 'Success';
        }
        if ($keyLower === 'data') {
            return [];
        }

        return 'string';
    }

    /**
     * Guess a value from an Eloquent cast type.
     */
    protected function guessValueFromCast(string $key, string $castType): mixed
    {
        $castLower = strtolower($castType);

        if (in_array($castLower, ['bool', 'boolean'])) {
            return true;
        }
        if (in_array($castLower, ['int', 'integer'])) {
            return 0;
        }
        if (in_array($castLower, ['float', 'double', 'decimal'])) {
            return 0.0;
        }
        if (in_array($castLower, ['array', 'json', 'collection', 'object'])) {
            return [];
        }
        if (in_array($castLower, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'])) {
            return '2025-01-01T00:00:00.000000Z';
        }

        return $this->guessValueForKey($key);
    }

    /**
     * Resolve a short class name (e.g. 'UserResource') to its FQCN
     * by scanning the file's `use` import statements.
     */
    protected function resolveShortClassName(string $shortName, array $fileLines, \ReflectionMethod $reflection): ?string
    {
        // 1. Scan `use` imports at the top of the file
        foreach ($fileLines as $line) {
            $line = trim($line);
            // Stop scanning at class declaration
            if (preg_match('/^\s*(abstract\s+)?(class|trait|interface|enum)\s+/', $line)) {
                break;
            }
            // Match use statements
            if (preg_match('/^use\s+([\w\\\\]+\\\\' . preg_quote($shortName, '/') . ')\s*;/', $line, $m)) {
                return $m[1];
            }
            // Match aliased imports: use Foo\Bar as ShortName;
            if (preg_match('/^use\s+([\w\\\\]+)\s+as\s+' . preg_quote($shortName, '/') . '\s*;/', $line, $m)) {
                return $m[1];
            }
        }

        // 2. Try controller's namespace + short name
        $ns = $reflection->getDeclaringClass()->getNamespaceName();
        $candidate = $ns . '\\' . $shortName;
        if (class_exists($candidate)) {
            return $candidate;
        }

        // 3. Try as fully qualified
        if (class_exists($shortName)) {
            return $shortName;
        }

        return null;
    }

    /**
     * Resolve the JsonResource class from the method's return type or @return docblock.
     *
     * @param  \ReflectionMethod  $reflection
     * @return string|null  Fully qualified class name of the resource
     */
    protected function resolveResourceClass(\ReflectionMethod $reflection): ?string
    {
        // Check native return type first
        $returnType = $reflection->getReturnType();

        if ($returnType instanceof \ReflectionNamedType && ! $returnType->isBuiltin()) {
            $className = $returnType->getName();
            if (class_exists($className) && is_subclass_of($className, \Illuminate\Http\Resources\Json\JsonResource::class)) {
                return $className;
            }
        }

        // Fallback: scan @return docblock tag
        $comment = $reflection->getDocComment();
        if ($comment && preg_match('/@return\s+\\\\?([\w\\\\]+Resource\w*)/', $comment, $m)) {
            $candidate = $m[1];

            // Try to resolve using the controller's namespace
            $controllerNs = $reflection->getDeclaringClass()->getNamespaceName();
            $fqcn = ltrim($candidate, '\\');

            // Try as-is first
            if (class_exists($fqcn) && is_subclass_of($fqcn, \Illuminate\Http\Resources\Json\JsonResource::class)) {
                return $fqcn;
            }

            // Try relative to controller namespace
            $relative = $controllerNs . '\\' . $candidate;
            if (class_exists($relative) && is_subclass_of($relative, \Illuminate\Http\Resources\Json\JsonResource::class)) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * Predict response structure by reading the `toArray()` source code of a JsonResource.
     *
     * Parses `$this->key` references and quoted array keys to build a field map.
     *
     * @param  string  $resourceClass
     * @return array{responseExample: mixed, schema: array}|null
     */
    protected function predictFromResource(string $resourceClass): ?array
    {
        try {
            $ref = new \ReflectionClass($resourceClass);
            if (! $ref->hasMethod('toArray')) {
                return null;
            }

            $toArray = $ref->getMethod('toArray');
            $filename = $toArray->getFileName();
            $startLine = $toArray->getStartLine();
            $endLine = $toArray->getEndLine();

            if (! $filename || ! file_exists($filename)) {
                return null;
            }

            $lines = array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1);
            $source = implode('', $lines);

            // Extract array keys: 'key' => $this->..., "key" => ...
            $example = [];
            if (preg_match_all("/['\"](\w+)['\"]\s*=>/", $source, $matches)) {
                foreach ($matches[1] as $key) {
                    $example[$key] = $this->guessExampleValue($key, $source);
                }
            }

            if (empty($example)) {
                return null;
            }

            return [
                'responseExample' => $example,
                'schema'          => $this->buildSchemaFromJson($example),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Synthesize an example value for a given key based on naming conventions.
     *
     * @param  string  $key
     * @param  string  $source  The source code context for advanced heuristics
     * @return mixed
     */
    protected function guessExampleValue(string $key, string $source = ''): mixed
    {
        $lower = strtolower($key);

        // Boolean fields
        if (in_array($lower, ['is_active', 'is_verified', 'is_admin', 'active', 'verified', 'enabled', 'success', 'is_published', 'is_featured'])) {
            return true;
        }

        // ID fields
        if ($lower === 'id' || str_ends_with($lower, '_id')) {
            return 1;
        }

        // Count/number fields
        if (str_ends_with($lower, '_count') || str_ends_with($lower, '_total') || in_array($lower, ['count', 'total', 'amount', 'price', 'quantity', 'order'])) {
            return 0;
        }

        // Email
        if (str_contains($lower, 'email')) {
            return 'user@example.com';
        }

        // Phone
        if (str_contains($lower, 'phone') || str_contains($lower, 'mobile')) {
            return '+1234567890';
        }

        // URL/link
        if (str_contains($lower, 'url') || str_contains($lower, 'link') || str_contains($lower, 'avatar') || str_contains($lower, 'image') || str_contains($lower, 'photo')) {
            return 'https://example.com/resource';
        }

        // Date/time patterns
        if (str_contains($lower, 'date') || str_contains($lower, '_at') || str_ends_with($lower, '_on')) {
            return '2025-01-01T00:00:00.000000Z';
        }

        // Name patterns
        if (in_array($lower, ['name', 'title', 'label', 'username', 'first_name', 'last_name', 'full_name', 'display_name'])) {
            return 'Example ' . ucfirst(str_replace('_', ' ', $key));
        }

        // Description/text
        if (in_array($lower, ['description', 'body', 'content', 'text', 'bio', 'summary', 'message', 'note', 'notes'])) {
            return null;
        }

        // Token/key
        if (str_contains($lower, 'token') || str_contains($lower, 'secret') || str_contains($lower, 'api_key')) {
            return 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...';
        }

        // Type/status/role
        if (in_array($lower, ['type', 'status', 'role', 'guard', 'guard_name', 'provider'])) {
            return $key;
        }

        // Check if the source context hints at array/relationship usage
        if (preg_match("/['\"]" . preg_quote($key) . "['\"]\s*=>\s*\\\$this->(\w+)/", $source, $ctx)) {
            $accessor = $ctx[1];
            // If accessing a relationship that's likely a collection
            if (in_array($accessor, ['permissions', 'roles', 'tags', 'items', 'children'])) {
                return [];
            }
        }

        // Default string
        return 'string';
    }

    /**
     * Generate a synthetic example payload from an already-parsed body parameter schema.
     *
     * @param  array  $schema
     * @return array
     */
    protected function generateExampleFromSchema(array $schema): array
    {
        $example = [];

        foreach ($schema as $node) {
            $key = $node['key'] ?? null;
            if (! $key) continue;

            $type = $node['type'] ?? 'text';

            if (($node['isList'] ?? false) && ! empty($node['schema'] ?? $node['children'] ?? [])) {
                // Array of objects
                $example[$key] = [$this->generateExampleFromSchema($node['schema'] ?? $node['children'] ?? [])];
            } elseif ($type === 'object' && ! empty($node['schema'] ?? $node['children'] ?? [])) {
                $example[$key] = $this->generateExampleFromSchema($node['schema'] ?? $node['children'] ?? []);
            } elseif ($type === 'array') {
                $childType = $node['childType'] ?? 'text';
                $example[$key] = match ($childType) {
                    'number'  => [1, 2, 3],
                    'boolean' => [true, false],
                    default   => ['example1', 'example2'],
                };
            } else {
                $example[$key] = $this->guessExampleValue($key);
            }
        }

        return $example;
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
                'description'  => null,
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

    /**
     * Build a realistic 422 validation error structure from the actual body parameters.
     *
     * Takes the user's configured error shape and replaces the placeholder 'errors'
     * object with real field names from the endpoint's body params.
     *
     * @param  array  $structure  The base 422 structure from config
     * @param  array  $bodyParams  Parsed body parameter schema nodes
     * @return array
     */
    protected function build422FromBodyParams(array $structure, array $bodyParams): array
    {
        $errors = [];

        // Collect top-level field keys from the body params schema
        $fieldKeys = $this->collectFieldKeys($bodyParams);

        foreach ($fieldKeys as $fieldKey) {
            $errors[$fieldKey] = [
                'The ' . str_replace('_', ' ', $fieldKey) . ' field is required.',
            ];
        }

        if (! empty($errors)) {
            $structure['errors'] = $errors;
        }

        return $structure;
    }

    /**
     * Recursively collect field keys from a schema tree for validation error generation.
     *
     * @param  array  $nodes
     * @param  string  $prefix
     * @param  int  $maxDepth
     * @return array
     */
    protected function collectFieldKeys(array $nodes, string $prefix = '', int $maxDepth = 2): array
    {
        if ($maxDepth <= 0) return [];

        $keys = [];

        foreach ($nodes as $node) {
            $key = $node['key'] ?? null;
            if (! $key) continue;

            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            $type = $node['type'] ?? 'text';
            $children = $node['schema'] ?? $node['children'] ?? [];

            if (($type === 'object' || ($node['isList'] ?? false)) && ! empty($children)) {
                // Recurse into nested objects
                $keys = array_merge($keys, $this->collectFieldKeys($children, $fullKey, $maxDepth - 1));
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }
}
