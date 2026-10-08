<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers\Manage;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Controllers\Controller;
use Ruvelo\Feedback\Http\Resources\EntryResource;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Support\Palette;

class EntryController extends Controller
{
    public function index(): View
    {
        return view('feedback::manage.entries', [
            'entries' => Entry::query()->withCount('requests')->orderByRaw('case when published_at is null then 0 else 1 end')->orderByDesc('published_at')->orderByDesc('id')->paginate(30),
        ]);
    }

    public function create(Request $request): View
    {
        $entry = new Entry(['published_at' => Carbon::now(), 'labels' => ['new']]);
        $linked = array_values(array_map('intval', array_filter((array) $request->query('requests', []), 'is_numeric')));

        return $this->form($entry, $linked);
    }

    public function edit(Entry $entry): View
    {
        return $this->form($entry, array_values(array_map('intval', $entry->requests()->pluck('id')->all())));
    }

    public function store(Request $request): RedirectResponse|JsonResponse|View
    {
        return $this->save($request, new Entry);
    }

    public function update(Request $request, Entry $entry): RedirectResponse|JsonResponse|View
    {
        return $this->save($request, $entry);
    }

    public function destroy(Request $request, Entry $entry): RedirectResponse|JsonResponse
    {
        Feedback::deleteEntry($entry);

        return $request->expectsJson()
            ? response()->json(['deleted' => true])
            : redirect()->route('feedback.manage.entries')->with('feedback.status', 'Deleted “'.$entry->title.'”.');
    }

    /**
     * The editor's live preview: Markdown in, HTML out.
     */
    public function preview(Request $request): Response
    {
        $body = $request->input('body');

        return response(Feedback::render(is_string($body) ? $body : '', trusted: true));
    }

    private function save(Request $request, Entry $entry): RedirectResponse|JsonResponse|View
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:100000'],
            'labels' => ['sometimes', 'array'],
            'labels.*' => ['string', Rule::in(array_keys(Palette::changelogLabels()))],
            'cover_url' => ['nullable', 'string', 'max:2048'],
            'published_at' => ['nullable', 'date'],
            'state' => ['sometimes', Rule::in(['draft', 'published'])],
            'requests' => ['sometimes', 'array'],
            'requests.*' => ['integer'],
            'notify' => ['sometimes', 'boolean'],
        ]);

        // The Preview button, for browsers without JavaScript.
        if ($request->input('action') === 'preview') {
            $entry->fill(['title' => $data['title'], 'body' => (string) ($data['body'] ?? ''), 'labels' => $data['labels'] ?? [], 'cover_url' => $data['cover_url'] ?? null]);

            return $this->form($entry, array_values(array_map('intval', (array) ($data['requests'] ?? []))));
        }

        $draft = ($data['state'] ?? 'published') === 'draft';

        Feedback::saveEntry($entry, [
            'title' => (string) $data['title'],
            'body' => (string) ($data['body'] ?? ''),
            'labels' => array_values(array_map('strval', (array) ($data['labels'] ?? []))),
            'cover_url' => isset($data['cover_url']) ? (string) $data['cover_url'] : null,
            'published_at' => $draft ? null : (isset($data['published_at']) ? Carbon::parse((string) $data['published_at']) : ($entry->published_at ?? Carbon::now())),
        ], array_map('intval', (array) ($data['requests'] ?? [])), $this->viewer($request), $request->boolean('notify', true));

        if ($request->expectsJson()) {
            return (new EntryResource($entry->load('requests')))->response()->setStatusCode($entry->wasRecentlyCreated ? 201 : 200);
        }

        $message = match (true) {
            $draft => 'Draft saved. Only managers can see it.',
            $entry->isScheduled() => 'Scheduled for '.$entry->published_at?->toFormattedDateString().'.',
            default => 'Published. Linked requests are marked shipped.',
        };

        return redirect()->route('feedback.manage.entries.edit', $entry)->with('feedback.status', $message);
    }

    /**
     * @param  list<int>  $linked
     */
    private function form(Entry $entry, array $linked): View
    {
        return view('feedback::manage.entry-edit', [
            'entry' => $entry,
            'linked' => $linked,
            'preview' => Feedback::render((string) $entry->body, trusted: true),
            'requests' => FeatureRequest::query()->listed()
                ->where(fn ($query) => $query->where('status', '!=', 'closed')->orWhereIn('id', $linked))
                ->orderByRaw("case when status = 'shipped' then 1 else 0 end")
                ->orderByDesc('votes_count')
                ->limit(300)
                ->get(['id', 'title', 'status', 'votes_count', 'entry_id']),
        ]);
    }
}
