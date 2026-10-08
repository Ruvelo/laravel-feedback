<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Resources\CommentResource;
use Ruvelo\Feedback\Models\Comment;
use Ruvelo\Feedback\Models\FeatureRequest;

class CommentController extends Controller
{
    public function store(Request $request, FeatureRequest $featureRequest): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);
        $feature = $featureRequest;

        abort_unless(Feedback::commentsDriver() === 'builtin', 404);
        abort_unless(! $feature->isMerged() && ($feature->isApproved() || $feature->isAuthoredBy($user) || Feedback::canManage($user)), 404);

        if ($this->caughtInHoneypot($request)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Comment posted.'], 202)
                : redirect()->to($feature->url().'#comments')->with('feedback.status', 'Comment posted.');
        }

        Feedback::authorize('feedback-comment', $request->user(), $feature);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.(int) config('feedback.max_length.comment', 5000)],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = isset($data['parent_id']) ? Comment::query()->find((int) $data['parent_id']) : null;
        $comment = Feedback::comment($feature, $user, (string) $data['body'], $parent);

        if ($request->expectsJson()) {
            return (new CommentResource($comment->load('author')))->response()->setStatusCode(201);
        }

        return redirect()->to($feature->url().'#comment-'.$comment->id)->with('feedback.status', 'Comment posted.');
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);

        abort_unless($comment->isAuthoredBy($user) || Feedback::canManage($user), 403);

        Feedback::deleteComment($comment);

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->to($comment->request->url().'#comments')->with('feedback.status', 'Comment deleted.');
    }
}
