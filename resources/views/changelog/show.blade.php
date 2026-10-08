@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', $entry->title)
@section('section', 'changelog')
@section('description', $entry->excerpt(160))
@section('canonical', $entry->url())
@unless ($entry->isPublished()) @section('robots', 'noindex') @endunless
@if ($entry->cover_url)
    @push('feedback-head')
        <meta property="og:image" content="{{ $entry->cover_url }}">
    @endpush
@endif

@section('feedback')
    <ol class="fb-crumbs" aria-label="Breadcrumbs">
        <li><a href="{{ route('feedback.changelog') }}">Changelog</a></li>
        <li aria-current="page">{{ \Illuminate\Support\Str::limit($entry->title, 60) }}</li>
    </ol>

    <article style="max-width: 46rem">
        @unless ($entry->isPublished())
            <p class="fb-flash">{{ $entry->isScheduled() ? 'Scheduled for '.$entry->published_at?->toDayDateTimeString().'.' : 'Draft.' }} Only managers can see this. <a href="{{ route('feedback.manage.entries.edit', $entry) }}">Edit it</a></p>
        @endunless
        @include('feedback::partials.labels', ['entry' => $entry, 'link' => true])
        <h1 style="font-size: clamp(1.6rem, 3.4vw, 2.25rem); letter-spacing: -.035em; margin: 0 0 .5rem">{{ $entry->title }}</h1>
        <p class="fb-byline" style="margin: 0 0 1.75rem">
            @if ($entry->published_at)<time datetime="{{ $entry->published_at->toIso8601String() }}">{{ $entry->published_at->toFormattedDateString() }}</time> · @endif
            {{ $entry->authorName() }}
            @if ($canManage) · <a href="{{ route('feedback.manage.entries.edit', $entry) }}">Edit</a>@endif
        </p>
        @if ($entry->cover_url)
            <img class="fb-cover" src="{{ $entry->cover_url }}" alt="">
        @endif
        <div class="fb-prose">{!! $entry->html() !!}</div>
        @include('feedback::partials.asked', ['entry' => $entry])

        <nav class="fb-pager" aria-label="More updates">
            @if ($older)
                <a href="{{ $older->url() }}" rel="prev"><small>Older</small>{{ $older->title }}</a>
            @endif
            @if ($newer)
                <a href="{{ $newer->url() }}" rel="next"><small>Newer</small>{{ $newer->title }}</a>
            @endif
        </nav>
    </article>
@endsection
