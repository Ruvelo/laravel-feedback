// The demo is static files, so this script answers what the server would:
// votes (remembered in this browser only), the board's filters, sorting and
// search, and "Similar requests". window.FEEDBACK_INDEX is written by
// build.php. Anything else that would post shows a note instead.
(() => {
    const requests = window.FEEDBACK_INDEX || [];
    const openStatuses = window.FEEDBACK_OPEN || ['under_review', 'planned', 'in_progress'];
    const byId = new Map(requests.map((r) => [String(r.id), r]));
    const KEY = 'feedback-demo-votes';
    const STOP = ['the', 'and', 'for', 'with', 'add', 'can', 'you', 'please', 'would', 'like', 'able', 'allow', 'option', 'support', 'feature', 'from', 'into', 'our', 'your', 'when', 'should', 'have', 'more', 'all', 'not', 'are', 'way', 'let', 'use', 'get', 'new'];

    const load = () => { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } };
    const save = (votes) => { try { localStorage.setItem(KEY, JSON.stringify(votes)); } catch (e) {} };
    const idFrom = (url) => (String(url).match(/\/requests\/(\d+)/) || [])[1];

    const words = (text) => {
        let list = [...new Set(text.toLowerCase().split(/[^\p{L}\p{N}]+/u).filter(Boolean))];
        const meaningful = list.filter((w) => w.length > 2 && !STOP.includes(w));
        if (meaningful.length) list = meaningful;
        return list.sort((a, b) => b.length - a.length).slice(0, 8);
    };

    const toast = (message) => {
        let box = document.getElementById('fb-demo-toast');
        if (!box) {
            box = Object.assign(document.createElement('div'), { id: 'fb-demo-toast', role: 'status' });
            box.style.cssText = 'position:fixed;left:50%;bottom:24px;translate:-50% 0;z-index:9999;max-width:calc(100vw - 32px);background:#16162a;color:#fff;padding:.7rem 1rem;border-radius:10px;font:500 .875rem/1.4 ui-sans-serif,system-ui,sans-serif;box-shadow:0 12px 30px -12px rgb(0 0 0 / .5)';
            document.body.append(box);
        }
        box.textContent = message;
        clearTimeout(box.timer);
        box.hidden = false;
        box.timer = setTimeout(() => { box.hidden = true; }, 3800);
    };

    // Votes cast in this browser, applied over what the page was built with.
    const applyVotes = (root) => {
        const votes = load();
        root.querySelectorAll('[data-feedback-vote]').forEach((form) => {
            const id = idFrom(form.action);
            if (!(id in votes)) return;
            const button = form.querySelector('button');
            const pressed = button.getAttribute('aria-pressed') === 'true';
            if (pressed === votes[id]) return;
            const count = button.querySelector('[data-feedback-count]');
            const n = Math.max(0, (Number(count.textContent.replace(/\D/g, '')) || 0) + (votes[id] ? 1 : -1));
            count.textContent = n.toLocaleString();
            button.setAttribute('aria-pressed', votes[id] ? 'true' : 'false');
            form.querySelector('input[name=vote]').value = votes[id] ? '0' : '1';
        });
    };

    // The board: filters, sorting and search from the query string.
    const board = (root) => {
        const container = root.querySelector('[data-feedback-board]');
        const list = root.querySelector('[data-feedback-list]');
        if (!container || !list) return;
        const empty = root.querySelector('[data-feedback-empty]');
        const search = root.querySelector('[data-feedback-search]');
        const cards = [...list.querySelectorAll('[data-feedback-card]')];

        // The page was built with every request; fix the "Open" count.
        const counts = {};
        requests.forEach((r) => { counts[r.status] = (counts[r.status] || 0) + 1; });
        const openLink = root.querySelector('[data-filter=status][data-value=open] .fb-count');
        if (openLink) openLink.textContent = openStatuses.reduce((sum, s) => sum + (counts[s] || 0), 0);

        let summary = root.querySelector('[data-feedback-active]');
        if (!summary) {
            summary = Object.assign(document.createElement('p'), { className: 'fb-active' });
            summary.dataset.feedbackActive = '';
            list.before(summary);
        }

        const read = () => {
            const params = new URLSearchParams(location.search);
            return {
                status: params.get('status') || 'open',
                category: params.get('category') || '',
                tag: params.get('tag') || '',
                q: params.get('q') || '',
                sort: params.get('sort') || 'top',
            };
        };

        const write = (state) => {
            const params = new URLSearchParams();
            if (state.status !== 'open') params.set('status', state.status);
            if (state.category) params.set('category', state.category);
            if (state.tag) params.set('tag', state.tag);
            if (state.q) params.set('q', state.q);
            if (state.sort !== 'top') params.set('sort', state.sort);
            const query = params.toString();
            history.replaceState(null, '', location.pathname + (query ? '?' + query : ''));
        };

        const apply = () => {
            const state = read();
            const terms = words(state.q);
            const matches = (r) =>
                (state.status === 'all' || (state.status === 'open' ? openStatuses.includes(r.status) : r.status === state.status))
                && (!state.category || r.category === state.category)
                && (!state.tag || r.tags.includes(state.tag))
                && terms.every((t) => r.text.includes(t));
            const rank = {
                top: (a, b) => b.votes - a.votes || b.id - a.id,
                new: (a, b) => b.created - a.created || b.id - a.id,
                trending: (a, b) => b.trending - a.trending || b.votes - a.votes || b.id - a.id,
            }[state.sort] || ((a, b) => b.votes - a.votes);
            const pinnedFirst = !state.q;

            const ordered = cards
                .map((card) => ({ card, r: byId.get(card.dataset.id) }))
                .filter((x) => x.r)
                .sort((a, b) => (pinnedFirst ? (b.r.pinned - a.r.pinned) : 0) || rank(a.r, b.r));
            let shown = 0;
            ordered.forEach(({ card, r }) => {
                const show = matches(r);
                card.hidden = !show;
                if (show) shown++;
                list.append(card);
            });
            if (empty) empty.hidden = shown > 0;

            root.querySelectorAll('[data-filter]').forEach((link) => {
                const current = state[link.dataset.filter] === link.dataset.value;
                link.toggleAttribute('aria-current', current);
                if (current) link.setAttribute('aria-current', 'true');
                if (link.dataset.filter === 'tag') link.classList.toggle('fb-chip--outline', !current);
            });

            const filtered = state.status !== 'open' || state.category || state.tag || state.q;
            summary.hidden = !filtered;
            summary.replaceChildren(shown + (shown === 1 ? ' request' : ' requests') + (state.q ? ' matching “' + state.q + '”' : '') + ' · ');
            const clear = Object.assign(document.createElement('a'), { href: location.pathname, textContent: 'Clear filters' });
            clear.addEventListener('click', (e) => { e.preventDefault(); write({ status: 'open', category: '', tag: '', q: '', sort: state.sort }); if (search) search.value = ''; apply(); });
            summary.append(clear);
            if (search && document.activeElement !== search) search.value = state.q;
        };

        root.addEventListener('click', (event) => {
            const link = event.target.closest('[data-filter]');
            if (!link || event.metaKey || event.ctrlKey) return;
            event.preventDefault();
            const state = read();
            const value = link.dataset.value;
            state[link.dataset.filter] = link.dataset.filter === 'tag' && state.tag === value ? '' : value;
            write(state);
            apply();
        });

        if (search) {
            search.form.addEventListener('submit', (e) => e.preventDefault());
            search.addEventListener('input', () => { write({ ...read(), q: search.value.trim() }); apply(); });
        }

        apply();
    };

    // A comment shows up on the page, for this visit only.
    const comments = (root) => {
        root.querySelectorAll('.fb-compose').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                event.stopImmediatePropagation();
                const textarea = form.querySelector('textarea');
                const text = textarea.value.trim();
                if (!text) return;
                const item = document.createElement('li');
                item.className = 'fb-comment';
                item.innerHTML = '<span class="fb-avatar" aria-hidden="true">KM</span><div><div class="fb-comment-head"><strong>Kenji Mori</strong><time>just now</time></div><div class="fb-prose"></div></div>';
                const prose = item.querySelector('.fb-prose');
                text.split(/\n{2,}/).forEach((para) => prose.append(Object.assign(document.createElement('p'), { textContent: para })));
                const replyBox = form.closest('[data-feedback-reply-form]');
                let list = replyBox ? replyBox.previousElementSibling : root.querySelector('#comments > .fb-comments');
                if (!list) {
                    list = Object.assign(document.createElement('ol'), { className: 'fb-comments' });
                    form.closest('section').append(list);
                }
                list.append(item);
                textarea.value = '';
                if (replyBox) replyBox.hidden = true;
                toast('Comment added on this page only: it’s a demo, so nothing is saved.');
            }, true);
        });
    };

    window.FeedbackDemo = {
        vote(url, wanted) {
            const votes = load();
            const id = idFrom(url);
            if (id) votes[id] = wanted;
            save(votes);
        },

        similar(query) {
            const terms = words(query);
            const scored = requests
                .map((r) => ({ r, score: terms.filter((t) => r.title.toLowerCase().includes(t)).reduce((sum, t) => sum + t.length, 0) }))
                .filter((x) => x.score > 0)
                .sort((a, b) => b.score - a.score || b.r.votes - a.r.votes)
                .slice(0, 5);
            return { query, results: scored.map(({ r }) => ({ title: r.title, url: r.url, votes: r.votes, status_label: r.status_label, tone: r.tone })) };
        },

        enhance(root) {
            applyVotes(root);
            board(root);
            comments(root);

            // Everything else that would post: say it's read-only.
            root.addEventListener('submit', (event) => {
                const form = event.target;
                if (form.matches('[data-feedback-vote], .fb-search, .fb-compose') || (form.method || '').toLowerCase() !== 'post') return;
                event.preventDefault();
                event.stopImmediatePropagation();
                toast(form.matches('[data-feedback-create]')
                    ? 'This is a demo, so nothing was posted. Install the package to collect requests.'
                    : 'This is a read-only demo: nothing changes. Install the package to try it for real.');
            }, true);
        },
    };
})();
