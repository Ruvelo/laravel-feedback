@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', 'Suggest a feature')
@section('section', 'board')
@section('robots', 'noindex')

@section('feedback')
    <ol class="fb-crumbs" aria-label="Breadcrumbs">
        <li><a href="{{ route('feedback.home') }}">Feedback</a></li>
        <li aria-current="page">Suggest a feature</li>
    </ol>

    <div class="fb-cols">
        <div>
            <div class="fb-head" style="margin-bottom: .5rem">
                <h1>Suggest a feature</h1>
            </div>

            <form method="post" action="{{ route('feedback.requests.store') }}" data-feedback-create>
                @csrf
                @if (config('feedback.honeypot'))
                    <div class="fb-honey" aria-hidden="true">
                        <label for="fb-{{ config('feedback.honeypot') }}">Leave this empty</label>
                        <input id="fb-{{ config('feedback.honeypot') }}" type="text" name="{{ config('feedback.honeypot') }}" tabindex="-1" autocomplete="off">
                    </div>
                @endif

                <label for="fb-title">Title</label>
                <input id="fb-title" type="text" name="title" value="{{ old('title', $title) }}" required minlength="3" maxlength="{{ (int) config('feedback.max_length.title', 160) }}"
                    placeholder="One line: what would you like?" autocomplete="off" autofocus
                    aria-describedby="fb-similar" data-feedback-similar="{{ route('feedback.similar') }}">
                @error('title') <p class="fb-error">{{ $message }}</p> @enderror

                <section class="fb-similar" id="fb-similar" aria-live="polite" @if ($similar->isEmpty()) hidden @endif data-feedback-similar-list>
                    <h2>Similar requests. Vote for one of these instead?</h2>
                    <ul>
                        @foreach ($similar as $match)
                            <li><a href="{{ $match->url() }}"><span class="fb-similar-votes">{{ $match->votes_count }}</span><span class="fb-similar-title">{{ $match->title }}</span>@include('feedback::partials.status', ['status' => $match->status])</a></li>
                        @endforeach
                    </ul>
                </section>

                <label for="fb-body">Details <span class="fb-muted">(optional)</span></label>
                <textarea id="fb-body" name="body" maxlength="{{ (int) config('feedback.max_length.body', 10000) }}" placeholder="What are you trying to do, and what gets in the way today?">{{ old('body') }}</textarea>
                <p class="fb-hint">Markdown works: **bold**, lists, `code` and links.</p>
                @error('body') <p class="fb-error">{{ $message }}</p> @enderror

                @if ($categories->isNotEmpty())
                    <label for="fb-category">Category</label>
                    <select id="fb-category" name="category">
                        <option value="">Not sure</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="fb-error">{{ $message }}</p> @enderror
                @endif

                <div class="fb-row">
                    <button type="submit">Post request</button>
                    <a class="fb-btn fb-btn--quiet" href="{{ route('feedback.home') }}">Cancel</a>
                </div>
            </form>
        </div>

        <aside class="fb-side">
            <div class="fb-panel fb-panel--soft">
                <h3>What makes a good request</h3>
                <p>Describe the problem, not only the fix: what you were doing, and what got in the way. It helps us build the right thing.</p>
            </div>
            <div class="fb-panel">
                <h3>What happens next</h3>
                <p>Your request starts with your vote. Others can vote and comment, and you’ll hear from us when its status changes or it ships.</p>
            </div>
        </aside>
    </div>
@endsection
