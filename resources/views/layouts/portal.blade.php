<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal Dept. of Human Capital &amp; GRC SIG">
    <title>Portal | Dept. of Human Capital &amp; GRC</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|space-grotesk:500,600,700&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>

<body class="overflow-x-clip bg-slate-100 font-['DM_Sans',ui-sans-serif,system-ui,sans-serif] text-slate-900 antialiased">
    @yield('content')
</body>
</html>
