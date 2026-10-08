{{-- The vote button: a form for signed-in people, a sign-in link for guests, a plain count when voting is closed. --}}
@php
    $big = ! empty($big);
    $count = number_format($feature->votes_count);
    $user = auth()->user();
    $canVote = Route::has('feedback.requests.vote');
@endphp
@if ($feature->isLocked() || ! $canVote)
    <span class="fb-vote-btn is-locked {{ $big ? 'fb-vote-btn--big' : '' }}" role="img" aria-label="{{ $count }} {{ \Illuminate\Support\Str::plural('vote', $feature->votes_count) }}{{ $feature->isLocked() ? ', voting is closed' : '' }}">@include('feedback::partials.icon', ['name' => 'up'])<span>{{ $count }}</span></span>
@elseif ($user === null)
    @if (Route::has('login'))
        <a class="fb-vote-btn {{ $big ? 'fb-vote-btn--big' : '' }}" href="{{ route('login') }}" aria-label="Sign in to vote. {{ $count }} {{ \Illuminate\Support\Str::plural('vote', $feature->votes_count) }} so far">@include('feedback::partials.icon', ['name' => 'up'])<span>{{ $count }}</span></a>
    @else
        <span class="fb-vote-btn is-locked {{ $big ? 'fb-vote-btn--big' : '' }}" role="img" title="Sign in to vote" aria-label="{{ $count }} {{ \Illuminate\Support\Str::plural('vote', $feature->votes_count) }}. Sign in to vote">@include('feedback::partials.icon', ['name' => 'up'])<span>{{ $count }}</span></span>
    @endif
@else
    <form class="fb-vote" method="post" action="{{ route('feedback.requests.vote', $feature) }}" data-feedback-vote>
        @csrf
        <input type="hidden" name="vote" value="{{ $voted ? 0 : 1 }}">
        <button type="submit" class="fb-vote-btn {{ $big ? 'fb-vote-btn--big' : '' }}" aria-pressed="{{ $voted ? 'true' : 'false' }}"
            aria-label="{{ $voted ? 'Remove your vote for' : 'Vote for' }} “{{ $feature->title }}”, {{ $count }} {{ \Illuminate\Support\Str::plural('vote', $feature->votes_count) }}">@include('feedback::partials.icon', ['name' => 'up'])<span data-feedback-count>{{ $count }}</span></button>
    </form>
@endif
