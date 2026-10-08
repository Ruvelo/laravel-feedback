<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Support\Palette;
use Ruvelo\Feedback\Support\SeenCookie;

class ChangelogController extends Controller
{
    public function index(Request $request): View
    {
        $label = $request->query('label');
        $label = is_string($label) && isset(Palette::changelogLabels()[$label]) ? $label : null;
        $since = $this->lastSeen($request);

        $entries = Feedback::changelog()
            ->with(['requests' => fn ($query) => $query->orderByDesc('votes_count')])
            ->when($label !== null, fn ($query) => $query->where('labels', 'like', '%"'.$label.'"%'))
            ->paginate(max(1, (int) config('feedback.changelog.per_page', 10)))
            ->withQueryString();

        $this->markSeen($request);

        return view('feedback::changelog.index', [
            'entries' => $entries,
            'label' => $label,
            'since' => $since,
            'drafts' => Feedback::canManage($request->user())
                ? Entry::query()->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '>', Carbon::now()))->latest('id')->get()
                : collect(),
        ]);
    }

    public function show(Request $request, Entry $entry): View
    {
        abort_unless($entry->isPublished() || Feedback::canManage($request->user()), 404);

        $entry->load(['requests' => fn ($query) => $query->whereNull('merged_into_id')->orderByDesc('votes_count'), 'author']);

        $this->markSeen($request);

        return view('feedback::changelog.show', [
            'entry' => $entry,
            'newer' => Feedback::changelog()->where('published_at', '>', $entry->published_at ?? Carbon::now())->reorder('published_at')->first(),
            'older' => Feedback::changelog()->where('published_at', '<', $entry->published_at ?? Carbon::now())->first(),
        ]);
    }

    public function atom(): Response
    {
        return $this->feed('atom', 'application/atom+xml');
    }

    public function rss(): Response
    {
        return $this->feed('rss', 'application/rss+xml');
    }

    /**
     * The "What's new" widget was opened: everything so far is read.
     */
    public function seen(Request $request): JsonResponse|RedirectResponse
    {
        $this->markSeen($request);

        return $request->expectsJson()
            ? response()->json(['unread' => 0])
            : back(fallback: route('feedback.changelog'));
    }

    private function feed(string $format, string $type): Response
    {
        $entries = Feedback::changelog()->limit(max(1, (int) config('feedback.changelog.feed', 20)))->get();

        return response()
            ->view('feedback::changelog.'.$format, ['entries' => $entries, 'updated' => $entries->first()->published_at ?? Carbon::now()])
            ->header('Content-Type', $type.'; charset=UTF-8');
    }

    private function lastSeen(Request $request): ?Carbon
    {
        $viewer = $this->viewer($request);

        return $viewer !== null ? Feedback::lastSeen($viewer) : SeenCookie::get($request);
    }

    private function markSeen(Request $request): void
    {
        $viewer = $this->viewer($request);

        $viewer !== null ? Feedback::markSeen($viewer) : SeenCookie::queue();
    }
}
