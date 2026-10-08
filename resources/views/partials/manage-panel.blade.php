{{-- Shown to managers on a request's page. Every form posts to the admin routes. --}}
<div class="fb-panel fb-manage" aria-labelledby="fb-manage-title">
    <h2 id="fb-manage-title" style="margin: 0">Manage this request</h2>

    @unless ($feature->isApproved())
        <form method="post" action="{{ route('feedback.manage.requests.approve', $feature) }}" class="fb-row">
            @csrf
            <button type="submit" class="fb-btn--small">Approve and publish</button>
        </form>
    @endunless

    <form method="post" action="{{ route('feedback.manage.requests.status', $feature) }}" data-feedback-status-form>
        @csrf
        <label for="fb-status">Status</label>
        <select id="fb-status" name="status" data-feedback-status-select>
            @foreach (\Ruvelo\Feedback\Support\Palette::statuses() as $key => $label)
                <option value="{{ $key }}" @selected(old('status', $feature->status) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <p class="fb-error">{{ $message }}</p> @enderror

        @if ($entries->isNotEmpty())
            <div data-feedback-entry-field>
                <label for="fb-entry">Shipped in <span class="fb-muted">(when shipped)</span></label>
                <select id="fb-entry" name="entry">
                    <option value="">No changelog entry</option>
                    @foreach ($entries as $entryOption)
                        <option value="{{ $entryOption->id }}" @selected((string) old('entry', $feature->entry_id) === (string) $entryOption->id)>{{ $entryOption->title }}{{ $entryOption->isPublished() ? '' : ' (draft)' }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <label for="fb-note">Public note <span class="fb-muted">(optional)</span></label>
        <textarea id="fb-note" name="note" maxlength="5000" placeholder="Tell voters what’s happening. Markdown works.">{{ old('note') }}</textarea>
        <input type="hidden" name="notify" value="0">
        <label class="fb-check"><input type="checkbox" name="notify" value="1" checked> Tell the author and voters</label>
        <div class="fb-row"><button type="submit" class="fb-btn--small">Update status</button></div>
    </form>

    <details>
        <summary>More actions</summary>
        <div class="fb-row">
            <form method="post" action="{{ route('feedback.manage.requests.pin', $feature) }}">
                @csrf
                <input type="hidden" name="pinned" value="{{ $feature->isPinned() ? 0 : 1 }}">
                <button type="submit" class="fb-btn--quiet fb-btn--small">{{ $feature->isPinned() ? 'Unpin' : 'Pin to the top' }}</button>
            </form>
            <a class="fb-btn fb-btn--quiet fb-btn--small" href="{{ route('feedback.manage.requests.edit', $feature) }}">Edit</a>
            @if (Route::has('feedback.manage.entries.create') && ! $feature->isShipped() && \Ruvelo\Feedback\Feedback::enabled('changelog'))
                <a class="fb-btn fb-btn--quiet fb-btn--small" href="{{ route('feedback.manage.entries.create', ['requests' => [$feature->id]]) }}">Announce it</a>
            @endif
        </div>

        @if ($mergeTargets->isNotEmpty())
            <form method="post" action="{{ route('feedback.manage.requests.merge', $feature) }}" onsubmit="return confirm('Merge this request into the one you picked? Its votes and comments move there, and this page will redirect.')">
                @csrf
                <label for="fb-merge">Merge into</label>
                <select id="fb-merge" name="into" required>
                    <option value="">Pick the request to keep…</option>
                    @foreach ($mergeTargets as $target)
                        <option value="{{ $target->id }}">#{{ $target->id }} {{ \Illuminate\Support\Str::limit($target->title, 60) }} ({{ $target->votes_count }})</option>
                    @endforeach
                </select>
                @error('into') <p class="fb-error">{{ $message }}</p> @enderror
                <p class="fb-hint">Votes carry over; people who voted for both keep one vote.</p>
                <div class="fb-row"><button type="submit" class="fb-btn--quiet fb-btn--small">Merge as duplicate</button></div>
            </form>
        @endif

        <form method="post" action="{{ route('feedback.manage.requests.destroy', $feature) }}" onsubmit="return confirm('Delete this request, its votes and comments? This can’t be undone.')" class="fb-row">
            @csrf
            @method('delete')
            <button type="submit" class="fb-btn--danger fb-btn--small">Delete request</button>
        </form>
    </details>
</div>
