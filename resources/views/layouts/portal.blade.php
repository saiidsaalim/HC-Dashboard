<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('hc_portal.company') . ' · ' . config('hc_portal.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    @stack('head')
</head>
<body class="portal">
    @include('portal.partials.icons')
    @yield('content')
    <script src="{{ asset('js/portal.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
