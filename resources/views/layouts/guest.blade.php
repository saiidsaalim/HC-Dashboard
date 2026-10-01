<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>HCM | Login</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|space-grotesk:500,600,700&display=swap"
        rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-white antialiased">
    <div
        class="relative isolate flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-8 sm:px-6">
        <div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-600/30 blur-3xl">
        </div>
        <div class="pointer-events-none absolute -right-24 top-1/3 h-96 w-96 rounded-full bg-sky-500/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-40 left-1/3 h-96 w-96 rounded-full bg-amber-400/10 blur-3xl">
        </div>
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(125,211,252,0.12),transparent_38%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(30,27,75,0.92),rgba(8,47,73,0.95))]">
        </div>
        <div class="relative z-10 w-full sm:max-w-md">
            <div class="mb-6 flex justify-center">
                <a href="/"
                    class="rounded-2xl border border-white/20 bg-white/10 p-3 shadow-2xl shadow-sky-950/30 backdrop-blur-md">
                    <img src="{{ asset('image/SM.png') }}" alt="Logo" class="h-20 w-20 object-contain" />
                </a>
            </div>
            <div
                class="overflow-hidden rounded-3xl border border-white/20 bg-white/10 px-6 py-7 shadow-2xl shadow-slate-950/40 backdrop-blur-2xl sm:px-8">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>

</html>
