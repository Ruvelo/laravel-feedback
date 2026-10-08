@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', 'Manage requests')
@section('section', 'manage')
@section('robots', 'noindex')

@section('feedback')
    @include('feedback::manage.tabs')

    <form class="fb-filters" method="get" action="{{ route('feedback.manage.requests') }}">
        <nav class="fb-seg" aria-label="Show">
            <a href="{{ route('feedback.manage.requests') }}" @if ($tab === 'all') aria-current="true" @endif>All</a>
            <a href="{{ route('feedback.manage.requests', ['tab' => 'pending']) }}" @if ($tab === 'pending') aria-current="true" @endif>Waiting for approval ({{ $pending }})</a>
        </nav>
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="fb-sr" for="fb-manage-q">Search</label>
        <input id="fb-manage-q" type="search" name="q" value="{{ $q }}" placeholder="Search titles and descriptions…">
        <label class="fb-sr" for="fb-manage-status">Status</label>
        <select id="fb-manage-status" name="status" onchange="this.form.submit()">
            <option value="">Any status</option>
            @foreach (\Ruvelo\Feedback\Support\Palette::statuses() as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="fb-btn--quiet">Filter</button>
    </form>

    @if ($requests->isEmpty())
        <p class="fb-empty">
            @if ($tab === 'pending')
                <strong>Nothing is waiting.</strong> New requests appear here when approval is on (<code>feedback.board.require_approval</code>).
            @else
                <strong>No requests match.</strong>
            @endif
        </p>
    @else
        <div class="fb-table-wrap">
            <table class="fb-table">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Status</th>
                        <th class="num">Votes</th>
                        <th class="num">Comments</th>
                        <th class="num">Posted</th>
                        <th><span class="fb-sr">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $feature)
                        <tr>
                            <td>
                                <a href="{{ $feature->url() }}">{{ $feature->title }}</a>
                                <small>{{ $feature->authorName() }}@if ($feature->category) · {{ $feature->category->name }}@endif @if ($feature->isPinned()) · Pinned @endif</small>
                            </td>
                            <td>@include('feedback::partials.status', ['status' => $feature->status])</td>
                            <td class="num">{{ number_format($feature->votes_count) }}</td>
                            <td class="num">{{ number_format($feature->comments_count) }}</td>
                            <td class="num" title="{{ $feature->created_at->toDayDateTimeString() }}">{{ $feature->created_at->diffForHumans(short: true) }}</td>
                            <td>
                                <div class="fb-actions">
                                    @unless ($feature->isApproved())
                                        <form method="post" action="{{ route('feedback.manage.requests.approve', $feature) }}">
                                            @csrf
                                            <button type="submit" class="fb-btn--small">Approve</button>
                                        </form>
                                        <form method="post" action="{{ route('feedback.manage.requests.destroy', $feature) }}" onsubmit="return confirm('Reject and delete this request?')">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="fb-btn--danger fb-btn--small">Reject</button>
                                        </form>
                                    @else
                                        <a class="fb-btn fb-btn--quiet fb-btn--small" href="{{ route('feedback.manage.requests.edit', $feature) }}">Edit</a>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    @endif
@endsection
