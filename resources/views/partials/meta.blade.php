@hasSection('description')
    <meta name="description" content="@yield('description')">
@endif
@hasSection('canonical')
    <link rel="canonical" href="@yield('canonical')">
@endif
@hasSection('robots')
    <meta name="robots" content="@yield('robots')">
@endif
@if (\Ruvelo\Feedback\Feedback::enabled('changelog') && Route::has('feedback.changelog.atom'))
    <link rel="alternate" type="application/atom+xml" title="{{ config('feedback.name') }} changelog" href="{{ route('feedback.changelog.atom') }}">
@endif
