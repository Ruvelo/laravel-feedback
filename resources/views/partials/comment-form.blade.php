@php($id = 'fb-comment-'.($parent?->id ?? 'new'))
<form class="fb-compose" method="post" action="{{ route('feedback.comments.store', $feature) }}">
    @csrf
    @if ($parent)
        <input type="hidden" name="parent_id" value="{{ $parent->id }}">
    @endif
    @if (config('feedback.honeypot'))
        <div class="fb-honey" aria-hidden="true"><input type="text" name="{{ config('feedback.honeypot') }}" tabindex="-1" autocomplete="off"></div>
    @endif
    @include('feedback::partials.avatar', ['name' => \Ruvelo\Feedback\Support\People::name(auth()->user() instanceof \Illuminate\Database\Eloquent\Model ? auth()->user() : null, 'You')])
    <div>
        <label class="fb-sr" for="{{ $id }}">{{ $parent ? 'Reply to '.$parent->authorName() : 'Add a comment' }}</label>
        <textarea id="{{ $id }}" name="body" required maxlength="{{ (int) config('feedback.max_length.comment', 5000) }}" placeholder="{{ $parent ? 'Write a reply…' : 'Add a use case, an example, or a question…' }}">{{ (string) old('parent_id') === (string) ($parent?->id ?? '') ? old('body') : '' }}</textarea>
        @if ((string) old('parent_id') === (string) ($parent?->id ?? ''))
            @error('body') <p class="fb-error">{{ $message }}</p> @enderror
        @endif
        <div class="fb-row">
            <span class="fb-hint" style="margin: 0">Markdown works.</span>
            <button type="submit" class="fb-btn--small">{{ $parent ? 'Post reply' : 'Post comment' }}</button>
        </div>
    </div>
</form>
