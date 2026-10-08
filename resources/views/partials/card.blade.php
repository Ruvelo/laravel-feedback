<li class="fb-card {{ $feature->isPinned() ? 'is-pinned' : '' }}"
    data-feedback-card
    data-status="{{ $feature->status }}"
    data-category="{{ $feature->category?->slug }}"
    data-tags="{{ $feature->tags->pluck('slug')->implode(' ') }}"
    data-votes="{{ $feature->votes_count }}"
    data-created="{{ $feature->created_at->getTimestamp() }}"
    data-pinned="{{ $feature->isPinned() ? 1 : 0 }}"
    data-search="{{ mb_strtolower($feature->title.' '.$feature->body) }}">
    @include('feedback::partials.vote', ['feature' => $feature, 'voted' => $feature->hasVoter(auth()->user() instanceof \Illuminate\Database\Eloquent\Model ? auth()->user() : null)])
    <div class="fb-card-body">
        <h2 class="fb-card-title"><a href="{{ $feature->url() }}">{{ $feature->title }}</a></h2>
        @if ($feature->body)
            <p class="fb-card-text">{{ $feature->excerpt(200) }}</p>
        @endif
        <div class="fb-meta">
            @include('feedback::partials.status', ['status' => $feature->status])
            @unless ($feature->isApproved())
                <span class="fb-chip fb-tone-warning">Waiting for approval</span>
            @endunless
            @if ($feature->isPinned())
                <span>@include('feedback::partials.icon', ['name' => 'pin'])Pinned</span>
            @endif
            @if ($feature->category)
                <span>{{ $feature->category->name }}</span>
            @endif
            @if (\Ruvelo\Feedback\Feedback::commentsDriver() !== null)
                <span>@include('feedback::partials.icon', ['name' => 'comment']){{ $feature->comments_count }}<span class="fb-sr"> {{ \Illuminate\Support\Str::plural('comment', $feature->comments_count) }}</span></span>
            @endif
        </div>
    </div>
</li>
