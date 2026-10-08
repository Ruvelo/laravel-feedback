@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', 'Feedback')
@section('section', 'board')
@section('description', 'Suggest features and vote on what '.config('feedback.name').' builds next.')
@section('canonical', route('feedback.home'))
@if ($board->isFiltered()) @section('robots', 'noindex') @endif

@section('feedback')
    @php($statuses = \Ruvelo\Feedback\Support\Palette::statuses())
    <div class="fb-head">
        <div>
            <h1>Tell us what to build next</h1>
            <p class="fb-lede">Search for your idea and vote for it, or suggest something new. We read every request.</p>
        </div>
        @if (Route::has('feedback.requests.create'))
            <a class="fb-btn" href="{{ route('feedback.requests.create') }}">@include('feedback::partials.icon', ['name' => 'plus'])Suggest a feature</a>
        @endif
    </div>

    <div class="fb-cols fb-cols--left" data-feedback-board>
        <aside class="fb-side fb-side--filters" aria-label="Filters">
            <section>
                <h2 id="fb-filter-status">Status</h2>
                <ul class="fb-filter" aria-labelledby="fb-filter-status">
                    <li><a href="{{ route('feedback.home', $board->with(['status' => 'open'])) }}" data-filter="status" data-value="open" @if ($board->status === 'open') aria-current="true" @endif><span class="fb-dot fb-tone-accent"></span>Open<span class="fb-count">{{ $openCount }}</span></a></li>
                    @foreach ($statuses as $key => $label)
                        @php($tone = \Ruvelo\Feedback\Support\Palette::statusChip($key))
                        <li><a href="{{ route('feedback.home', $board->with(['status' => $key])) }}" data-filter="status" data-value="{{ $key }}" @if ($board->status === $key) aria-current="true" @endif><span class="fb-dot {{ $tone['class'] }}" @if ($tone['style']) style="{{ $tone['style'] }}" @endif></span>{{ $label }}<span class="fb-count">{{ $counts[$key] ?? 0 }}</span></a></li>
                    @endforeach
                    <li><a href="{{ route('feedback.home', $board->with(['status' => 'all'])) }}" data-filter="status" data-value="all" @if ($board->status === 'all') aria-current="true" @endif><span class="fb-dot fb-tone-neutral"></span>Everything<span class="fb-count">{{ $allCount }}</span></a></li>
                </ul>
            </section>

            @if ($categories->isNotEmpty())
                <section>
                    <h2 id="fb-filter-category">Category</h2>
                    <ul class="fb-filter" aria-labelledby="fb-filter-category">
                        <li><a href="{{ route('feedback.home', $board->with(['category' => null])) }}" data-filter="category" data-value="" @if ($board->category === null) aria-current="true" @endif>All categories</a></li>
                        @foreach ($categories as $category)
                            <li><a href="{{ route('feedback.home', $board->with(['category' => $category->slug])) }}" data-filter="category" data-value="{{ $category->slug }}" @if ($board->category === $category->slug) aria-current="true" @endif>{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($tags->isNotEmpty())
                <section>
                    <h2>Tags</h2>
                    <div class="fb-tagcloud">
                        @foreach ($tags as $tag)
                            <a class="fb-chip {{ $board->tag === $tag->slug ? '' : 'fb-chip--outline' }}" href="{{ route('feedback.home', $board->with(['tag' => $board->tag === $tag->slug ? null : $tag->slug])) }}" data-filter="tag" data-value="{{ $tag->slug }}" @if ($board->tag === $tag->slug) aria-current="true" @endif>{{ $tag->name }}</a>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>

        <div>
            <div class="fb-toolbar">
                <form class="fb-search" action="{{ route('feedback.home') }}" method="get" role="search">
                    <label class="fb-sr" for="fb-q">Search requests</label>
                    <input id="fb-q" type="search" name="q" value="{{ $board->q }}" placeholder="Search requests…" maxlength="200" autocomplete="off" data-feedback-search>
                    @foreach ($board->with(['q' => null]) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                </form>
                <nav class="fb-seg" aria-label="Sort">
                    @foreach (\Ruvelo\Feedback\Support\Board::SORTS as $key => $label)
                        <a href="{{ route('feedback.home', $board->with(['sort' => $key])) }}" data-filter="sort" data-value="{{ $key }}" @if ($board->sort === $key) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>

            @if ($board->isFiltered())
                <p class="fb-active" data-feedback-active>
                    {{ number_format($requests->total()) }} {{ \Illuminate\Support\Str::plural('request', $requests->total()) }}
                    @if ($board->q !== '') matching “{{ $board->q }}”@endif
                    · <a href="{{ route('feedback.home') }}">Clear filters</a>
                </p>
            @endif

            @if ($requests->isEmpty())
                <p class="fb-empty" data-feedback-empty>
                    @if ($board->isFiltered())
                        <strong>No requests match.</strong>
                        @if (Route::has('feedback.requests.create'))
                            Can’t find it? <a href="{{ route('feedback.requests.create', $board->q !== '' ? ['title' => $board->q] : []) }}">Suggest it</a>.
                        @endif
                    @else
                        <strong>No requests yet.</strong>
                        @if (Route::has('feedback.requests.create'))
                            <a href="{{ route('feedback.requests.create') }}">Suggest the first one</a>.
                        @endif
                    @endif
                </p>
            @else
                <ol class="fb-list" data-feedback-list>
                    @foreach ($requests as $feature)
                        @include('feedback::partials.card', ['feature' => $feature])
                    @endforeach
                </ol>
                <p class="fb-empty" data-feedback-empty hidden><strong>No requests match.</strong> Try another filter.</p>
                {{ $requests->links() }}
            @endif
        </div>
    </div>
@endsection
