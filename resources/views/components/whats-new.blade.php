@once
<style>
    @include('feedback::partials.tokens', ['scope' => '.fb-wn'])
    .fb-wn { position: relative; display: inline-flex; font: 400 15px/1.5 var(--feedback-sans); color: var(--feedback-ink); -webkit-font-smoothing: antialiased; }
    .fb-wn *, .fb-wn *::before, .fb-wn *::after { box-sizing: border-box; }
    .fb-wn :focus-visible { outline: 2px solid var(--feedback-accent); outline-offset: 2px; }
    .fb-wn-button { position: relative; display: inline-flex; align-items: center; gap: .4rem; height: 2.375rem; padding: 0 .7rem; border-radius: var(--feedback-radius); color: var(--feedback-text-2); text-decoration: none; font: 500 .875rem/1 var(--feedback-sans); cursor: pointer; border: 1px solid transparent; transition: background-color .15s, color .15s; }
    .fb-wn-button:hover, .fb-wn-button[aria-expanded="true"] { background: var(--feedback-muted); color: var(--feedback-ink); text-decoration: none; }
    .fb-wn-button svg { width: 1.2rem; height: 1.2rem; flex: none; }
    .fb-wn-badge { min-width: 1.15rem; height: 1.15rem; padding: 0 .3rem; border-radius: 999px; background: var(--feedback-accent); color: var(--feedback-on-accent); font: 600 .65rem/1.15rem var(--feedback-sans); text-align: center; font-variant-numeric: tabular-nums; box-shadow: 0 4px 10px -4px color-mix(in srgb, var(--feedback-accent) 70%, transparent); }
    .fb-wn-badge[hidden] { display: none; }
    .fb-wn-panel { position: absolute; z-index: 1000; top: calc(100% + .5rem); right: 0; width: min(25rem, calc(100vw - 32px)); background: var(--feedback-bg); border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius-lg); box-shadow: 0 24px 60px -32px color-mix(in srgb, var(--feedback-accent) 60%, transparent); overflow: hidden; text-align: left; }
    .fb-wn-panel[hidden] { display: none; }
    .fb-wn-panel:focus { outline: none; }
    .fb-wn-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem .75rem; border-bottom: 1px solid var(--feedback-line); background: linear-gradient(160deg, var(--feedback-accent-soft), var(--feedback-bg) 70%); }
    .fb-wn-head h2 { margin: 0; font: 600 .95rem/1.3 var(--feedback-sans); letter-spacing: -.01em; color: var(--feedback-ink); }
    .fb-wn-link { font: 500 .8rem/1.3 var(--feedback-sans); color: var(--feedback-accent); text-decoration: none; }
    .fb-wn-link:hover { text-decoration: underline; text-underline-offset: 3px; }
    .fb-wn-list { list-style: none; margin: 0; padding: .35rem; max-height: min(28rem, 65vh); overflow-y: auto; scrollbar-width: thin; }
    .fb-wn-list li { margin: 0; padding: 0; }
    .fb-wn-item { display: grid; gap: .2rem; padding: .7rem .75rem .75rem; border-radius: 10px; color: var(--feedback-ink); text-decoration: none; position: relative; }
    .fb-wn-item:hover { background: var(--feedback-subtle); text-decoration: none; }
    .fb-wn-item.is-new { background: color-mix(in srgb, var(--feedback-accent-soft) 55%, transparent); }
    .fb-wn-item.is-new:hover { background: var(--feedback-accent-soft); }
    .fb-wn-top { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; font-size: .75rem; color: var(--feedback-text-3); }
    .fb-wn-chip { display: inline-flex; border-radius: 999px; padding: 0 .5rem; font-size: .7rem; font-weight: 500; line-height: 1.6; background: var(--fb-tone-soft, var(--feedback-accent-soft)); color: var(--fb-tone, var(--feedback-accent-ink)); }
    .fb-wn-top time { margin-left: auto; }
    .fb-wn-title { font-size: .9rem; line-height: 1.35; font-weight: 600; letter-spacing: -.01em; overflow-wrap: anywhere; }
    .fb-wn-text { font-size: .8125rem; line-height: 1.5; color: var(--feedback-text-2); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .fb-wn-dot { position: absolute; top: 1.05rem; left: -.05rem; width: .4rem; height: .4rem; border-radius: 50%; background: var(--feedback-accent); }
    .fb-wn-empty { padding: 2rem 1rem; text-align: center; font-size: .85rem; color: var(--feedback-text-3); margin: 0; }
    .fb-wn-foot { padding: .7rem 1rem; border-top: 1px solid var(--feedback-line); background: var(--feedback-subtle); }
    @media (max-width: 30rem) {
        .fb-wn-panel { position: fixed; left: 16px; right: 16px; width: auto; top: 4rem; }
    }
</style>
@endonce

<div {{ $attributes->class('fb-wn') }}
     data-feedback-whats-new
     data-unread="{{ $unread }}"
     data-seen-url="{{ route('feedback.changelog.seen') }}"
     data-csrf="{{ csrf_token() }}">
    <a class="fb-wn-button" href="{{ route('feedback.changelog') }}" data-fb-wn-toggle aria-controls="{{ $id }}" @if ($open) aria-expanded="true" @endif
       aria-label="{{ $label }}{{ $unread > 0 ? ', '.$unread.' new '.\Illuminate\Support\Str::plural('update', $unread) : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21"/><path d="M12 7.5 13.5 10.5 16.5 12 13.5 13.5 12 16.5 10.5 13.5 7.5 12 10.5 10.5Z"/></svg>
        <span>{{ $label }}</span>
        <span class="fb-wn-badge" data-fb-wn-badge aria-hidden="true" @if ($unread === 0) hidden @endif>{{ $unread > 9 ? '9+' : $unread }}</span>
    </a>

    <div class="fb-wn-panel" id="{{ $id }}" data-fb-wn-panel role="region" aria-labelledby="{{ $id }}-title" tabindex="-1" @unless ($open) hidden @endunless>
        <div class="fb-wn-head">
            <h2 id="{{ $id }}-title">{{ $label }}</h2>
            <a class="fb-wn-link" href="{{ route('feedback.changelog') }}">See all</a>
        </div>
        @if ($entries->isEmpty())
            <p class="fb-wn-empty">No updates yet. New features and fixes will show up here.</p>
        @else
            <ul class="fb-wn-list">
                @foreach ($entries as $entry)
                    @php($fresh = $entry->published_at !== null && $entry->published_at->greaterThan($since))
                    <li>
                        <a class="fb-wn-item {{ $fresh ? 'is-new' : '' }}" href="{{ $entry->url() }}">
                            @if ($fresh)<span class="fb-wn-dot"></span><span class="fb-wn-sr" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">New: </span>@endif
                            <span class="fb-wn-top">
                                @foreach ($entry->labelKeys() as $key)
                                    @php($tone = \Ruvelo\Feedback\Support\Palette::labelChip($key))
                                    <span class="fb-wn-chip {{ $tone['class'] }}" @if ($tone['style']) style="{{ $tone['style'] }}" @endif>{{ \Ruvelo\Feedback\Support\Palette::changelogLabel($key) }}</span>
                                @endforeach
                                <time datetime="{{ $entry->published_at?->toIso8601String() }}">{{ $entry->published_at?->format('M j') }}</time>
                            </span>
                            <span class="fb-wn-title">{{ $entry->title }}</span>
                            <span class="fb-wn-text">{{ $entry->excerpt(140) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="fb-wn-foot">
            <a class="fb-wn-link" href="{{ route('feedback.changelog') }}">Read the full changelog</a>
        </div>
    </div>
</div>

@once
<script>
(() => {
    const init = (root) => {
        if (root.dataset.fbWnReady) return;
        root.dataset.fbWnReady = '1';

        const button = root.querySelector('[data-fb-wn-toggle]');
        const panel = root.querySelector('[data-fb-wn-panel]');
        const badge = root.querySelector('[data-fb-wn-badge]');
        const label = root.querySelector('h2')?.textContent.trim() || 'What’s new';
        const demo = 'feedbackDemo' in root.dataset;
        let unread = Number(root.dataset.unread) || 0;

        button.setAttribute('role', 'button');
        button.setAttribute('aria-haspopup', 'true');
        if (!button.hasAttribute('aria-expanded')) button.setAttribute('aria-expanded', 'false');

        const isOpen = () => !panel.hidden;

        // Opening it means everything so far has been seen.
        const markSeen = () => {
            if (unread === 0) return;
            unread = 0;
            badge.hidden = true;
            button.setAttribute('aria-label', label);
            if (demo) return;
            fetch(root.dataset.seenUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf || '', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', keepalive: true }).catch(() => {});
        };

        const open = () => {
            panel.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            panel.style.top = matchMedia('(max-width: 30rem)').matches ? Math.round(button.getBoundingClientRect().bottom + 8) + 'px' : '';
            panel.focus({ preventScroll: true });
            markSeen();
        };

        const close = (refocus) => {
            if (!isOpen()) return;
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            if (refocus) button.focus();
        };

        button.addEventListener('click', (e) => {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
            e.preventDefault();
            isOpen() ? close(false) : open();
        });
        button.addEventListener('keydown', (e) => {
            if (e.key === ' ') { e.preventDefault(); button.click(); }
            if (e.key === 'ArrowDown' && !isOpen()) { e.preventDefault(); open(); }
        });
        root.addEventListener('keydown', (e) => { if (e.key === 'Escape' && isOpen()) { e.preventDefault(); close(true); } });
        document.addEventListener('click', (e) => { if (!root.contains(e.target)) close(false); });
        root.addEventListener('focusout', (e) => { if (e.relatedTarget && !root.contains(e.relatedTarget)) close(false); });
    };

    const all = () => document.querySelectorAll('[data-feedback-whats-new]').forEach(init);
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', all) : all();
    document.addEventListener('livewire:navigated', all);
})();
</script>
@endonce
