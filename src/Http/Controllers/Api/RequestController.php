<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Controllers\Controller;
use Ruvelo\Feedback\Http\Resources\CommentResource;
use Ruvelo\Feedback\Http\Resources\RequestResource;
use Ruvelo\Feedback\Models\Comment;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Support\Board;
use Ruvelo\Feedback\Support\Palette;

class RequestController extends Controller
{
    /**
     * The board: ?status=open|all|{key}&category=&tag=&q=&sort=top|new|trending&page=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $board = Board::fromRequest($request);
        $perPage = min(100, max(1, $request->integer('per_page', (int) config('feedback.board.per_page', 20))));

        return RequestResource::collection(
            $board->query($this->viewer($request))->with('author')->paginate($perPage)->withQueryString()
        )->additional(['meta' => ['filters' => $board->with() + ['status' => $board->status, 'sort' => $board->sort]]]);
    }

    public function show(Request $request, FeatureRequest $featureRequest): RequestResource
    {
        $this->ensureVisible($request, $featureRequest);

        return new RequestResource($featureRequest->load(['author', 'category', 'tags', 'entry', 'statusChanges.changer']));
    }

    public function similar(Request $request): AnonymousResourceCollection
    {
        $q = is_string($request->query('q')) ? mb_substr($request->query('q'), 0, 160) : '';

        return RequestResource::collection(Feedback::similar($q, viewer: $this->viewer($request))->load(['category', 'tags']));
    }

    public function comments(Request $request, FeatureRequest $featureRequest): AnonymousResourceCollection
    {
        $this->ensureVisible($request, $featureRequest);
        abort_unless(Feedback::commentsDriver() === 'builtin', 404);

        return CommentResource::collection(
            $featureRequest->comments()->whereNull('parent_id')->with(['author', 'replies.author'])->oldest()->orderBy('id')->paginate(50)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        Feedback::authorize('feedback-post', $request->user());

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:'.(int) config('feedback.max_length.title', 160)],
            'body' => ['nullable', 'string', 'max:'.(int) config('feedback.max_length.body', 10000)],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $feature = Feedback::post($user, (string) $data['title'], isset($data['body']) ? (string) $data['body'] : null, isset($data['category']) ? (string) $data['category'] : null);

        return (new RequestResource($feature->load(['author', 'category', 'tags'])))->response()->setStatusCode(201);
    }

    public function vote(Request $request, FeatureRequest $featureRequest): JsonResponse
    {
        $user = $this->user($request);
        $this->ensureVisible($request, $featureRequest);
        Feedback::authorize('feedback-vote', $request->user(), $featureRequest);

        $added = Feedback::vote($featureRequest, $user);

        return response()->json(['voted' => true, 'added' => $added, 'votes' => $featureRequest->refresh()->votes_count]);
    }

    public function unvote(Request $request, FeatureRequest $featureRequest): JsonResponse
    {
        $user = $this->user($request);
        $this->ensureVisible($request, $featureRequest);

        $removed = Feedback::unvote($featureRequest, $user);

        return response()->json(['voted' => false, 'removed' => $removed, 'votes' => $featureRequest->refresh()->votes_count]);
    }

    public function comment(Request $request, FeatureRequest $featureRequest): JsonResponse
    {
        $user = $this->user($request);
        $this->ensureVisible($request, $featureRequest);
        abort_unless(Feedback::commentsDriver() === 'builtin' && ! $featureRequest->isMerged(), 404);
        Feedback::authorize('feedback-comment', $request->user(), $featureRequest);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.(int) config('feedback.max_length.comment', 5000)],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = isset($data['parent_id']) ? Comment::query()->find((int) $data['parent_id']) : null;
        $comment = Feedback::comment($featureRequest, $user, (string) $data['body'], $parent);

        return (new CommentResource($comment->load('author')))->response()->setStatusCode(201);
    }

    /**
     * Managers: edit, change status (with `note`, `notify`, `entry`), pin.
     */
    public function update(Request $request, FeatureRequest $featureRequest): RequestResource
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'min:3', 'max:'.(int) config('feedback.max_length.title', 160)],
            'body' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('feedback.max_length.body', 10000)],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:60'],
            'status' => ['sometimes', 'string', Rule::in(array_keys(Palette::statuses()))],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notify' => ['sometimes', 'boolean'],
            'entry' => ['sometimes', 'nullable', 'integer', Rule::exists(Feedback::table('entries'), 'id')],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        $changes = array_intersect_key($data, array_flip(['title', 'body', 'category', 'tags']));
        if ($changes !== []) {
            /** @var array{title?: string, body?: string|null, category?: string|null, tags?: list<string>} $changes */
            Feedback::update($featureRequest, $changes);
        }

        if (isset($data['status'])) {
            $note = isset($data['note']) ? (string) $data['note'] : null;
            $notify = $request->boolean('notify', true);
            $data['status'] === 'shipped'
                ? Feedback::ship($featureRequest, isset($data['entry']) ? Entry::query()->find((int) $data['entry']) : null, $note, $this->viewer($request), $notify)
                : Feedback::changeStatus($featureRequest, (string) $data['status'], $note, $this->viewer($request), $notify);
        }

        if (array_key_exists('pinned', $data)) {
            Feedback::pin($featureRequest, $request->boolean('pinned'));
        }

        return new RequestResource($featureRequest->refresh()->load(['author', 'category', 'tags', 'entry', 'statusChanges.changer']));
    }

    public function approve(FeatureRequest $featureRequest): RequestResource
    {
        return new RequestResource(Feedback::approve($featureRequest));
    }

    public function merge(Request $request, FeatureRequest $featureRequest): RequestResource
    {
        $data = $request->validate(['into' => ['required', 'integer', Rule::exists(Feedback::table('requests'), 'id')]]);

        $into = Feedback::merge($featureRequest, FeatureRequest::query()->findOrFail((int) $data['into']), $this->viewer($request));

        return new RequestResource($into->refresh()->load(['category', 'tags']));
    }

    public function destroy(FeatureRequest $featureRequest): JsonResponse
    {
        Feedback::delete($featureRequest);

        return response()->json(['deleted' => true]);
    }

    private function ensureVisible(Request $request, FeatureRequest $feature): void
    {
        $viewer = $this->viewer($request);

        abort_unless($feature->isApproved() || $feature->isAuthoredBy($viewer) || Feedback::canManage($viewer), 404);
    }
}
