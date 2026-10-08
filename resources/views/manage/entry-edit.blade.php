@extends(\Ruvelo\Feedback\Feedback::layout())

@section('title', $entry->exists ? 'Editing '.$entry->getOriginal('title') : 'New update')
@section('section', 'manage')
@section('robots', 'noindex')

@section('feedback')
    @include('feedback::manage.tabs')

    <ol class="fb-crumbs" aria-label="Breadcrumbs">
        <li><a href="{{ route('feedback.manage.entries') }}">Changelog</a></li>
        <li aria-current="page">{{ $entry->exists ? \Illuminate\Support\Str::limit((string) $entry->getOriginal('title'), 50) : 'New update' }}</li>
        @if ($entry->exists)
            <li><a href="{{ $entry->url() }}">{{ $entry->isPublished() ? 'View it' : 'Preview it' }}</a></li>
        @endif
    </ol>

    <form method="post" action="{{ $entry->exists ? route('feedback.manage.entries.update', $entry) : route('feedback.manage.entries.store') }}" class="fb-editor" data-feedback-editor="{{ route('feedback.manage.entries.preview') }}">
        @csrf
        @if ($entry->exists) @method('put') @endif
        <div>
            <label for="fb-title" style="margin-top: 0">Title</label>
            <input id="fb-title" type="text" name="title" value="{{ old('title', $entry->title) }}" required maxlength="255" placeholder="Usage-based billing is here">
            @error('title') <p class="fb-error">{{ $message }}</p> @enderror

            <fieldset style="border: 0; padding: 0; margin: 1.25rem 0 0">
                <legend style="font-weight: 500; font-size: .85rem; margin-bottom: .4rem">Labels</legend>
                <div class="fb-checks">
                    @foreach (\Ruvelo\Feedback\Support\Palette::changelogLabels() as $key => $label)
                        <label class="fb-check"><input type="checkbox" name="labels[]" value="{{ $key }}" @checked(in_array($key, (array) old('labels', $entry->labelKeys()), true))> {{ $label }}</label>
                    @endforeach
                </div>
                @error('labels') <p class="fb-error">{{ $message }}</p> @enderror
            </fieldset>

            <div class="fb-fields">
                <div>
                    <label for="fb-state">State</label>
                    <select id="fb-state" name="state">
                        <option value="published" @selected(old('state', $entry->exists && $entry->published_at === null ? 'draft' : 'published') === 'published')>Published (or scheduled)</option>
                        <option value="draft" @selected(old('state', $entry->exists && $entry->published_at === null ? 'draft' : 'published') === 'draft')>Draft: only managers see it</option>
                    </select>
                </div>
                <div>
                    <label for="fb-date">Date</label>
                    <input id="fb-date" type="datetime-local" name="published_at" value="{{ old('published_at', ($entry->published_at ?? now())->format('Y-m-d\TH:i')) }}">
                    @error('published_at') <p class="fb-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <label for="fb-cover">Cover image URL <span class="fb-muted">(optional)</span></label>
            <input id="fb-cover" type="url" name="cover_url" value="{{ old('cover_url', $entry->cover_url) }}" placeholder="https://…/screenshot.png" maxlength="2048">
            @error('cover_url') <p class="fb-error">{{ $message }}</p> @enderror

            <label for="fb-body">What changed</label>
            <textarea id="fb-body" name="body" class="fb-code" spellcheck="true" placeholder="Markdown: **bold**, lists, links, images, code…">{{ old('body', $entry->body) }}</textarea>
            @error('body') <p class="fb-error">{{ $message }}</p> @enderror

            @if ($requests->isNotEmpty())
                <fieldset style="border: 0; padding: 0; margin: 1.25rem 0 0">
                    <legend style="font-weight: 500; font-size: .85rem; margin-bottom: .4rem">Requests this ships</legend>
                    <div class="fb-picklist">
                        @foreach ($requests as $feature)
                            <label class="fb-check"><input type="checkbox" name="requests[]" value="{{ $feature->id }}" @checked(in_array($feature->id, array_map('intval', (array) old('requests', $linked)), true))> <span>{{ $feature->title }}</span> @include('feedback::partials.status', ['status' => $feature->status])<span class="fb-chip fb-chip--quiet">{{ $feature->votes_count }}</span></label>
                        @endforeach
                    </div>
                    <input type="hidden" name="notify" value="0">
                    <label class="fb-check"><input type="checkbox" name="notify" value="1" @checked(old('notify', '1') === '1')> Mark them shipped and tell their voters when this goes live</label>
                </fieldset>
            @endif

            <div class="fb-row">
                <button type="submit" name="action" value="save">{{ $entry->exists ? 'Save changes' : 'Save update' }}</button>
                <button type="submit" name="action" value="preview" class="fb-btn--quiet" data-feedback-preview-button>Preview</button>
            </div>
        </div>

        <section class="fb-editor-preview" aria-label="Preview" aria-live="polite">
            <p class="fb-muted" style="margin: 0 0 .75rem"><small>Preview</small></p>
            <div class="fb-prose" data-feedback-preview>{!! $preview !!}</div>
        </section>
    </form>

    @if ($entry->exists)
        <form method="post" action="{{ route('feedback.manage.entries.destroy', $entry) }}" onsubmit="return confirm('Delete this update? Requests it shipped stay shipped.')" class="fb-row" style="margin-top: 3rem">
            @csrf
            @method('delete')
            <button type="submit" class="fb-btn--danger">Delete update</button>
        </form>
    @endif
@endsection
