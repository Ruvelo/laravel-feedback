@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', 'Manage the changelog')
@section('section', 'manage')
@section('robots', 'noindex')

@section('feedback')
    @include('feedback::manage.tabs')

    <div class="fb-filters" style="justify-content: space-between">
        <p class="fb-muted" style="margin: 0">Publish an update and link the requests it answers: they’re marked shipped and their voters hear about it.</p>
        <a class="fb-btn" href="{{ route('feedback.manage.entries.create') }}">@include('feedback::partials.icon', ['name' => 'plus'])Write an update</a>
    </div>

    @if ($entries->isEmpty())
        <p class="fb-empty"><strong>The changelog is empty.</strong> <a href="{{ route('feedback.manage.entries.create') }}">Write the first update</a>.</p>
    @else
        <div class="fb-table-wrap">
            <table class="fb-table">
                <thead>
                    <tr>
                        <th>Entry</th>
                        <th>State</th>
                        <th class="num">Requests</th>
                        <th class="num">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td>
                                <a href="{{ route('feedback.manage.entries.edit', $entry) }}">{{ $entry->title }}</a>
                                <small>@foreach ($entry->labelKeys() as $label){{ \Ruvelo\Feedback\Support\Palette::changelogLabel($label) }}@if (! $loop->last), @endif @endforeach</small>
                            </td>
                            <td>
                                @if ($entry->isPublished())
                                    <span class="fb-chip fb-tone-success">Published</span>
                                @elseif ($entry->isScheduled())
                                    <span class="fb-chip fb-tone-lilac">Scheduled</span>
                                @else
                                    <span class="fb-chip fb-tone-warning">Draft</span>
                                @endif
                            </td>
                            <td class="num">{{ $entry->requests_count }}</td>
                            <td class="num">{{ $entry->published_at?->toFormattedDateString() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $entries->links() }}
    @endif
@endsection
