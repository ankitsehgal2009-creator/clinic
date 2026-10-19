<!doctype html>
@php($u = auth()->user())
<html lang="en" data-theme="{{ $u?->pref('theme') ?? 'teal' }}" data-mode="{{ $u?->pref('mode') ?? 'auto' }}" data-menu="{{ $u?->pref('menu') ?? 'dark' }}" data-size="{{ $u?->pref('size') ?? 'normal' }}" data-corners="{{ $u?->pref('corners') ?? 'round' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'SmileDesk')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
</head>
<body class="@yield('bodyclass')">
@yield('body')
<script src="{{ asset('js/app.js') }}?v=3"></script>
@stack('scripts')
</body>
</html>
