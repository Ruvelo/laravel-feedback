@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', 'Changelog')
@section('section', 'changelog')
@section('description', 'New features, improvements and fixes in '.config('feedback.name').'.')
@section('canonical', route('feedback.changelog'))
@if ($label) @section('robots', 'noindex') @endif

@section('feedback')
    <div class="fb-head">
        <div>
            <h1>What’s new</h1>
            <p class="fb-lede">New features, improvements and fixes in {{ config('feedback.name') }}, newest first.</p>
        </div>
        <div class="fb-feeds">
            <a class="fb-btn fb-btn--quiet fb-btn--small" href="{{ route('feedback.changelog.rss') }}">@include('feedback::partials.icon', ['name' => 'rss'])RSS</a>
            <a class="fb-btn fb-btn--quiet fb-btn--small" href="{{ route('feedback.changelog.atom') }}">Atom</a>
            @if ($canManage)
                <a class="fb-btn fb-btn--small" href="{{ route('feedback.manage.entries.create') }}">Write an update</a>
            @endif
        </div>
    </div>

    <nav class="fb-seg" aria-label="Filter by label" style="margin-bottom: 1.5rem">
        <a href="{{ route('feedback.changelog') }}" @if ($label === null) aria-current="true" @endif>Everything</a>
        @foreach (\Ruvelo\Feedback\Support\Palette::changelogLabels() as $key => $text)
            <a href="{{ route('feedback.changelog', ['label' => $key]) }}" @if ($label === $key) aria-current="true" @endif>{{ $text }}</a>
        @endforeach
    </nav>

    @if ($drafts->isNotEmpty())
        <p class="fb-flash">{{ $drafts->count() }} {{ \Illuminate\Support\Str::plural('entry', $drafts->count()) }} not live yet:
            @foreach ($drafts as $draft)<a href="{{ route('feedback.manage.entries.edit', $draft) }}">{{ $draft->title }}</a>@if (! $loop->last), @endif @endforeach
        </p>
    @endif

    @if ($entries->isEmpty())
        <p class="fb-empty"><strong>Nothing here yet.</strong> Updates will show up here as they ship.</p>
    @else
        <ol class="fb-log">
            @foreach ($entries as $entry)
                <li id="entry-{{ $entry->slug }}">
                    <div class="fb-log-date">
                        <time datetime="{{ $entry->published_at?->toIso8601String() }}">{{ $entry->published_at?->toFormattedDateString() }}</time>
                        @if ($since === null || ($entry->published_at && $entry->published_at->greaterThan($since)))
                            <span class="fb-new">New</span>
                        @endif
                    </div>
                    <article>
                        @include('feedback::partials.labels', ['entry' => $entry, 'link' => true])
                        <h2><a href="{{ $entry->url() }}">{{ $entry->title }}</a></h2>
                        @if ($entry->cover_url)
                            <img class="fb-cover" src="{{ $entry->cover_url }}" alt="" loading="lazy">
                        @endif
                        <div class="fb-prose">{!! $entry->html() !!}</div>
                        @include('feedback::partials.asked', ['entry' => $entry])
                    </article>
                </li>
            @endforeach
        </ol>
        {{ $entries->links() }}
    @endif
@endsection
