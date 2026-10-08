<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Support\People;
use Ruvelo\Feedback\Support\Text;

/**
 * A comment on a request, from the built-in thread. Replies are one level
 * deep. `official` marks comments from managers.
 *
 * @property int $id
 * @property int $request_id
 * @property int|null $parent_id
 * @property string|null $author_type
 * @property int|string|null $author_id
 * @property string $body
 * @property bool $official
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FeatureRequest $request
 * @property-read Comment|null $parent
 * @property-read Model|null $author
 * @property-read Collection<int, Comment> $replies
 */
class Comment extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Feedback::table('comments');
    }

    protected function casts(): array
    {
        return ['official' => 'boolean'];
    }

    /**
     * @return BelongsTo<FeatureRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(FeatureRequest::class, 'request_id');
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest()->orderBy('id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function html(): string
    {
        return Feedback::render($this->body);
    }

    public function excerpt(int $length = 160): string
    {
        return Text::excerpt($this->body, $length);
    }

    public function authorName(): string
    {
        return People::name($this->author);
    }

    public function isAuthoredBy(?Model $person): bool
    {
        return $person !== null
            && $this->author_type === $person->getMorphClass()
            && (string) $this->author_id === (string) $person->getKey();
    }
}
