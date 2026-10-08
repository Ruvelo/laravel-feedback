{{-- Renders the pages inside the host app's layout (config: feedback.layout and feedback.section). --}}
@extends(config('feedback.layout'))

@push('feedback-head')
    @include('feedback::partials.meta')
@endpush

@section(config('feedback.section', 'content'))
    @include('feedback::partials.styles')
    <div class="fb">
        @include('feedback::partials.page')
    </div>
@endsection
