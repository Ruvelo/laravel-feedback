<style>
    /* Ruvelo house style, scoped to .fb so it can't leak into a host layout.
       Override any --feedback-* variable on .fb to re-theme. */
    @include('feedback::partials.tokens', ['scope' => '.fb'])
    .fb { background: var(--feedback-bg); color: var(--feedback-ink); font: 15px/1.6 var(--feedback-sans); -webkit-font-smoothing: antialiased; }
    .fb *, .fb *::before, .fb *::after { box-sizing: border-box; }
    .fb a { color: var(--feedback-accent); text-decoration: none; }
    .fb a:hover { text-decoration: underline; text-underline-offset: 3px; }
    .fb :focus-visible { outline: 2px solid var(--feedback-accent); outline-offset: 2px; border-radius: 4px; }
    .fb [hidden] { display: none !important; }
    .fb-sr { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    .fb h1, .fb h2, .fb h3, .fb h4 { line-height: 1.25; font-weight: 600; letter-spacing: -.02em; color: var(--feedback-ink); }

    /* Header and tabs */
    .fb-top { position: sticky; top: 0; z-index: 20; background: color-mix(in srgb, var(--feedback-bg) 90%, transparent); backdrop-filter: blur(12px); border-bottom: 1px solid var(--feedback-line); }
    .fb-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.5rem; max-width: 72rem; margin: 0 auto; padding: .7rem 24px 0; }
    .fb-brand { display: inline-flex; align-items: center; gap: .6rem; font-weight: 600; font-size: .95rem; color: var(--feedback-ink) !important; letter-spacing: -.01em; }
    .fb-brand:hover { text-decoration: none !important; }
    .fb-mark { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 7px; background: linear-gradient(135deg, var(--feedback-accent), var(--feedback-accent-2)); color: #fff; font-size: .85rem; font-weight: 700; box-shadow: 0 4px 12px -4px color-mix(in srgb, var(--feedback-accent) 60%, transparent); }
    .fb-bar nav { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem 1.1rem; margin-left: auto; font-size: .875rem; }
    .fb .fb-bar nav a:not(.fb-btn) { color: var(--feedback-text-2); }
    .fb .fb-bar nav a:not(.fb-btn):hover { color: var(--feedback-ink); text-decoration: none; }
    .fb-tabs { display: flex; gap: .25rem; max-width: 72rem; margin: 0 auto; padding: 0 24px; overflow-x: auto; scrollbar-width: none; }
    .fb .fb-tabs a { display: inline-flex; align-items: center; gap: .45rem; padding: .7rem .2rem .65rem; margin-right: 1.1rem; border-bottom: 2px solid transparent; color: var(--feedback-text-2); font-size: .9rem; font-weight: 500; white-space: nowrap; }
    .fb .fb-tabs a:hover { color: var(--feedback-ink); text-decoration: none; }
    .fb .fb-tabs a[aria-current] { color: var(--feedback-ink); border-bottom-color: var(--feedback-accent); }
    .fb-tabs svg { width: 1rem; height: 1rem; color: var(--feedback-text-3); }
    .fb-tabs a[aria-current] svg { color: var(--feedback-accent); }

    .fb-page { max-width: 72rem; margin: 0 auto; padding: 2.25rem 24px 4rem; }
    .fb-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem 2rem; margin-bottom: 1.75rem; }
    .fb-head h1 { font-size: clamp(1.6rem, 3.2vw, 2.1rem); letter-spacing: -.035em; margin: 0; }
    .fb-lede { color: var(--feedback-text-2); margin: .4rem 0 0; max-width: 46rem; }
    .fb-foot { max-width: 72rem; margin: 0 auto; padding: 0 24px 2rem; color: var(--feedback-text-3); font-size: .8rem; }
    .fb-foot-in { border-top: 1px solid var(--feedback-line); padding-top: 1.25rem; display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; justify-content: space-between; }
    .fb .fb-foot a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }

    /* Two columns: content and a sidebar */
    .fb-cols { display: grid; grid-template-columns: minmax(0, 1fr) 17.5rem; gap: 2.5rem; align-items: start; }
    .fb-cols--left { grid-template-columns: 15rem minmax(0, 1fr); }
    .fb-side { display: grid; gap: 1.5rem; position: sticky; top: 7.5rem; }
    .fb-side h2 { font-size: .8rem; color: var(--feedback-text-3); font-weight: 500; letter-spacing: 0; margin: 0 0 .5rem; }
    .fb-filter { list-style: none; margin: 0; padding: 0; font-size: .9rem; }
    .fb .fb-filter a { display: flex; align-items: center; gap: .55rem; padding: .38rem .6rem; margin: 0 -.6rem; border-radius: var(--feedback-radius); color: var(--feedback-text-2); }
    .fb .fb-filter a:hover { background: var(--feedback-subtle); color: var(--feedback-ink); text-decoration: none; }
    .fb .fb-filter a[aria-current] { background: var(--feedback-accent-soft); color: var(--feedback-accent-ink); font-weight: 500; }
    .fb-filter .fb-count { margin-left: auto; font-size: .78rem; color: var(--feedback-text-3); font-variant-numeric: tabular-nums; }
    .fb-filter a[aria-current] .fb-count { color: var(--feedback-accent-ink); }
    .fb-dot { width: .5rem; height: .5rem; border-radius: 50%; background: var(--fb-tone-dot, var(--feedback-text-3)); flex: none; }
    .fb-tagcloud { display: flex; flex-wrap: wrap; gap: .35rem; }

    .fb-panel { border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius-lg); padding: 1.1rem 1.2rem; }
    .fb-panel--soft { background: var(--feedback-accent-soft); border-color: transparent; }
    .fb-panel--soft h2, .fb-panel--soft h3 { color: var(--feedback-accent-ink) !important; }
    .fb-panel h3 { font-size: 1rem; margin: 0 0 .3rem; }
    .fb-panel p { margin: 0; color: var(--feedback-text-2); font-size: .9rem; }
    .fb-panel .fb-btn { margin-top: .9rem; width: 100%; }

    /* Toolbar: search and sort */
    .fb-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-bottom: 1rem; }
    .fb-search { position: relative; flex: 1 1 16rem; }
    .fb-search::before { content: ""; position: absolute; left: .8rem; top: 50%; width: .85rem; height: .85rem; translate: 0 -50%; background: var(--feedback-text-3); pointer-events: none; -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M7 1.5a5.5 5.5 0 0 1 4.38 8.83l3.15 3.14-1.06 1.06-3.14-3.15A5.5 5.5 0 1 1 7 1.5Zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z'/%3E%3C/svg%3E") center / contain no-repeat; mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M7 1.5a5.5 5.5 0 0 1 4.38 8.83l3.15 3.14-1.06 1.06-3.14-3.15A5.5 5.5 0 1 1 7 1.5Zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z'/%3E%3C/svg%3E") center / contain no-repeat; }
    .fb .fb-search input[type=search] { padding-left: 2.2rem; background: var(--feedback-muted); border-color: transparent; }
    .fb .fb-search input[type=search]:focus { background: var(--feedback-bg); border-color: var(--feedback-accent); }
    .fb-seg { display: inline-flex; padding: .2rem; gap: .15rem; background: var(--feedback-muted); border-radius: calc(var(--feedback-radius) + 2px); }
    .fb .fb-seg a { padding: .35rem .75rem; border-radius: var(--feedback-radius); color: var(--feedback-text-2); font-size: .85rem; font-weight: 500; }
    .fb .fb-seg a:hover { color: var(--feedback-ink); text-decoration: none; }
    .fb .fb-seg a[aria-current] { background: var(--feedback-bg); color: var(--feedback-ink); box-shadow: 0 0 0 1px var(--feedback-line); }
    .fb-active { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin: -.25rem 0 1rem; font-size: .85rem; color: var(--feedback-text-3); }

    /* Request cards */
    .fb-list { list-style: none; margin: 0; padding: 0; display: grid; gap: .75rem; }
    .fb-card { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 1rem; padding: 1rem 1.1rem; border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius-lg); background: var(--feedback-bg); transition: border-color .15s, background .15s; }
    .fb-card:hover { border-color: color-mix(in srgb, var(--feedback-accent) 45%, var(--feedback-line)); }
    .fb-card.is-pinned { background: linear-gradient(160deg, var(--feedback-accent-soft), var(--feedback-bg) 55%); }
    .fb-card-title { font-size: 1rem; font-weight: 600; letter-spacing: -.01em; margin: 0; line-height: 1.35; overflow-wrap: anywhere; }
    .fb .fb-card-title a { color: var(--feedback-ink); }
    .fb .fb-card-title a:hover { color: var(--feedback-accent); text-decoration: none; }
    .fb .fb-card-title a::after { content: ""; position: absolute; inset: 0; }
    .fb-card-body { position: relative; min-width: 0; }
    .fb-card-text { color: var(--feedback-text-2); font-size: .9rem; margin: .25rem 0 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .fb-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .8rem; margin-top: .6rem; color: var(--feedback-text-3); font-size: .8rem; }
    .fb-meta svg { width: .95rem; height: .95rem; vertical-align: -.18em; margin-right: .2rem; }
    .fb-meta .fb-chip { position: relative; z-index: 1; }

    /* The vote button */
    .fb-vote { margin: 0; }
    .fb .fb-vote-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .05rem; width: 3.4rem; height: 3.6rem; padding: 0; border-radius: var(--feedback-radius); border: 1px solid var(--feedback-line); background: var(--feedback-bg); color: var(--feedback-ink); font: 600 .95rem/1 var(--feedback-sans); font-variant-numeric: tabular-nums; cursor: pointer; box-shadow: none; transition: border-color .15s, background .15s, color .15s, transform .1s; }
    .fb .fb-vote-btn svg { width: 1.05rem; height: 1.05rem; color: var(--feedback-text-3); transition: transform .15s; }
    .fb .fb-vote-btn:hover { border-color: var(--feedback-accent); background: var(--feedback-accent-soft); color: var(--feedback-accent-ink); text-decoration: none; }
    .fb .fb-vote-btn:hover svg { color: var(--feedback-accent); transform: translateY(-2px); }
    .fb .fb-vote-btn:active { transform: scale(.96); }
    .fb .fb-vote-btn[aria-pressed=true] { background: var(--feedback-accent); border-color: var(--feedback-accent); color: var(--feedback-on-accent); box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--feedback-accent) 70%, transparent); }
    .fb .fb-vote-btn[aria-pressed=true] svg { color: var(--feedback-on-accent); }
    .fb .fb-vote-btn.is-locked { cursor: default; background: var(--feedback-subtle); }
    .fb .fb-vote-btn.is-locked:hover { border-color: var(--feedback-line); color: var(--feedback-ink); }
    .fb .fb-vote-btn.is-locked:hover svg { transform: none; color: var(--feedback-text-3); }
    .fb .fb-vote-btn--big { width: 4.25rem; height: 4.5rem; font-size: 1.2rem; }

    /* Chips, avatars, buttons */
    .fb-chip { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; padding: .1rem .6rem; font-size: .75rem; font-weight: 500; line-height: 1.6; white-space: nowrap; background: var(--fb-tone-soft, var(--feedback-accent-soft)); color: var(--fb-tone, var(--feedback-accent-ink)) !important; }
    .fb a.fb-chip:hover { text-decoration: none; filter: brightness(.97); }
    .fb-chip--status::before { content: ""; width: .4rem; height: .4rem; border-radius: 50%; background: var(--fb-tone-dot, currentColor); }
    .fb-chip--quiet { background: var(--feedback-muted); color: var(--feedback-text-2) !important; }
    .fb-chip--outline { background: transparent; box-shadow: inset 0 0 0 1px var(--feedback-line); color: var(--feedback-text-2) !important; }
    .fb-chip--team { background: var(--feedback-accent); color: var(--feedback-on-accent) !important; font-size: .68rem; padding: 0 .45rem; }
    .fb-avatar { display: inline-grid; place-items: center; width: 2rem; height: 2rem; border-radius: 999px; background: var(--feedback-accent-soft); color: var(--feedback-accent-ink); font: 600 .7rem/1 var(--feedback-sans); flex: none; }
    .fb-avatar--small { width: 1.6rem; height: 1.6rem; font-size: .6rem; }
    .fb-avatars { display: flex; flex-wrap: wrap; gap: .25rem; }
    .fb-avatars .fb-avatar { letter-spacing: -.02em; }

    .fb .fb-btn, .fb button { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; font: 500 .875rem/1 var(--feedback-sans); padding: .62rem .95rem; border-radius: var(--feedback-radius); border: 1px solid var(--feedback-accent); background: var(--feedback-accent); color: var(--feedback-on-accent); cursor: pointer; box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--feedback-accent) 70%, transparent); text-decoration: none; }
    .fb .fb-btn:hover, .fb button:hover { background: var(--feedback-accent-ink); border-color: var(--feedback-accent-ink); text-decoration: none; }
    .fb .fb-btn svg, .fb button svg { width: 1rem; height: 1rem; }
    .fb .fb-btn--quiet, .fb button.fb-btn--quiet { background: transparent; color: var(--feedback-ink); border-color: var(--feedback-line); box-shadow: none; }
    .fb .fb-btn--quiet:hover, .fb button.fb-btn--quiet:hover { background: var(--feedback-accent-soft); border-color: var(--feedback-accent); color: var(--feedback-accent-ink); }
    .fb button.fb-btn--danger { background: transparent; color: var(--feedback-danger-ink); border-color: var(--feedback-line); box-shadow: none; }
    .fb button.fb-btn--danger:hover { background: var(--feedback-danger-soft); border-color: var(--feedback-danger); }
    .fb .fb-btn--small, .fb button.fb-btn--small { padding: .38rem .65rem; font-size: .8rem; }
    .fb button.fb-link { background: none; border: 0; padding: 0; box-shadow: none; color: var(--feedback-text-3); font-size: .8rem; font-weight: 500; }
    .fb button.fb-link:hover { background: none; color: var(--feedback-accent); text-decoration: underline; }
    .fb button.fb-link--danger:hover { color: var(--feedback-danger-ink); }

    /* Forms */
    .fb input[type=text], .fb input[type=search], .fb input[type=url], .fb input[type=number], .fb input[type=datetime-local], .fb select, .fb textarea { font: inherit; font-size: .925rem; color: inherit; background: var(--feedback-bg); border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius); padding: .55rem .75rem; width: 100%; }
    .fb input:focus, .fb textarea:focus, .fb select:focus { outline: none; border-color: var(--feedback-accent); box-shadow: 0 0 0 3px var(--feedback-accent-soft); }
    .fb textarea { min-height: 9rem; resize: vertical; line-height: 1.6; }
    .fb textarea.fb-code { font: .875rem/1.65 var(--feedback-mono); }
    .fb label { display: block; font-weight: 500; font-size: .85rem; margin: 1.25rem 0 .4rem; }
    .fb label.fb-check { display: flex; align-items: center; gap: .5rem; font-weight: 400; margin: .5rem 0 0; color: var(--feedback-text-2); }
    .fb input[type=checkbox] { accent-color: var(--feedback-accent); width: 1rem; height: 1rem; margin: 0; }
    .fb-hint { color: var(--feedback-text-3); font-size: .8rem; margin: .4rem 0 0; }
    .fb-error { color: var(--feedback-danger-ink); font-size: .85rem; margin: .4rem 0 0; }
    .fb-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-top: 1.25rem; }
    .fb-honey { position: absolute !important; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    .fb-flash { display: flex; align-items: center; gap: .6rem; background: var(--feedback-subtle); border: 1px solid var(--feedback-line); padding: .6rem .9rem; border-radius: var(--feedback-radius); margin: 0 0 1.5rem; font-size: .9rem; }
    .fb-flash::before { content: ""; width: .5rem; height: .5rem; border-radius: 50%; background: var(--feedback-accent); flex: none; }
    .fb-flash--error::before { background: var(--feedback-danger); }
    .fb-empty { padding: 2.5rem 1rem; text-align: center; color: var(--feedback-text-3); border: 1px dashed var(--feedback-line); border-radius: var(--feedback-radius-lg); margin: 0; }
    .fb-empty strong { display: block; color: var(--feedback-ink); font-weight: 600; margin-bottom: .2rem; }
    .fb nav[role=navigation] { margin-top: 1.5rem; font-size: .875rem; }
    .fb nav[role=navigation] svg { width: 1rem; height: 1rem; }
    .fb-muted { color: var(--feedback-text-3); }

    /* Similar requests while typing a title */
    .fb-similar { margin-top: .75rem; border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius-lg); padding: .35rem; background: var(--feedback-subtle); }
    .fb-similar h2 { font-size: .8rem; font-weight: 500; color: var(--feedback-text-3); letter-spacing: 0; margin: .35rem .6rem .4rem; }
    .fb-similar ul { list-style: none; margin: 0; padding: 0; }
    .fb .fb-similar a { display: flex; align-items: center; gap: .75rem; padding: .5rem .6rem; border-radius: var(--feedback-radius); color: var(--feedback-ink); font-size: .9rem; }
    .fb .fb-similar a:hover { background: var(--feedback-bg); text-decoration: none; }
    .fb-similar-votes { display: inline-grid; place-items: center; min-width: 2.4rem; padding: .15rem .4rem; border-radius: 6px; background: var(--feedback-bg); border: 1px solid var(--feedback-line); font-size: .78rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .fb-similar-title { flex: 1; min-width: 0; overflow-wrap: anywhere; }

    /* Request page */
    .fb-crumbs { display: flex; flex-wrap: wrap; gap: .35rem; list-style: none; margin: 0 0 1.25rem; padding: 0; font-size: .85rem; color: var(--feedback-text-3); }
    .fb-crumbs li + li::before { content: "/"; margin-right: .35rem; color: var(--feedback-line); }
    .fb .fb-crumbs a { color: var(--feedback-text-2); }
    .fb-request-head { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 1.25rem; align-items: start; }
    .fb-request-head h1 { font-size: clamp(1.45rem, 3vw, 1.9rem); margin: .1rem 0 0; overflow-wrap: anywhere; letter-spacing: -.03em; }
    .fb-byline { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; color: var(--feedback-text-3); font-size: .85rem; margin-top: .6rem; }
    .fb-prose { font-size: 1rem; line-height: 1.75; overflow-wrap: break-word; color: var(--feedback-ink); }
    .fb-prose > :first-child { margin-top: 0; }
    .fb-prose > :last-child { margin-bottom: 0; }
    .fb-prose h2 { font-size: 1.3rem; margin: 2rem 0 .6rem; }
    .fb-prose h3 { font-size: 1.1rem; margin: 1.5rem 0 .5rem; }
    .fb .fb-prose a { text-decoration: underline; text-decoration-color: color-mix(in srgb, currentColor 30%, transparent); text-underline-offset: 3px; }
    .fb-prose code { font: .85em var(--feedback-mono); background: var(--feedback-muted); padding: .15em .4em; border-radius: 5px; }
    .fb-prose pre { background: var(--feedback-subtle); border: 1px solid var(--feedback-line); padding: .9rem 1.1rem; border-radius: var(--feedback-radius-lg); overflow-x: auto; line-height: 1.55; }
    .fb-prose pre code { background: none; padding: 0; font-size: .85rem; }
    .fb-prose blockquote { margin: 1.25rem 0; padding: .7rem 1.1rem; background: var(--feedback-accent-soft); border-radius: var(--feedback-radius-lg); }
    .fb-prose blockquote p { margin: .3rem 0; }
    .fb-prose img { max-width: 100%; height: auto; border-radius: var(--feedback-radius); border: 1px solid var(--feedback-line); }
    .fb-prose table { border-collapse: collapse; display: block; overflow-x: auto; font-size: .9rem; }
    .fb-prose th, .fb-prose td { border: 1px solid var(--feedback-line); padding: .45rem .8rem; }
    .fb-prose th { background: var(--feedback-subtle); font-weight: 500; text-align: left; }
    .fb-prose li + li { margin-top: .25rem; }
    .fb-prose ul li::marker { color: var(--feedback-accent); }
    .fb-request-body { margin: 1.75rem 0 0; padding-left: calc(4.25rem + 1.25rem); }
    .fb-update { margin-top: 2rem; padding: 1.1rem 1.25rem; border-radius: var(--feedback-radius-lg); background: var(--feedback-accent-soft); }
    .fb-update-head { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .85rem; color: var(--feedback-text-2); margin-bottom: .5rem; }
    .fb-update-head strong { color: var(--feedback-accent-ink); font-weight: 600; }
    .fb-update .fb-prose { font-size: .95rem; }
    .fb-shipped { display: flex; align-items: center; gap: 1rem; margin-top: 1.25rem; padding: 1rem 1.2rem; border-radius: var(--feedback-radius-lg); border: 1px solid color-mix(in srgb, var(--feedback-success) 30%, var(--feedback-line)); background: var(--feedback-success-soft); }
    .fb-shipped-icon { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: 50%; background: var(--feedback-success); color: var(--feedback-bg); flex: none; }
    .fb-shipped-icon svg { width: 1.1rem; height: 1.1rem; }
    .fb-shipped p { margin: 0; font-size: .9rem; color: var(--feedback-text-2); }
    .fb-shipped strong { display: block; color: var(--feedback-ink); font-size: .95rem; }
    .fb-section { margin-top: 2.75rem; }
    .fb-section > h2 { font-size: 1.1rem; margin: 0 0 1rem; display: flex; align-items: baseline; gap: .5rem; }
    .fb-section > h2 small { color: var(--feedback-text-3); font-weight: 400; font-size: .85rem; letter-spacing: 0; }

    .fb-history { list-style: none; margin: 0; padding: 0; position: relative; }
    .fb-history li { position: relative; display: grid; grid-template-columns: 1rem minmax(0, 1fr); gap: .75rem; padding-bottom: 1rem; font-size: .875rem; color: var(--feedback-text-2); }
    .fb-history li::before { content: ""; position: absolute; left: calc(.5rem - 1px); top: 1.1rem; bottom: 0; width: 2px; background: var(--feedback-line); }
    .fb-history li:last-child::before { display: none; }
    .fb-history .fb-dot { margin: .45rem auto 0; width: .65rem; height: .65rem; box-shadow: 0 0 0 3px var(--feedback-bg); }
    .fb-history time { color: var(--feedback-text-3); font-size: .8rem; }
    .fb-history .fb-prose { font-size: .875rem; margin-top: .3rem; color: var(--feedback-text-2); }

    /* Comments */
    .fb-comments { list-style: none; margin: 0; padding: 0; }
    .fb-comment { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .8rem; padding: 1rem 0; border-top: 1px solid var(--feedback-line); }
    .fb-comment .fb-comments .fb-comment { border-top: 0; padding: .9rem 0 0; }
    .fb-comment-head { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem; font-size: .85rem; }
    .fb-comment-head strong { font-weight: 600; }
    .fb-comment-head time { color: var(--feedback-text-3); }
    .fb-comment.is-official > div > .fb-prose { background: var(--feedback-accent-soft); padding: .6rem .85rem; border-radius: var(--feedback-radius); }
    .fb-comment .fb-prose { font-size: .95rem; line-height: 1.65; margin-top: .3rem; }
    .fb-comment-tools { display: flex; gap: 1rem; margin-top: .4rem; }
    .fb-comment-tools form { display: inline; margin: 0; }
    .fb .fb-comment-tools a { color: var(--feedback-text-3); font-size: .8rem; font-weight: 500; }
    .fb .fb-comment-tools a:hover { color: var(--feedback-accent); }
    .fb-compose { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .8rem; padding: 1rem 0 0; }
    .fb-compose textarea { min-height: 5.5rem; }
    .fb-compose .fb-row { margin-top: .6rem; justify-content: space-between; }
    .fb-signin { padding: 1rem 1.2rem; border-radius: var(--feedback-radius-lg); background: var(--feedback-subtle); color: var(--feedback-text-2); font-size: .9rem; margin: 0; }

    .fb-voters { display: grid; gap: .5rem; }
    .fb-voters p { margin: 0; font-size: .85rem; color: var(--feedback-text-2); }
    .fb-facts { display: grid; gap: .9rem; margin: 0; font-size: .875rem; }
    .fb-facts dt { color: var(--feedback-text-3); font-size: .8rem; margin-bottom: .2rem; }
    .fb-facts dd { margin: 0; display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
    .fb-manage { display: grid; gap: .25rem; }
    .fb-manage form { margin: 0; }
    .fb-manage label { margin-top: .75rem; }
    .fb-manage textarea { min-height: 5rem; font-size: .875rem; }
    .fb-manage .fb-row { margin-top: .75rem; }
    .fb-manage details { border-top: 1px solid var(--feedback-line); padding-top: .75rem; margin-top: .75rem; }
    .fb-manage summary { cursor: pointer; font-size: .875rem; font-weight: 500; color: var(--feedback-text-2); }

    /* Roadmap */
    .fb-roadmap { display: grid; grid-template-columns: repeat(var(--fb-columns, 3), minmax(0, 1fr)); gap: 1.25rem; align-items: start; }
    .fb-column { background: var(--feedback-subtle); border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius-lg); padding: .9rem; }
    .fb-column > h2 { display: flex; align-items: center; gap: .55rem; font-size: .95rem; margin: .1rem .25rem .85rem; }
    .fb-column > h2 .fb-count { margin-left: auto; font-size: .78rem; font-weight: 500; color: var(--feedback-text-3); background: var(--feedback-bg); border: 1px solid var(--feedback-line); border-radius: 999px; padding: 0 .55rem; letter-spacing: 0; }
    .fb-column ol { list-style: none; margin: 0; padding: 0; display: grid; gap: .6rem; }
    .fb-rm-card { position: relative; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .75rem; align-items: start; padding: .8rem .9rem; background: var(--feedback-bg); border: 1px solid var(--feedback-line); border-radius: 10px; }
    .fb-rm-card:hover { border-color: color-mix(in srgb, var(--feedback-accent) 45%, var(--feedback-line)); }
    .fb .fb-rm-card a { color: var(--feedback-ink); font-weight: 500; font-size: .9rem; line-height: 1.4; }
    .fb .fb-rm-card a::after { content: ""; position: absolute; inset: 0; }
    .fb .fb-rm-card a:hover { color: var(--feedback-accent); text-decoration: none; }
    .fb-rm-card .fb-meta { margin-top: .35rem; }
    .fb-rm-votes { display: grid; justify-items: center; min-width: 2.4rem; padding: .25rem .35rem; border-radius: 7px; background: var(--feedback-muted); font-size: .8rem; font-weight: 600; font-variant-numeric: tabular-nums; line-height: 1.2; }
    .fb-rm-votes svg { width: .75rem; height: .75rem; color: var(--feedback-text-3); }
    .fb-rm-votes.is-voted { background: var(--feedback-accent-soft); color: var(--feedback-accent-ink); }
    .fb-rm-votes.is-voted svg { color: var(--feedback-accent); }
    .fb-column .fb-empty { padding: 1.5rem .75rem; font-size: .85rem; background: var(--feedback-bg); }

    /* Changelog */
    .fb-log { list-style: none; margin: 0; padding: 0; }
    .fb-log > li { display: grid; grid-template-columns: 10rem minmax(0, 1fr); gap: 2rem; padding: 2.25rem 0; border-top: 1px solid var(--feedback-line); }
    .fb-log > li:first-child { border-top: 0; padding-top: .5rem; }
    .fb-log-date { position: sticky; top: 8rem; align-self: start; color: var(--feedback-text-3); font-size: .875rem; padding-top: .2rem; }
    .fb-log-date time { display: block; color: var(--feedback-text-2); font-weight: 500; }
    .fb-new { display: inline-flex; align-items: center; gap: .35rem; margin-top: .4rem; color: var(--feedback-accent); font-size: .78rem; font-weight: 600; }
    .fb-new::before { content: ""; width: .45rem; height: .45rem; border-radius: 50%; background: var(--feedback-accent); box-shadow: 0 0 0 3px var(--feedback-accent-soft); }
    .fb-labels { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: .6rem; }
    .fb-log h2 { font-size: 1.4rem; letter-spacing: -.03em; margin: 0 0 1rem; }
    .fb .fb-log h2 a { color: var(--feedback-ink); }
    .fb .fb-log h2 a:hover { color: var(--feedback-accent); text-decoration: none; }
    .fb-cover { display: block; width: 100%; aspect-ratio: 2 / 1; object-fit: cover; border-radius: var(--feedback-radius-lg); border: 1px solid var(--feedback-line); margin: 0 0 1.25rem; background: var(--feedback-subtle); }
    .fb-asked { margin-top: 1.5rem; padding: .9rem 1.1rem; border-radius: var(--feedback-radius-lg); background: var(--feedback-subtle); border: 1px solid var(--feedback-line); }
    .fb-asked h3 { font-size: .8rem; font-weight: 500; color: var(--feedback-text-3); letter-spacing: 0; margin: 0 0 .5rem; }
    .fb-asked ul { list-style: none; margin: 0; padding: 0; display: grid; gap: .35rem; }
    .fb-asked li { display: flex; align-items: center; gap: .6rem; font-size: .9rem; }
    .fb-asked .fb-chip { margin-left: auto; }
    .fb-feeds { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; font-size: .85rem; }
    .fb-pager { display: flex; justify-content: space-between; gap: 1rem; margin-top: 3rem; padding-top: 1.25rem; border-top: 1px solid var(--feedback-line); font-size: .9rem; }
    .fb-pager a { display: grid; gap: .1rem; max-width: 48%; }
    .fb-pager a small { color: var(--feedback-text-3); font-size: .78rem; }
    .fb-pager a:last-child { text-align: right; margin-left: auto; }

    /* Admin area */
    .fb-admin-tabs { display: flex; flex-wrap: wrap; gap: .25rem; margin: 0 0 1.75rem; border-bottom: 1px solid var(--feedback-line); }
    .fb .fb-admin-tabs a { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem .9rem; margin-bottom: -1px; border-bottom: 2px solid transparent; color: var(--feedback-text-2); font-size: .9rem; font-weight: 500; }
    .fb .fb-admin-tabs a:hover { color: var(--feedback-ink); text-decoration: none; }
    .fb .fb-admin-tabs a[aria-current] { color: var(--feedback-accent-ink); border-bottom-color: var(--feedback-accent); }
    .fb-filters { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin: 0 0 1.25rem; }
    .fb-filters input[type=search] { width: auto; min-width: 14rem; flex: 1 1 14rem; max-width: 24rem; }
    .fb-filters select { width: auto; }
    .fb-table-wrap { overflow-x: auto; border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius-lg); }
    .fb-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    .fb-table th { text-align: left; font-weight: 500; color: var(--feedback-text-2); background: var(--feedback-subtle); font-size: .8rem; white-space: nowrap; }
    .fb-table th, .fb-table td { padding: .7rem .9rem; border-bottom: 1px solid var(--feedback-line); vertical-align: middle; }
    .fb-table tr:last-child td { border-bottom: 0; }
    .fb-table td.num, .fb-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fb-table tbody tr:hover { background: linear-gradient(90deg, var(--feedback-accent-soft), transparent 70%); }
    .fb-table a { font-weight: 500; }
    .fb-table form { display: inline; margin: 0; }
    .fb-table small { display: block; color: var(--feedback-text-3); font-size: .78rem; }
    .fb-actions { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: flex-end; }

    .fb-editor { display: grid; gap: 1rem 2.5rem; }
    .fb-editor-preview { min-width: 0; border-top: 1px solid var(--feedback-line); padding-top: 1rem; }
    .fb-editor textarea[name=body] { min-height: 22rem; }
    .fb-fields { display: grid; gap: 0 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); }
    .fb-checks { display: flex; flex-wrap: wrap; gap: .25rem 1.25rem; }
    .fb-picklist { max-height: 15rem; overflow-y: auto; border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius); padding: .35rem .75rem .6rem; }
    .fb-picklist label.fb-check { font-size: .875rem; }
    .fb-picklist .fb-chip { margin-left: auto; }
    @media (min-width: 64rem) {
        .fb-editor { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
        .fb-editor-preview { border-top: 0; border-left: 1px solid var(--feedback-line); padding: 0 0 0 2.5rem; }
        .fb-editor textarea[name=body] { min-height: 50vh; }
    }
    .fb-taxonomy { display: grid; gap: 2rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); align-items: start; }
    .fb-tax-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; padding: .6rem 0; border-bottom: 1px solid var(--feedback-line); }
    .fb-tax-row form { display: flex; gap: .5rem; align-items: center; margin: 0; }
    .fb-tax-row form:first-child { flex: 1 1 12rem; }
    .fb-tax-row input[type=text] { flex: 1; }
    .fb-tax-row input[type=number] { width: 4.5rem; }
    .fb-stats { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); margin-bottom: 2rem; }
    .fb-stat { border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius-lg); padding: 1rem 1.15rem; }
    .fb-stat small { color: var(--feedback-text-3); font-size: .8rem; }
    .fb-stat strong { display: block; font-size: 1.6rem; font-weight: 600; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
    .fb-chart { display: grid; grid-template-columns: repeat(var(--fb-weeks, 12), minmax(0, 1fr)); gap: .4rem; align-items: end; height: 11rem; padding: 1rem 1rem .5rem; border: 1px solid var(--feedback-line); border-radius: var(--feedback-radius-lg); }
    .fb-chart-col { display: grid; grid-template-rows: 1fr auto; gap: .35rem; height: 100%; text-align: center; font-size: .7rem; color: var(--feedback-text-3); font-variant-numeric: tabular-nums; }
    .fb-chart-bar { align-self: end; border-radius: 5px 5px 2px 2px; background: linear-gradient(var(--feedback-accent), color-mix(in srgb, var(--feedback-accent) 70%, var(--feedback-accent-2))); min-height: 2px; position: relative; }
    .fb-chart-bar span { position: absolute; bottom: calc(100% + .2rem); left: 0; right: 0; color: var(--feedback-text-2); font-weight: 500; }
    .fb-meter { display: inline-block; width: 6rem; height: .4rem; border-radius: 999px; background: var(--feedback-muted); vertical-align: middle; overflow: hidden; margin-right: .5rem; }
    .fb-meter span { display: block; height: 100%; background: var(--feedback-accent); border-radius: inherit; }

    @media (max-width: 63.99rem) {
        .fb-cols, .fb-cols--left { grid-template-columns: minmax(0, 1fr); gap: 1.75rem; }
        .fb-side { position: static; }
        .fb-cols .fb-side--filters { order: -1; }
        .fb-roadmap { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 47.99rem) {
        .fb-bar, .fb-tabs, .fb-page, .fb-foot { padding-inline: 16px; }
        .fb-page { padding-top: 1.5rem; }
        .fb-log > li { grid-template-columns: minmax(0, 1fr); gap: .5rem; padding: 1.75rem 0; }
        .fb-log-date { position: static; }
        .fb-log-date time { display: inline; margin-right: .5rem; }
        .fb-request-body { padding-left: 0; }
        .fb-card { padding: .85rem; gap: .8rem; }
        .fb .fb-vote-btn { width: 3rem; height: 3.3rem; }
        .fb .fb-vote-btn--big { width: 3.6rem; height: 4rem; }
        .fb-toolbar .fb-seg { width: 100%; }
        .fb-toolbar .fb-seg a { flex: 1; text-align: center; }
    }
</style>
