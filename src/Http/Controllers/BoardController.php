<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Resources\RequestResource;
use Ruvelo\Feedback\Models\Category;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Models\Tag;
use Ruvelo\Feedback\Support\Board;
use Ruvelo\Feedback\Support\Palette;

class BoardController extends Controller
{
    public function index(Request $request): View
    {
        $board = Board::fromRequest($request);
        $viewer = $this->viewer($request);

        $counts = Feedback::requests($viewer)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            ->map(fn (mixed $total): int => is_numeric($total) ? (int) $total : 0);

        return view('feedback::board', [
            'board' => $board,
            'requests' => $board->query($viewer)
                ->paginate(max(1, (int) config('feedback.board.per_page', 20)))
                ->withQueryString(),
            'categories' => Category::query()->orderBy('position')->orderBy('name')->get(),
            'tags' => Tag::query()->whereHas('requests', fn ($query) => $query->whereNull('merged_into_id')->whereNotNull('approved_at'))->orderBy('name')->get(),
            'counts' => $counts,
            'openCount' => $counts->only(Palette::openStatuses())->sum(),
            'allCount' => $counts->sum(),
        ]);
    }

    public function create(Request $request): View
    {
        $title = is_string($request->query('title')) ? mb_substr(trim($request->query('title')), 0, 160) : '';

        return view('feedback::create', [
            'title' => $title,
            'similar' => $title !== '' ? Feedback::similar($title, viewer: $this->viewer($request)) : collect(),
            'categories' => Category::query()->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);

        if ($this->caughtInHoneypot($request)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Thanks! Your request was posted.'], 202)
                : redirect()->route('feedback.home')->with('feedback.status', 'Thanks! Your request was posted.');
        }

        Feedback::authorize('feedback-post', $request->user());

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:'.(int) config('feedback.max_length.title', 160)],
            'body' => ['nullable', 'string', 'max:'.(int) config('feedback.max_length.body', 10000)],
            'category' => ['nullable', 'integer', Rule::exists(Feedback::table('categories'), 'id')],
        ]);

        $feature = Feedback::post(
            $user,
            (string) $data['title'],
            isset($data['body']) ? (string) $data['body'] : null,
            isset($data['category']) ? (string) $data['category'] : null,
        );

        $message = $feature->isApproved()
            ? 'Thanks! Your request is on the board, with your vote.'
            : 'Thanks! Your request will appear once the team has looked at it.';

        if ($request->expectsJson()) {
            return (new RequestResource($feature->load(['category', 'tags', 'author'])))
                ->additional(['message' => $message])
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->to($feature->url())->with('feedback.status', $message);
    }

    public function show(Request $request, FeatureRequest $featureRequest): View|RedirectResponse
    {
        $feature = $featureRequest;
        $viewer = $this->viewer($request);
        $manager = Feedback::canManage($viewer);

        if ($feature->isMerged() && $feature->mergedInto !== null) {
            return redirect()->to($feature->mergedInto->url(), 301)
                ->with('feedback.status', '“'.$feature->title.'” was merged into this request, with its votes.');
        }

        abort_unless($feature->isApproved() || $manager || $feature->isAuthoredBy($viewer), 404);

        $feature->load(['author', 'category', 'tags', 'entry', 'statusChanges' => fn ($query) => $query->with('changer')->oldest('id'), 'duplicates']);

        $driver = Feedback::commentsDriver();

        return view('feedback::show', [
            'feature' => $feature,
            'voted' => $feature->hasVoter($viewer),
            'voters' => $feature->votes()->with('voter')->latest('id')->limit(7)->get(),
            'comments' => $driver === 'builtin'
                ? $feature->comments()->whereNull('parent_id')->with(['author', 'replies.author'])->oldest()->orderBy('id')->get()
                : collect(),
            'driver' => $driver,
            'replyTo' => $request->integer('reply') ?: null,
            'manager' => $manager,
            'categories' => $manager ? Category::query()->orderBy('position')->orderBy('name')->get() : collect(),
            'entries' => $manager && Feedback::enabled('changelog') ? Entry::query()->latest('id')->limit(30)->get() : collect(),
            'mergeTargets' => $manager
                ? FeatureRequest::query()->whereNull('merged_into_id')->whereKeyNot($feature->id)->orderByDesc('votes_count')->limit(300)->get(['id', 'title', 'votes_count'])
                : collect(),
        ]);
    }

    /**
     * Requests like the title being typed, for the "Similar requests" list.
     */
    public function similar(Request $request): JsonResponse
    {
        $q = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 160) : '';
        $viewer = $this->viewer($request);

        return response()->json([
            'query' => $q,
            'results' => Feedback::similar($q, viewer: $viewer)->map(fn (FeatureRequest $feature): array => [
                'id' => $feature->id,
                'title' => $feature->title,
                'url' => $feature->url(),
                'votes' => $feature->votes_count,
                'voted' => $feature->hasVoter($viewer),
                'status' => $feature->status,
                'status_label' => $feature->statusLabel(),
                'tone' => Palette::statusChip($feature->status)['class'],
                'tone_style' => Palette::statusChip($feature->status)['style'],
            ])->values(),
        ]);
    }
}
