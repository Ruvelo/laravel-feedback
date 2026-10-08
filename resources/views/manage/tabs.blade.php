<div class="fb-head" style="margin-bottom: 1rem">
    <div>
        <h1>Manage feedback</h1>
    </div>
</div>
<nav class="fb-admin-tabs" aria-label="Manage">
    <a href="{{ route('feedback.manage.requests') }}" @if (request()->routeIs('feedback.manage.requests*')) aria-current="page" @endif>Requests @if (($pendingCount = \Ruvelo\Feedback\Models\FeatureRequest::query()->whereNull('merged_into_id')->whereNull('approved_at')->count()) > 0)<span class="fb-chip fb-tone-warning">{{ $pendingCount }} waiting</span>@endif</a>
    <a href="{{ route('feedback.manage.entries') }}" @if (request()->routeIs('feedback.manage.entries*')) aria-current="page" @endif>Changelog</a>
    <a href="{{ route('feedback.manage.taxonomy') }}" @if (request()->routeIs('feedback.manage.taxonomy')) aria-current="page" @endif>Categories and tags</a>
    <a href="{{ route('feedback.manage.stats') }}" @if (request()->routeIs('feedback.manage.stats')) aria-current="page" @endif>Stats</a>
</nav>
