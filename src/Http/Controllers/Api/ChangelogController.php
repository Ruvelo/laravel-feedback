<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Controllers\Controller;
use Ruvelo\Feedback\Http\Resources\EntryResource;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Support\Palette;

class ChangelogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $label = $request->query('label');

        return EntryResource::collection(
            Feedback::changelog()
                ->with('requests')
                ->when(is_string($label) && isset(Palette::changelogLabels()[$label]), fn ($query) => $query->where('labels', 'like', '%"'.$label.'"%'))
                ->paginate(min(100, max(1, $request->integer('per_page', (int) config('feedback.changelog.per_page', 10)))))
                ->withQueryString()
        );
    }

    public function show(string $slug): EntryResource
    {
        $entry = Feedback::entry($slug);
        abort_if($entry === null, 404);

        return new EntryResource($entry->load('requests'));
    }

    /**
     * For a custom "What's new" widget: the unread count and the latest
     * entries. Guests get `unread` counted from `?since=` (a timestamp).
     */
    public function whatsNew(Request $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $since = $request->query('since');
        $since = is_string($since) && ctype_digit($since) ? Carbon::createFromTimestamp((int) $since) : null;
        $limit = min(20, max(1, $request->integer('limit', (int) config('feedback.widget.limit', 5))));

        return response()->json([
            'unread' => Feedback::unreadCount($viewer, $since),
            'last_seen' => $viewer !== null ? Feedback::lastSeen($viewer)?->toIso8601String() : null,
            'entries' => EntryResource::collection(Feedback::changelog()->limit($limit)->get())->resolve($request),
            'url' => route('feedback.changelog'),
        ]);
    }

    public function seen(Request $request): JsonResponse
    {
        Feedback::markSeen($this->user($request));

        return response()->json(['unread' => 0]);
    }

    public function store(Request $request): JsonResponse
    {
        $entry = $this->save($request, new Entry);

        return (new EntryResource($entry->load('requests')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Entry $entry): EntryResource
    {
        return new EntryResource($this->save($request, $entry)->load('requests'));
    }

    public function destroy(Entry $entry): JsonResponse
    {
        Feedback::deleteEntry($entry);

        return response()->json(['deleted' => true]);
    }

    private function save(Request $request, Entry $entry): Entry
    {
        $required = $entry->exists ? 'sometimes' : 'required';

        $data = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'labels' => ['sometimes', 'array'],
            'labels.*' => ['string', Rule::in(array_keys(Palette::changelogLabels()))],
            'cover_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'requests' => ['sometimes', 'array'],
            'requests.*' => ['integer'],
            'notify' => ['sometimes', 'boolean'],
        ]);

        $attributes = [];
        foreach (['title', 'body', 'cover_url'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key] === null ? ($key === 'cover_url' ? null : '') : (string) $data[$key];
            }
        }
        if (array_key_exists('labels', $data)) {
            $attributes['labels'] = array_values(array_map('strval', (array) $data['labels']));
        }
        if (array_key_exists('published_at', $data)) {
            $attributes['published_at'] = $data['published_at'] === null ? null : Carbon::parse((string) $data['published_at']);
        } elseif (! $entry->exists) {
            $attributes['published_at'] = Carbon::now();
        }

        /** @var array{title?: string, body?: string, labels?: list<string>, cover_url?: string|null, published_at?: Carbon|null} $attributes */
        return Feedback::saveEntry(
            $entry,
            $attributes,
            array_key_exists('requests', $data) ? array_map('intval', (array) $data['requests']) : null,
            $this->viewer($request),
            $request->boolean('notify', true),
        );
    }
}
