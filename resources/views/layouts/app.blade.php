<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#173f35">
    <title>@yield('title', config('app.name', 'Saflivo'))</title>
    <meta name="description" content="@yield('description', 'A connected workspace for membership, events, and organization operations.')">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-stone-50 font-sans text-slate-900 antialiased">
    @yield('content')
    @stack('scripts')
</body>
</html>
