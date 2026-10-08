@php($section = trim($__env->yieldContent('section')))
<div class="fb-root" id="feedback">
    <header class="fb-top">
        <div class="fb-bar">
            <a class="fb-brand" href="{{ route('feedback.home') }}"><span class="fb-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr((string) config('feedback.name'), 0, 1)) }}</span>{{ config('feedback.name') }}</a>
            <nav aria-label="Account">
                @if (config('feedback.app.url') && config('feedback.app.label'))
                    <a href="{{ config('feedback.app.url') }}">{{ config('feedback.app.label') }}</a>
                @endif
                @if ($canManage)
                    @if (request()->routeIs('feedback.manage.*'))
                        <a href="{{ route('feedback.home') }}">View the public site</a>
                    @else
                        <a class="fb-btn fb-btn--small" href="{{ route('feedback.manage.requests') }}">Manage</a>
                    @endif
                @elseif (auth()->guest() && Route::has('login'))
                    <a href="{{ route('login') }}">Sign in</a>
                @endif
            </nav>
        </div>
        <nav class="fb-tabs" aria-label="Sections">
            @if (\Ruvelo\Feedback\Feedback::enabled('board'))
                <a href="{{ route('feedback.home') }}" @if ($section === 'board') aria-current="page" @endif>@include('feedback::partials.icon', ['name' => 'bulb'])Feedback</a>
            @endif
            @if (\Ruvelo\Feedback\Feedback::enabled('roadmap'))
                <a href="{{ route('feedback.roadmap') }}" @if ($section === 'roadmap') aria-current="page" @endif>@include('feedback::partials.icon', ['name' => 'map'])Roadmap</a>
            @endif
            @if (\Ruvelo\Feedback\Feedback::enabled('changelog'))
                <a href="{{ route('feedback.changelog') }}" @if ($section === 'changelog') aria-current="page" @endif>@include('feedback::partials.icon', ['name' => 'sparkle'])Changelog</a>
            @endif
        </nav>
    </header>

    <main class="fb-page" id="feedback-main">
        @include('feedback::partials.flash')
        @yield('feedback')
    </main>

    <footer class="fb-foot">
        <div class="fb-foot-in">
            <span>© {{ date('Y') }} {{ config('feedback.name') }}</span>
            <span>Powered by <a href="https://github.com/Ruvelo/laravel-feedback">Laravel Feedback</a> by Ruvelo</span>
        </div>
    </footer>

    @include('feedback::partials.scripts')
</div>
