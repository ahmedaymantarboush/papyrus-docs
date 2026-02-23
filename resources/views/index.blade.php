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
        window.PapyrusConfig = @json($papyrusConfig);
    </script>

    @php
    $hotFile = __DIR__ . '/../../dist/hot';
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
    {{-- CONTROLLER BASED ASSET SERVING (Fallback if not published) --}}
    @php
    $manifestPath = __DIR__ . '/../../dist/build/.vite/manifest.json';
    $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : [];
    $cssFile = basename($manifest['resources/css/app.css']['file'] ?? 'app.css');
    $jsFile = basename($manifest['resources/js/App.jsx']['file'] ?? 'App.js');
    $cssPath = __DIR__ . '/../../dist/build/assets/' . $cssFile;
    $jsPath = __DIR__ . '/../../dist/build/assets/' . $jsFile;


    // $cssV = file_exists($cssPath) ? filemtime($cssPath) : time();
    // $jsV = file_exists($jsPath) ? filemtime($jsPath) : time();

    $cssV = time();
    $jsV = time();
    @endphp
    <link rel="stylesheet" href="{{ route('papyrus.assets', ['path' => $cssFile]) }}?v={{ $cssV }}">
    <script type="module" src="{{ route('papyrus.assets', ['path' => $jsFile]) }}?v={{ $jsV }}"></script>
    @endif
</head>

<body class="m-0 p-0 overflow-hidden antialiased">
    <div id="papyrus-app"></div>
</body>

</html>