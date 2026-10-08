@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', $feature->title)
@section('section', 'board')
@section('description', $feature->excerpt(160) ?: $feature->title)
@section('canonical', $feature->url())
@unless ($feature->isApproved()) @section('robots', 'noindex') @endunless

@section('feedback')
    @php
        $viewer = auth()->user() instanceof \Illuminate\Database\Eloquent\Model ? auth()->user() : null;
        $latest = $feature->statusChanges->filter(fn ($change) => $change->note !== null)->last();
        $entry = $feature->entry !== null && ($feature->entry->isPublished() || $manager) ? $feature->entry : null;
    @endphp

    <ol class="fb-crumbs" aria-label="Breadcrumbs">
        <li><a href="{{ route(\Ruvelo\Feedback\Feedback::enabled('board') ? 'feedback.home' : (\Ruvelo\Feedback\Feedback::enabled('roadmap') ? 'feedback.roadmap' : 'feedback.home')) }}">{{ \Ruvelo\Feedback\Feedback::enabled('board') ? 'Feedback' : 'Roadmap' }}</a></li>
        @if ($feature->category)
            <li>@if (\Ruvelo\Feedback\Feedback::enabled('board'))<a href="{{ route('feedback.home', ['category' => $feature->category->slug, 'status' => 'all']) }}">{{ $feature->category->name }}</a>@else{{ $feature->category->name }}@endif</li>
        @endif
        <li aria-current="page">Request #{{ $feature->id }}</li>
    </ol>

    <div class="fb-cols">
        <article>
            <div class="fb-request-head">
                @include('feedback::partials.vote', ['feature' => $feature, 'voted' => $voted, 'big' => true])
                <div>
                    <div class="fb-meta" style="margin-top: 0">
                        @include('feedback::partials.status', ['status' => $feature->status])
                        @unless ($feature->isApproved())
                            <span class="fb-chip fb-tone-warning">Waiting for approval</span>
                        @endunless
                        @if ($feature->isPinned())
                            <span>@include('feedback::partials.icon', ['name' => 'pin'])Pinned</span>
                        @endif
                    </div>
                    <h1>{{ $feature->title }}</h1>
                    <p class="fb-byline">
                        @include('feedback::partials.avatar', ['name' => $feature->authorName(), 'class' => 'fb-avatar--small'])
                        <span><strong style="color: var(--feedback-ink); font-weight: 500">{{ $feature->authorName() }}</strong> asked <time datetime="{{ $feature->created_at->toIso8601String() }}" title="{{ $feature->created_at->toDayDateTimeString() }}">{{ $feature->created_at->diffForHumans() }}</time></span>
                    </p>
                </div>
            </div>

            @if ($feature->body)
                <div class="fb-prose fb-request-body">{!! $feature->html() !!}</div>
            @endif

            @if ($feature->isShipped())
                <div class="fb-shipped">
                    <span class="fb-shipped-icon">@include('feedback::partials.icon', ['name' => 'check'])</span>
                    <p>
                        <strong>Shipped{{ $feature->status_changed_at ? ' on '.$feature->status_changed_at->toFormattedDateString() : '' }}.</strong>
                        @if ($entry)
                            Read about it in the changelog: <a href="{{ $entry->url() }}">{{ $entry->title }}</a>
                        @else
                            Thanks to everyone who asked for it.
                        @endif
                    </p>
                </div>
            @endif

            @if ($latest !== null && $latest->note)
                <section class="fb-update" aria-label="Latest update from the team">
                    <div class="fb-update-head">
                        @include('feedback::partials.avatar', ['name' => $latest->changerName(), 'class' => 'fb-avatar--small'])
                        <strong>{{ $latest->changerName() }}</strong>
                        @if ($latest->from === $latest->to)
                            <span>posted an update</span>
                        @else
                            <span>marked this</span>
                            @include('feedback::partials.status', ['status' => $latest->to])
                        @endif
                        <time class="fb-muted" datetime="{{ $latest->created_at?->toIso8601String() }}">{{ $latest->created_at?->diffForHumans() }}</time>
                    </div>
                    <div class="fb-prose">{!! $latest->noteHtml() !!}</div>
                </section>
            @endif

            @if ($feature->duplicates->isNotEmpty())
                <section class="fb-section" aria-labelledby="fb-dupes">
                    <h2 id="fb-dupes">Also asked as <small>merged here, with their votes</small></h2>
                    <ul class="fb-history">
                        @foreach ($feature->duplicates as $duplicate)
                            <li><span class="fb-dot fb-tone-neutral"></span><div><strong style="color: var(--feedback-ink); font-weight: 500">{{ $duplicate->title }}</strong>@if ($duplicate->body)<div class="fb-prose">{{ $duplicate->excerpt(240) }}</div>@endif</div></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($driver === 'ruvelo')
                <section class="fb-section" id="comments" aria-label="Comments">
                    @include('feedback::partials.ruvelo-comments', ['feature' => $feature])
                </section>
            @elseif ($driver === 'builtin')
                <section class="fb-section" id="comments" aria-labelledby="fb-comments-title">
                    <h2 id="fb-comments-title">Comments <small>{{ $feature->comments_count }}</small></h2>

                    @if ($viewer !== null && Route::has('feedback.comments.store') && ! $feature->isMerged())
                        @if (\Ruvelo\Feedback\Feedback::allows('feedback-comment', auth()->user(), $feature))
                            @include('feedback::partials.comment-form', ['parent' => null])
                        @endif
                    @elseif ($viewer === null)
                        <p class="fb-signin">
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}">Sign in</a> to vote and comment.
                            @else
                                Sign in to vote and comment.
                            @endif
                        </p>
                    @endif

                    @if ($comments->isEmpty())
                        <p class="fb-muted" style="font-size: .9rem; margin-top: 1rem">No comments yet. Add a use case or an example: it helps the team understand what you need.</p>
                    @else
                        <ol class="fb-comments" style="margin-top: 1rem">
                            @foreach ($comments as $comment)
                                @include('feedback::partials.comment', ['comment' => $comment, 'feature' => $feature, 'replyTo' => $replyTo])
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endif
        </article>

        <aside class="fb-side">
            <div class="fb-panel">
                <dl class="fb-facts">
                    <div>
                        <dt>Voters</dt>
                        <dd>
                            <div class="fb-voters">
                                @if ($voters->isNotEmpty())
                                    <span class="fb-avatars">
                                        @foreach ($voters as $vote)
                                            <span title="{{ \Ruvelo\Feedback\Support\People::name($vote->voter) }}">@include('feedback::partials.avatar', ['name' => \Ruvelo\Feedback\Support\People::name($vote->voter), 'class' => 'fb-avatar--small'])</span>
                                        @endforeach
                                    </span>
                                @endif
                                <p><strong>{{ number_format($feature->votes_count) }}</strong> {{ \Illuminate\Support\Str::plural('vote', $feature->votes_count) }}@if ($voted), including yours @endif</p>
                            </div>
                        </dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>@include('feedback::partials.status', ['status' => $feature->status])</dd>
                    </div>
                    @if ($feature->category)
                        <div>
                            <dt>Category</dt>
                            <dd>{{ $feature->category->name }}</dd>
                        </div>
                    @endif
                    @if ($feature->tags->isNotEmpty())
                        <div>
                            <dt>Tags</dt>
                            <dd>@foreach ($feature->tags as $tag)<span class="fb-chip fb-chip--outline">{{ $tag->name }}</span>@endforeach</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($feature->statusChanges->isNotEmpty())
                <div>
                    <h2>History</h2>
                    <ol class="fb-history">
                        @foreach ($feature->statusChanges->reverse() as $change)
                            @php($tone = \Ruvelo\Feedback\Support\Palette::statusChip($change->to))
                            <li>
                                <span class="fb-dot {{ $tone['class'] }}" @if ($tone['style']) style="{{ $tone['style'] }}" @endif></span>
                                <div>
                                    {{ $change->from === $change->to ? 'Update' : $change->label() }}
                                    <time datetime="{{ $change->created_at?->toIso8601String() }}">· {{ $change->created_at?->toFormattedDateString() }}</time>
                                </div>
                            </li>
                        @endforeach
                        <li>
                            <span class="fb-dot fb-tone-neutral"></span>
                            <div>Posted <time datetime="{{ $feature->created_at->toIso8601String() }}">· {{ $feature->created_at->toFormattedDateString() }}</time></div>
                        </li>
                    </ol>
                </div>
            @endif

            @if ($manager)
                @include('feedback::partials.manage-panel')
            @endif
        </aside>
    </div>
@endsection
