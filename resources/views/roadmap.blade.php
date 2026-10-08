@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', 'Roadmap')
@section('section', 'roadmap')
@section('description', 'What '.config('feedback.name').' is planning, building and has shipped.')
@section('canonical', route('feedback.roadmap'))

@section('feedback')
    <div class="fb-head">
        <div>
            <h1>Roadmap</h1>
            <p class="fb-lede">What we’re planning, what we’re building now, and what just shipped. Vote on the board to move things up.</p>
        </div>
        @if (\Ruvelo\Feedback\Feedback::enabled('board'))
            <a class="fb-btn fb-btn--quiet" href="{{ route('feedback.home') }}">Browse all requests</a>
        @endif
    </div>

    <div class="fb-roadmap" style="--fb-columns: {{ max(1, count($columns)) }}">
        @foreach ($columns as $status => $requests)
            @php($tone = \Ruvelo\Feedback\Support\Palette::statusChip($status))
            <section class="fb-column {{ $tone['class'] }}" @if ($tone['style']) style="{{ $tone['style'] }}" @endif aria-labelledby="fb-col-{{ $status }}">
                <h2 id="fb-col-{{ $status }}"><span class="fb-dot"></span>{{ \Ruvelo\Feedback\Support\Palette::statusLabel($status) }}<span class="fb-count">{{ $requests->count() }}</span></h2>
                @if ($requests->isEmpty())
                    <p class="fb-empty">Nothing here yet.</p>
                @else
                    <ol>
                        @foreach ($requests as $feature)
                            @php($voted = $feature->hasVoter(auth()->user() instanceof \Illuminate\Database\Eloquent\Model ? auth()->user() : null))
                            <li class="fb-rm-card">
                                <div>
                                    <a href="{{ $feature->url() }}">{{ $feature->title }}</a>
                                    <div class="fb-meta">
                                        @if ($feature->category)<span>{{ $feature->category->name }}</span>@endif
                                        @if ($status === 'shipped' && $feature->entry && $feature->entry->isPublished())
                                            <span>In <em style="font-style: normal; color: var(--feedback-text-2)">{{ \Illuminate\Support\Str::limit($feature->entry->title, 40) }}</em></span>
                                        @elseif ($status === 'shipped' && $feature->status_changed_at)
                                            <span>{{ $feature->status_changed_at->toFormattedDateString() }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="fb-rm-votes {{ $voted ? 'is-voted' : '' }}" aria-label="{{ $feature->votes_count }} {{ \Illuminate\Support\Str::plural('vote', $feature->votes_count) }}{{ $voted ? ', including yours' : '' }}">@include('feedback::partials.icon', ['name' => 'up']){{ $feature->votes_count }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endforeach
    </div>
@endsection
