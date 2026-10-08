<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Models\StatusChange;
use Ruvelo\Feedback\Models\Tag;
use Ruvelo\Feedback\Support\People;

/**
 * @mixin FeatureRequest
 */
class RequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FeatureRequest $feature */
        $feature = $this->resource;
        $viewer = $request->user() instanceof Model ? $request->user() : null;
        $author = $feature->relationLoaded('author') ? $feature->author : null;

        return [
            'id' => $feature->id,
            'title' => $feature->title,
            'slug' => $feature->slug,
            'body' => $feature->body,
            'html' => $feature->html(),
            'excerpt' => $feature->excerpt(),
            'status' => $feature->status,
            'status_label' => $feature->statusLabel(),
            'status_note' => $feature->status_note,
            'status_changed_at' => $feature->status_changed_at?->toIso8601String(),
            'votes' => $feature->votes_count,
            'comments' => $feature->comments_count,
            'voted' => $viewer !== null && $feature->hasVoter($viewer),
            'pinned' => $feature->isPinned(),
            'approved' => $feature->isApproved(),
            'locked' => $feature->isLocked(),
            'author' => $author === null ? null : ['name' => People::name($author), 'initials' => People::initials(People::name($author))],
            'category' => $feature->relationLoaded('category') && $feature->category !== null
                ? ['name' => $feature->category->name, 'slug' => $feature->category->slug]
                : null,
            'tags' => $feature->relationLoaded('tags')
                ? $feature->tags->map(fn (Tag $tag): array => ['name' => $tag->name, 'slug' => $tag->slug])->values()->all()
                : [],
            'entry' => $feature->relationLoaded('entry') && $feature->entry !== null && $feature->entry->isPublished()
                ? ['title' => $feature->entry->title, 'url' => $feature->entry->url()]
                : null,
            'merged_into' => $feature->merged_into_id,
            'history' => $this->whenLoaded('statusChanges', fn () => $feature->statusChanges->map(fn (StatusChange $change): array => [
                'from' => $change->from,
                'to' => $change->to,
                'label' => $change->label(),
                'note' => $change->note,
                'by' => $change->changer !== null ? People::name($change->changer) : null,
                'at' => $change->created_at?->toIso8601String(),
            ])->values()->all()),
            'url' => $feature->url(),
            'created_at' => $feature->created_at->toIso8601String(),
            'can' => [
                'vote' => $viewer !== null && ! $feature->isLocked() && Feedback::allows('feedback-vote', $request->user(), $feature),
                'comment' => $viewer !== null && Feedback::commentsDriver() === 'builtin' && Feedback::allows('feedback-comment', $request->user(), $feature),
                'manage' => Feedback::canManage($request->user()),
            ],
        ];
    }
}
