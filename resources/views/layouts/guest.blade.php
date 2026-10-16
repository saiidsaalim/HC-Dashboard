@props(['title' => 'Login'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>HCM | {{ $title }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|space-grotesk:500,600,700&display=swap"
        rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body data-guest-shell class="font-sans text-white antialiased">
    <main
        class="relative isolate flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-8 sm:px-6 sm:py-12">
        <div aria-hidden="true" class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-600/20 blur-3xl">
        </div>
        <div aria-hidden="true" class="pointer-events-none absolute -right-24 top-1/3 h-96 w-96 rounded-full bg-sky-500/20 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 left-1/3 h-96 w-96 rounded-full bg-amber-400/10 blur-3xl">
        </div>
        <div aria-hidden="true"
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(125,211,252,0.12),transparent_38%),linear-gradient(135deg,rgba(2,6,23,0.98),rgba(30,27,75,0.92),rgba(8,47,73,0.95))]">
        </div>
        <div class="relative z-10 w-full sm:max-w-lg">
            <div class="mb-6 flex justify-center sm:mb-7">
                <a href="/"
                    class="flex h-28 w-28 items-center justify-center rounded-3xl border border-white/20 bg-white/10 p-4 shadow-2xl shadow-sky-950/30 backdrop-blur-md transition hover:-translate-y-0.5 hover:border-white/30 focus:outline-none focus:ring-2 focus:ring-amber-300 sm:h-[124px] sm:w-[124px] sm:p-5">
                    <img src="{{ asset('image/SM.png') }}" alt="Semen Tonasa" class="h-full w-full object-contain" />
                </a>
            </div>
            <div
                class="overflow-hidden rounded-[1.75rem] border border-white/20 bg-white/10 px-5 py-6 shadow-2xl shadow-slate-950/40 backdrop-blur-2xl sm:rounded-[2rem] sm:px-9 sm:py-8">
                {{ $slot }}
            </div>
            <p class="mt-5 text-center text-xs font-medium text-slate-500">HC Dashboard &middot; Semen Tonasa</p>
        </div>
    </main>
</body>

</html>
