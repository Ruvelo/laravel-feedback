<script>
    // Progressive enhancement only: every feature here also works as a
    // plain link or form without JavaScript.
    (() => {
        const root = document.getElementById('feedback');
        if (!root || root.dataset.enhanced) return;
        root.dataset.enhanced = '1';

        // A static demo answers instead of the server (see demo/).
        const demo = window.FeedbackDemo || null;
        const csrf = root.querySelector('input[name=_token]')?.value || '';
        const plural = (n, word) => n.toLocaleString() + ' ' + word + (n === 1 ? '' : 's');

        // Votes without a reload.
        root.addEventListener('submit', async (event) => {
            const form = event.target.closest('[data-feedback-vote]');
            if (!form) return;
            event.preventDefault();
            const button = form.querySelector('button');
            const input = form.querySelector('input[name=vote]');
            const wanted = input.value === '1';
            const count = button.querySelector('[data-feedback-count]');
            const title = (button.getAttribute('aria-label') || '').replace(/^(Remove your vote for|Vote for) /, '').replace(/, [\d,.\s]+votes?$/, '');
            const paint = (voted, votes) => {
                button.setAttribute('aria-pressed', voted ? 'true' : 'false');
                input.value = voted ? '0' : '1';
                count.textContent = votes.toLocaleString();
                button.setAttribute('aria-label', (voted ? 'Remove your vote for ' : 'Vote for ') + title + ', ' + plural(votes, 'vote'));
            };
            const before = Number(count.textContent.replace(/\D/g, '')) || 0;
            paint(wanted, Math.max(0, before + (wanted ? 1 : -1)));
            if (demo) { demo.vote(form.action, wanted); return; }
            try {
                const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw response;
                const data = await response.json();
                paint(data.voted, data.votes);
            } catch (error) {
                paint(!wanted, before);
                form.submit();
            }
        });

        // "Similar requests" while typing a title.
        const titleInput = root.querySelector('[data-feedback-similar]');
        const similar = root.querySelector('[data-feedback-similar-list]');
        if (titleInput && similar) {
            let timer, pending;
            const list = similar.querySelector('ul');
            const show = (results) => {
                list.replaceChildren(...results.map((result) => {
                    const item = document.createElement('li');
                    const link = document.createElement('a');
                    link.href = result.url;
                    const votes = Object.assign(document.createElement('span'), { className: 'fb-similar-votes', textContent: result.votes });
                    const text = Object.assign(document.createElement('span'), { className: 'fb-similar-title', textContent: result.title });
                    const status = Object.assign(document.createElement('span'), { className: 'fb-chip fb-chip--status ' + (result.tone || ''), textContent: result.status_label });
                    if (result.tone_style) status.setAttribute('style', result.tone_style);
                    link.append(votes, text, status);
                    item.append(link);
                    return item;
                }));
                similar.hidden = results.length === 0;
            };
            titleInput.addEventListener('input', () => {
                clearTimeout(timer);
                const query = titleInput.value.trim();
                if (query.length < 3) { pending?.abort(); similar.hidden = true; return; }
                timer = setTimeout(async () => {
                    pending?.abort();
                    pending = new AbortController();
                    try {
                        const data = demo
                            ? demo.similar(query)
                            : await fetch(titleInput.dataset.feedbackSimilar + '?q=' + encodeURIComponent(query), { signal: pending.signal, headers: { Accept: 'application/json' } }).then((r) => r.json());
                        if (titleInput.value.trim() === query) show(data.results);
                    } catch (error) {
                        if (error.name !== 'AbortError') similar.hidden = true;
                    }
                }, 220);
            });
        }

        // Reply in place.
        root.addEventListener('click', (event) => {
            const link = event.target.closest('[data-feedback-reply]');
            if (!link) return;
            const box = root.querySelector('[data-feedback-reply-form="' + link.dataset.feedbackReply + '"]');
            if (!box) return;
            event.preventDefault();
            box.hidden = !box.hidden;
            if (!box.hidden) box.querySelector('textarea')?.focus();
        });

        // Managers: "Shipped in" only matters for shipped.
        const statusSelect = root.querySelector('[data-feedback-status-select]');
        const entryField = root.querySelector('[data-feedback-entry-field]');
        if (statusSelect && entryField) {
            const sync = () => { entryField.hidden = statusSelect.value !== 'shipped'; };
            statusSelect.addEventListener('change', sync);
            sync();
        }

        // Changelog editor: live preview a moment after typing stops.
        const editor = root.querySelector('[data-feedback-editor]');
        if (editor) {
            const body = editor.querySelector('textarea[name=body]');
            const pane = editor.querySelector('[data-feedback-preview]');
            const previewButton = editor.querySelector('[data-feedback-preview-button]');
            if (previewButton) previewButton.hidden = true;
            let timer, pending;
            body.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(async () => {
                    pending?.abort();
                    pending = new AbortController();
                    try {
                        const response = await fetch(editor.dataset.feedbackEditor, {
                            method: 'POST',
                            signal: pending.signal,
                            headers: { 'X-CSRF-TOKEN': editor.querySelector('[name=_token]').value, Accept: 'text/html' },
                            body: new URLSearchParams({ body: body.value }),
                        });
                        if (response.ok) pane.innerHTML = await response.text();
                    } catch (error) {
                        if (error.name !== 'AbortError') throw error;
                    }
                }, 300);
            });
        }

        demo?.enhance(root);
    })();
</script>
