<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Integrations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Ruvelo\Comments\Concerns\HasComments;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * A request as ruvelo/laravel-comments sees it: the same row, with the
 * HasComments trait. Only loaded when that package is installed and
 * feedback.comments.driver picks it; registered as the `feedback-request`
 * commentable.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property int|null $merged_into_id
 * @property-read Model|null $author
 */
class CommentableRequest extends Model
{
    use HasComments;

    public const ALIAS = 'feedback-request';

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Feedback::table('requests');
    }

    public static function wrap(FeatureRequest $request): self
    {
        $model = (new self)->newFromBuilder($request->getAttributes());
        $model->exists = $request->exists;

        return $model;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function commentsAreOpen(): bool
    {
        return $this->merged_into_id === null;
    }

    public function commentUrl(): ?string
    {
        return route('feedback.requests.show', $this->id.'-'.$this->slug);
    }

    public function commentTitle(): string
    {
        return $this->title;
    }

    /**
     * @return iterable<int, mixed>
     */
    public function commentNotifiables(): iterable
    {
        return array_filter([$this->author]);
    }
}
