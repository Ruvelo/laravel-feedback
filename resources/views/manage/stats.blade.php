@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', 'Stats')
@section('section', 'manage')
@section('robots', 'noindex')

@section('feedback')
    @include('feedback::manage.tabs')
    @php
        $open = collect($stats['statuses'])->only(\Ruvelo\Feedback\Support\Palette::openStatuses())->sum();
        $maxVotes = max(1, collect($stats['weeks'])->max('votes'));
        $maxTop = max(1, (int) $stats['top']->max('votes_count'));
        $weekVotes = collect($stats['weeks'])->sum('votes');
        $weekRequests = collect($stats['weeks'])->sum('requests');
    @endphp

    <div class="fb-stats">
        <div class="fb-stat"><small>Open requests</small><strong>{{ number_format($open) }}</strong></div>
        <div class="fb-stat"><small>Shipped</small><strong>{{ number_format($stats['statuses']['shipped'] ?? 0) }}</strong></div>
        <div class="fb-stat"><small>Votes, all time</small><strong>{{ number_format($stats['votes']) }}</strong></div>
        <div class="fb-stat"><small>People who voted</small><strong>{{ number_format($stats['voters']) }}</strong></div>
        <div class="fb-stat"><small>Waiting for approval</small><strong>{{ number_format($stats['pending']) }}</strong></div>
    </div>

    <section class="fb-section" style="margin-top: 0" aria-labelledby="fb-weeks">
        <h2 id="fb-weeks">Votes per week <small>{{ number_format($weekVotes) }} votes and {{ number_format($weekRequests) }} new requests in {{ count($stats['weeks']) }} weeks</small></h2>
        <div class="fb-chart" style="--fb-weeks: {{ count($stats['weeks']) }}" role="img" aria-label="Votes per week for the last {{ count($stats['weeks']) }} weeks">
            @foreach ($stats['weeks'] as $week)
                <div class="fb-chart-col" title="Week of {{ $week['start']->toFormattedDateString() }}: {{ $week['votes'] }} votes, {{ $week['requests'] }} new requests">
                    <div class="fb-chart-bar" style="height: {{ round($week['votes'] / $maxVotes * 100, 1) }}%"><span>{{ $week['votes'] ?: '' }}</span></div>
                    <span>{{ $week['start']->format('M j') }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="fb-taxonomy fb-section">
        <section aria-labelledby="fb-top">
            <h2 id="fb-top" style="font-size: 1.1rem; margin: 0 0 1rem">Top open requests</h2>
            @if ($stats['top']->isEmpty())
                <p class="fb-empty">No open requests.</p>
            @else
                <div class="fb-table-wrap">
                    <table class="fb-table">
                        <tbody>
                            @foreach ($stats['top'] as $feature)
                                <tr>
                                    <td><a href="{{ $feature->url() }}">{{ $feature->title }}</a><small>{{ $feature->statusLabel() }}</small></td>
                                    <td class="num"><span class="fb-meter"><span style="width: {{ round($feature->votes_count / $maxTop * 100) }}%"></span></span>{{ number_format($feature->votes_count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section aria-labelledby="fb-by-status">
            <h2 id="fb-by-status" style="font-size: 1.1rem; margin: 0 0 1rem">Requests by status</h2>
            <div class="fb-table-wrap">
                <table class="fb-table">
                    <tbody>
                        @foreach ($stats['statuses'] as $status => $total)
                            <tr>
                                <td>@include('feedback::partials.status', ['status' => $status])</td>
                                <td class="num">{{ number_format($total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
