<!DOCTYPE html>
<html lang="en" class="m-0 p-0 overflow-hidden">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('papyrus.title', 'Papyrus - Laravel API Docs') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ route('papyrus.favicon', ['file' => 'favicon.ico']) }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ route('papyrus.favicon', ['file' => 'favicon-32x32.png']) }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ route('papyrus.favicon', ['file' => 'favicon-16x16.png']) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">

    {{-- Typography: system font stacks (no external CDN dependency) --}}
    <style>
        :root {
            --font-sans: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-brand: 'Playfair Display', 'Merriweather', 'Georgia', 'Cambria', 'Times New Roman', serif;
            --font-mono: 'Fira Code', 'JetBrains Mono', 'Cascadia Code', ui-monospace, 'SF Mono', 'Consolas', monospace;
        }

        .font-brand {
            font-family: var(--font-brand) !important;
        }

        .font-mono {
            font-family: var(--font-mono) !important;
        }
    </style>

    {{-- Config Injection --}}
    <script>
        window.PapyrusConfig = {
            title: @json(config('papyrus.title', 'Papyrus - Laravel API Docs')),
            headers: @json(config('papyrus.default_headers', [])),
            defaultResponses: @json(config('papyrus.default_responses', [])),
            groupByPatterns: @json(config('papyrus.group_by.uri_patterns', [])),
            debug: @json(config('papyrus.debug', false)),
            faviconUrl: "{{ route('papyrus.favicon', ['file' => 'android-chrome-192x192.png']) }}"
        };
    </script>

    @php
    $hotFile = __DIR__ . '/../../dist/hot';
    $packageAssetsPath = 'vendor/papyrus-docs';
    @endphp

    @if(file_exists($hotFile))
    {{-- HOT MODULE REPLACEMENT (HMR) --}}
    @php
    $viteHost = trim(file_get_contents($hotFile));
    @endphp
    <script type="module" src="{{ $viteHost }}/@vite/client"></script>
    <link rel="stylesheet" href="{{ $viteHost }}/resources/css/app.css">
    <script type="module" src="{{ $viteHost }}/resources/js/App.jsx"></script>
    @else
    {{-- CONTROLLER BASED ASSET SERVING --}}
    @php
    $manifestPath = __DIR__ . '/../../dist/build/.vite/manifest.json';
    $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];

    $cssFile = $manifest['resources/css/app.css']['file'] ?? 'assets/app.css';
    $jsFile = $manifest['resources/js/App.jsx']['file'] ?? 'assets/App.js';

    // Remove 'assets/' prefix since our route handles it, or adjust logic
    // The manifest returns "assets/app.css". Our file system has "dist/build/assets/app.css".
    // Our route is /assets/{path}. Controller looks in dist/build/assets/{path}.
    // So we need to strip "assets/" from the manifest config if the controller expects just the filename,
    // OR make the controller look in "dist/build/" and pass "assets/app.css".

    // Let's go with: Controller serves "dist/build/assets/{filename}".
    // Manifest gives "assets/filename.css".
    // So we take basename of the manifest file.
    $cssFile = basename($cssFile);
    $jsFile = basename($jsFile);

    $cssPath = public_path($packageAssetsPath . '/' . $cssFile);
    $jsPath = public_path($packageAssetsPath . '/' . $jsFile);
    $cssV = file_exists($cssPath) ? filemtime($cssPath) : time();
    $jsV = file_exists($jsPath) ? filemtime($jsPath) : time();
    @endphp
    <link rel="stylesheet" href="{{ route('papyrus.assets', ['path' => $cssFile]) }}?v={{ $cssV }}">
    <script type="module" src="{{ route('papyrus.assets', ['path' => $jsFile]) }}?v={{ $jsV }}"></script>
    @endif
</head>

<body class="m-0 p-0 overflow-hidden antialiased">
    <div id="papyrus-app"></div>
</body>

</html>