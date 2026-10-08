<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Overview · Halyard</title>
    <meta name="description" content="A demo of ruvelo/laravel-feedback: the “What’s new” widget, inside an app.">
    <style>
        /* A stand-in for your app: Halyard, a made-up billing platform. Only “What’s new” comes from the package. */
        :root { --h-bg: #fff; --h-subtle: #f8f8fc; --h-line: #e4e4ef; --h-ink: #16162a; --h-text-2: #4b4b63; --h-text-3: #74748b; --h-accent: #3d4eff; --h-accent-2: #a78bfa; --h-soft: #eef0ff; --h-ok: #167a4a; --h-ok-soft: #e8f8ef; --h-bad: #e5384f; --h-bad-soft: #fdecef; --h-warn: #b26a00; --h-warn-soft: #fff4e0; color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root { --h-bg: #11111c; --h-subtle: #171725; --h-line: #2a2a3f; --h-ink: #f1f1f8; --h-text-2: #b6b6cc; --h-text-3: #8787a3; --h-accent: #8f9bff; --h-accent-2: #c4b5fd; --h-soft: #1e2150; --h-ok: #74d6a2; --h-ok-soft: #0f2b1f; --h-bad: #ff6b80; --h-bad-soft: #331520; --h-warn: #ffc266; --h-warn-soft: #2e2210; color-scheme: dark; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--h-bg); color: var(--h-ink); font: 15px/1.6 "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
        body::before { content: ""; position: fixed; inset: 0 0 auto; height: 3px; z-index: 20; background: linear-gradient(90deg, var(--h-accent), var(--h-accent-2)); }
        a { color: var(--h-accent); text-decoration: none; }
        .h-bar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 2rem; padding: .7rem max(16px, calc((100% - 72rem) / 2 + 24px)); background: color-mix(in srgb, var(--h-bg) 88%, transparent); backdrop-filter: blur(12px); border-bottom: 1px solid var(--h-line); }
        .h-brand { display: inline-flex; align-items: center; gap: .6rem; font-weight: 600; color: var(--h-ink); letter-spacing: -.01em; }
        .h-mark { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 7px; background: linear-gradient(135deg, var(--h-accent), var(--h-accent-2)); color: #fff; font-size: .85rem; font-weight: 700; }
        .h-nav { display: flex; gap: 1.25rem; font-size: .9rem; }
        .h-nav a { color: var(--h-text-2); }
        .h-nav a[aria-current] { color: var(--h-ink); font-weight: 500; }
        .h-right { margin-left: auto; display: flex; align-items: center; gap: .75rem; }
        .h-avatar { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; background: var(--h-soft); color: var(--h-accent); font-size: .72rem; font-weight: 600; }
        main { max-width: 72rem; margin: 0 auto; padding: 2.5rem 24px 4rem; }
        h1 { font-size: 1.875rem; font-weight: 600; letter-spacing: -.02em; line-height: 1.25; margin: 0; }
        .h-sub { color: var(--h-text-3); margin: .35rem 0 2rem; }
        .h-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .h-card { border: 1px solid var(--h-line); border-radius: 14px; padding: 1.1rem 1.25rem; }
        .h-card small { color: var(--h-text-3); font-size: .8rem; }
        .h-card strong { display: block; font-size: 1.6rem; font-weight: 600; letter-spacing: -.02em; margin: .15rem 0; font-variant-numeric: tabular-nums; }
        .h-card span { font-size: .8rem; color: var(--h-ok); }
        .h-card span.is-bad { color: var(--h-bad); }
        .h-tip { display: flex; gap: .75rem; align-items: flex-start; background: var(--h-soft); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 2rem; font-size: .9rem; color: var(--h-text-2); }
        .h-tip b { color: var(--h-accent); font-weight: 600; }
        .h-tip code { font: .85em "Geist Mono", ui-monospace, Menlo, monospace; background: var(--h-bg); padding: .1em .4em; border-radius: 5px; color: var(--h-ink); }
        h2 { font-size: 1rem; font-weight: 600; letter-spacing: -.01em; margin: 0 0 .75rem; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--h-line); border-radius: 14px; overflow: hidden; font-size: .9rem; }
        th, td { text-align: left; padding: .75rem 1rem; border-top: 1px solid var(--h-line); }
        thead th { border-top: 0; background: var(--h-subtle); color: var(--h-text-3); font-weight: 500; font-size: .8rem; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        th.num { text-align: right; }
        .h-chip { display: inline-block; padding: .05rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 500; }
        .h-chip.paid { background: var(--h-ok-soft); color: var(--h-ok); }
        .h-chip.failed { background: var(--h-bad-soft); color: var(--h-bad); }
        .h-chip.due { background: var(--h-warn-soft); color: var(--h-warn); }
        .h-muted { color: var(--h-text-3); }
        @media (max-width: 48rem) {
            .h-nav { display: none; }
            .h-bar { padding-inline: 16px; }
            main { padding: 1.5rem 16px 3rem; }
            .h-cards { grid-template-columns: minmax(0, 1fr); }
            .h-hide-sm { display: none; }
        }
        .h-feedback { color: var(--h-text-2); font-size: .875rem; font-weight: 500; }
        .h-feedback:hover { color: var(--h-ink); }
    </style>
</head>
<body>
    <header class="h-bar">
        <a class="h-brand" href="{{ url('/laravel-feedback/in-your-app') }}"><span class="h-mark" aria-hidden="true">H</span>Halyard</a>
        <nav class="h-nav" aria-label="Main">
            <a href="{{ url('/laravel-feedback/in-your-app') }}" aria-current="page">Overview</a>
            <a href="{{ url('/laravel-feedback/in-your-app') }}">Invoices</a>
            <a href="{{ url('/laravel-feedback/in-your-app') }}">Customers</a>
            <a href="{{ url('/laravel-feedback/in-your-app') }}">Reports</a>
        </nav>
        <div class="h-right">
            <a class="h-feedback h-hide-sm" href="{{ route('feedback.home') }}">Feedback</a>
            <x-feedback::whats-new :open="$open" data-feedback-demo />
            <span class="h-avatar" title="Kenji Mori">KM</span>
        </div>
    </header>

    <main>
        <h1>Good afternoon, Kenji</h1>
        <p class="h-sub">Here’s how October is going so far.</p>

        <div class="h-tip">
            <p style="margin:0"><b>Try “What’s new”</b> in the header. It’s <code>&lt;x-feedback::whats-new /&gt;</code> from Laravel Feedback: it counts the changelog entries Kenji hasn’t seen yet. <a href="{{ route('feedback.changelog') }}">Open the changelog</a> or <a href="{{ route('feedback.home') }}">the feedback board</a>.</p>
        </div>

        <div class="h-cards">
            <div class="h-card"><small>Monthly recurring revenue</small><strong>€48,210</strong><span>+4.1% on September</span></div>
            <div class="h-card"><small>Usage billed this month</small><strong>€7,940</strong><span>1.9M API calls metered</span></div>
            <div class="h-card"><small>Failed payments</small><strong>2</strong><span class="is-bad">€3,120 to recover</span></div>
        </div>

        <h2>Recent invoices</h2>
        <table>
            <thead><tr><th>Invoice</th><th>Customer</th><th class="h-hide-sm">Due</th><th class="num">Amount</th><th>Status</th></tr></thead>
            <tbody>
                <tr><td>INV-2291</td><td>Northwind Freight</td><td class="h-hide-sm h-muted">7 Oct</td><td class="num">€6,350.00</td><td><span class="h-chip due">Due</span></td></tr>
                <tr><td>INV-2288</td><td>Bluefin Studio</td><td class="h-hide-sm h-muted">8 Oct</td><td class="num">€4,800.00</td><td><span class="h-chip paid">Paid</span></td></tr>
                <tr><td>INV-2287</td><td>Lumen Health</td><td class="h-hide-sm h-muted">8 Oct</td><td class="num">€1,920.00</td><td><span class="h-chip failed">Failed</span></td></tr>
                <tr><td>INV-2284</td><td>Acme Corp</td><td class="h-hide-sm h-muted">6 Oct</td><td class="num">€2,400.00</td><td><span class="h-chip paid">Paid</span></td></tr>
                <tr><td>INV-2280</td><td>Mosaic</td><td class="h-hide-sm h-muted">4 Oct</td><td class="num">€1,200.00</td><td><span class="h-chip failed">Failed</span></td></tr>
            </tbody>
        </table>
    </main>
</body>
</html>
