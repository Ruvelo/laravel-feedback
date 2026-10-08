@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', 'Categories and tags')
@section('section', 'manage')
@section('robots', 'noindex')

@section('feedback')
    @include('feedback::manage.tabs')

    <div class="fb-taxonomy">
        <section aria-labelledby="fb-cats">
            <h2 id="fb-cats" style="font-size: 1.1rem; margin: 0 0 .3rem">Categories</h2>
            <p class="fb-muted" style="margin: 0 0 1rem; font-size: .9rem">People pick one when they post. Use them for areas of your product.</p>
            @foreach ($categories as $category)
                <div class="fb-tax-row">
                    <form method="post" action="{{ route('feedback.manage.categories.update', $category) }}">
                        @csrf
                        @method('put')
                        <label class="fb-sr" for="fb-cat-{{ $category->id }}">Name</label>
                        <input id="fb-cat-{{ $category->id }}" type="text" name="name" value="{{ $category->name }}" required maxlength="80">
                        <label class="fb-sr" for="fb-cat-pos-{{ $category->id }}">Position</label>
                        <input id="fb-cat-pos-{{ $category->id }}" type="number" name="position" value="{{ $category->position }}" min="0">
                        <button type="submit" class="fb-btn--quiet fb-btn--small">Save</button>
                    </form>
                    <span class="fb-chip fb-chip--quiet">{{ $category->requests_count }}</span>
                    <form method="post" action="{{ route('feedback.manage.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? Its requests keep their votes and lose the category.')">
                        @csrf
                        @method('delete')
                        <button type="submit" class="fb-link fb-link--danger">Delete</button>
                    </form>
                </div>
            @endforeach
            <form method="post" action="{{ route('feedback.manage.categories.store') }}" class="fb-row">
                @csrf
                <label class="fb-sr" for="fb-new-cat">New category</label>
                <input id="fb-new-cat" type="text" name="name" required maxlength="80" placeholder="New category, e.g. Integrations" style="flex: 1">
                <button type="submit" class="fb-btn--small">Add category</button>
            </form>
            @error('name') <p class="fb-error">{{ $message }}</p> @enderror
        </section>

        <section aria-labelledby="fb-tags">
            <h2 id="fb-tags" style="font-size: 1.1rem; margin: 0 0 .3rem">Tags</h2>
            <p class="fb-muted" style="margin: 0 0 1rem; font-size: .9rem">Only managers add tags, from a request’s Edit page. They show as filters on the board.</p>
            @forelse ($tags as $tag)
                <div class="fb-tax-row">
                    <form method="post" action="{{ route('feedback.manage.tags.update', $tag) }}">
                        @csrf
                        @method('put')
                        <label class="fb-sr" for="fb-tag-{{ $tag->id }}">Name</label>
                        <input id="fb-tag-{{ $tag->id }}" type="text" name="name" value="{{ $tag->name }}" required maxlength="60">
                        <button type="submit" class="fb-btn--quiet fb-btn--small">Save</button>
                    </form>
                    <span class="fb-chip fb-chip--quiet">{{ $tag->requests_count }}</span>
                    <form method="post" action="{{ route('feedback.manage.tags.destroy', $tag) }}" onsubmit="return confirm('Delete this tag from every request?')">
                        @csrf
                        @method('delete')
                        <button type="submit" class="fb-link fb-link--danger">Delete</button>
                    </form>
                </div>
            @empty
                <p class="fb-muted" style="font-size: .9rem">No tags yet.</p>
            @endforelse
            <form method="post" action="{{ route('feedback.manage.tags.store') }}" class="fb-row">
                @csrf
                <label class="fb-sr" for="fb-new-tag">New tag</label>
                <input id="fb-new-tag" type="text" name="name" required maxlength="60" placeholder="New tag, e.g. Enterprise" style="flex: 1">
                <button type="submit" class="fb-btn--small">Add tag</button>
            </form>
        </section>
    </div>
@endsection
