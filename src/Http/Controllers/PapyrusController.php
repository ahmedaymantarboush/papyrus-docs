<?php

namespace AhmedTarboush\PapyrusDocs\Http\Controllers;

use Illuminate\Routing\Controller;
use AhmedTarboush\PapyrusDocs\PapyrusGenerator;
use AhmedTarboush\PapyrusDocs\Exporters\OpenApiGenerator;
use AhmedTarboush\PapyrusDocs\Exporters\PostmanGenerator;

class PapyrusController extends Controller
{
    /**
     * Render the Papyrus Docs SPA.
     */
    public function index()
    {
        $papyrusConfig = [
            'title' => config('papyrus.title', 'Papyrus - Laravel API Docs'),
            'baseUrl' => config('papyrus.base_url', request()->getSchemeAndHttpHost()),
            'path' => config('papyrus.url', config('papyrus.path', 'papyrus-docs')),
            'headers' => config('papyrus.default_headers', []),
            'defaultResponses' => config('papyrus.default_responses', []),
            'groupByPatterns' => config('papyrus.group_by.uri_patterns', []),
            'debug' => config('papyrus.debug', false),
            'faviconUrl' => route('papyrus.favicon', ['file' => 'android-chrome-192x192.png']),
        ];

        return response()->view('papyrus::index', compact('papyrusConfig'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    /**
     * Return the Documentation Schema as JSON.
     *
     * When debug mode is enabled (config: papyrus.debug), the response
     * includes X-Papyrus-Debug-* headers with execution metadata.
     */
    public function schema(PapyrusGenerator $generator)
    {
        $startTime   = microtime(true);
        $startMemory = memory_get_usage(true);

        $schema = $generator->scan();

        $response = response()->json($schema);

        // Add no-cache headers to ensure schema is never cached
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');

        // Append debug metadata as headers if enabled
        if (config('papyrus.debug', false)) {
            $response->header('X-Papyrus-Debug-Time-Ms', (string) round((microtime(true) - $startTime) * 1000, 2));
            $response->header('X-Papyrus-Debug-Memory-Mb', (string) round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2));
            $response->header('X-Papyrus-Debug-PHP', PHP_VERSION);
            $response->header('X-Papyrus-Debug-Laravel', app()->version());
        }

        return $response;
    }

    /**
     * Serve static assets from the package.
     */
    public function assets($path)
    {
        $path = str_replace('..', '', $path); // Prevent traversal
        $file = __DIR__ . '/../../../dist/build/assets/' . $path;

        if (!file_exists($file)) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $this->getMimeType($file),
            'Cache-Control' => 'public, max-age=31536000',
        ];

        return response()->file($file, $headers);
    }

    /**
     * Export OpenAPI 3.0 JSON specification as a downloadable file.
     */
    public function exportOpenApi(PapyrusGenerator $generator)
    {
        $schema = $generator->scan();
        $spec = (new OpenApiGenerator())->generate($schema);

        return response()->streamDownload(function () use ($spec) {
            echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, 'openapi-spec.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Export Postman Collection v2.1 JSON as a downloadable file.
     */
    public function exportPostman(PapyrusGenerator $generator)
    {
        $schema = $generator->scan();
        $collection = (new PostmanGenerator())->generate($schema);

        return response()->streamDownload(function () use ($collection) {
            echo json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, 'postman-collection.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Serve favicon files from the package.
     */
    public function favicon($file = 'favicon.ico')
    {
        $file = str_replace('..', '', $file);
        $path = __DIR__ . '/../../../favicon/' . $file;

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => $this->getMimeType($path),
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    protected function getMimeType($filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return match ($extension) {
            'js' => 'application/javascript',
            'css' => 'text/css',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'ico' => 'image/x-icon',
            default => 'text/plain',
        };
    }
}
