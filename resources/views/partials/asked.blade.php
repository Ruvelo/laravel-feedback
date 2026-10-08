@php($asked = $entry->requests->filter(fn ($feature) => $feature->merged_into_id === null && $feature->approved_at !== null))
@if ($asked->isNotEmpty())
    <aside class="fb-asked" aria-label="Requests this answers">
        <h3>You asked for this: {{ number_format($asked->sum('votes_count')) }} {{ \Illuminate\Support\Str::plural('vote', $asked->sum('votes_count')) }}</h3>
        <ul>
            @foreach ($asked as $feature)
                <li><span class="fb-shipped-icon" style="width: 1.25rem; height: 1.25rem">@include('feedback::partials.icon', ['name' => 'check'])</span><a href="{{ $feature->url() }}">{{ $feature->title }}</a><span class="fb-chip fb-chip--quiet">{{ $feature->votes_count }} {{ \Illuminate\Support\Str::plural('vote', $feature->votes_count) }}</span></li>
            @endforeach
        </ul>
    </aside>
@endif
