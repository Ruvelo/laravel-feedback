<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\Comment;
use Ruvelo\Feedback\Support\People;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Comment $comment */
        $comment = $this->resource;
        $viewer = $request->user() instanceof Model ? $request->user() : null;
        $name = $comment->authorName();

        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'author' => ['name' => $name, 'initials' => People::initials($name)],
            'official' => $comment->official,
            'body' => $comment->body,
            'html' => $comment->html(),
            'created_at' => $comment->created_at->toIso8601String(),
            'can' => ['delete' => $comment->isAuthoredBy($viewer) || Feedback::canManage($request->user())],
            'replies' => $comment->relationLoaded('replies') ? self::collection($comment->replies)->resolve($request) : [],
        ];
    }
}
