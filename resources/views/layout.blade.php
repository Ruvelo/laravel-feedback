<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') · @endif{{ config('feedback.name') }}</title>
    @include('feedback::partials.meta')
    @include('feedback::partials.styles')
    <style>
        body.fb { margin: 0; min-height: 100vh; }
        body.fb::before { content: ""; position: fixed; inset: 0 0 auto; height: 3px; z-index: 40; background: linear-gradient(90deg, var(--feedback-accent), var(--feedback-accent-2)); }
    </style>
    @stack('feedback-head')
</head>
<body class="fb">
    @include('feedback::partials.page')
</body>
</html>
