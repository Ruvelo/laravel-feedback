@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', 'Edit '.$feature->title)
@section('section', 'manage')
@section('robots', 'noindex')

@section('feedback')
    @include('feedback::manage.tabs')

    <ol class="fb-crumbs" aria-label="Breadcrumbs">
        <li><a href="{{ route('feedback.manage.requests') }}">Requests</a></li>
        <li><a href="{{ $feature->url() }}">{{ \Illuminate\Support\Str::limit($feature->getOriginal('title'), 50) }}</a></li>
        <li aria-current="page">Edit</li>
    </ol>

    <form method="post" action="{{ route('feedback.manage.requests.update', $feature) }}" style="max-width: 46rem">
        @csrf
        @method('put')
        <label for="fb-title" style="margin-top: 0">Title</label>
        <input id="fb-title" type="text" name="title" value="{{ old('title', $feature->title) }}" required maxlength="{{ (int) config('feedback.max_length.title', 160) }}">
        @error('title') <p class="fb-error">{{ $message }}</p> @enderror

        <label for="fb-body">Description</label>
        <textarea id="fb-body" name="body" class="fb-code" maxlength="{{ (int) config('feedback.max_length.body', 10000) }}">{{ old('body', $feature->body) }}</textarea>
        @error('body') <p class="fb-error">{{ $message }}</p> @enderror

        <div class="fb-fields">
            <div>
                <label for="fb-category">Category</label>
                <select id="fb-category" name="category">
                    <option value="">No category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category', $feature->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="fb-new-tags">New tags <span class="fb-muted">(comma-separated)</span></label>
                <input id="fb-new-tags" type="text" name="new_tags" value="{{ old('new_tags') }}" placeholder="Enterprise, Quick win">
            </div>
        </div>

        @if ($tags->isNotEmpty())
            <fieldset style="border: 0; padding: 0; margin: 1.25rem 0 0">
                <legend style="font-weight: 500; font-size: .85rem; margin-bottom: .4rem">Tags</legend>
                <div class="fb-checks">
                    @foreach ($tags as $tag)
                        <label class="fb-check"><input type="checkbox" name="tags[]" value="{{ $tag->slug }}" @checked($feature->tags->contains('id', $tag->id))> {{ $tag->name }}</label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        <div class="fb-row">
            <button type="submit">Save changes</button>
            <a class="fb-btn fb-btn--quiet" href="{{ $feature->url() }}">Cancel</a>
        </div>
    </form>
@endsection
