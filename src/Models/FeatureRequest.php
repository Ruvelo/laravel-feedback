<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Ruvelo\Feedback\Database\Factories\FeatureRequestFactory;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Support\Palette;
use Ruvelo\Feedback\Support\People;
use Ruvelo\Feedback\Support\Text;

/**
 * Something people asked for. Write through Ruvelo\Feedback\Feedback so
 * counts stay right and events fire.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $body
 * @property string $status
 * @property string|null $status_note
 * @property Carbon|null $status_changed_at
 * @property string|null $author_type
 * @property int|string|null $author_id
 * @property int|null $category_id
 * @property int|null $entry_id
 * @property int|null $merged_into_id
 * @property int $votes_count
 * @property int $comments_count
 * @property Carbon|null $pinned_at
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model|null $author
 * @property-read Category|null $category
 * @property-read Entry|null $entry
 * @property-read FeatureRequest|null $mergedInto
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, Vote> $votes
 * @property-read Collection<int, Comment> $comments
 * @property-read Collection<int, StatusChange> $statusChanges
 * @property-read Collection<int, FeatureRequest> $duplicates
 */
class FeatureRequest extends Model
{
    /** @use HasFactory<FeatureRequestFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'under_review',
        'votes_count' => 0,
        'comments_count' => 0,
    ];

    public function getTable(): string
    {
        return Feedback::table('requests');
    }

    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
            'comments_count' => 'integer',
            'status_changed_at' => 'datetime',
            'pinned_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function newFactory(): FeatureRequestFactory
    {
        return FeatureRequestFactory::new();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The changelog entry it shipped in.
     *
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * @return BelongsTo<FeatureRequest, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * Requests merged into this one.
     *
     * @return HasMany<FeatureRequest, $this>
     */
    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, Feedback::table('request_tag'), 'request_id', 'tag_id');
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'request_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'request_id');
    }

    /**
     * @return HasMany<StatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(StatusChange::class, 'request_id');
    }

    /**
     * Requests anyone can see: approved and not merged.
     *
     * @param  Builder<static>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->whereNull('merged_into_id')->whereNotNull('approved_at');
    }

    /**
     * What this person sees: approved requests, their own waiting ones,
     * and everything for managers. Never merged ones.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, ?Model $viewer): void
    {
        $query->whereNull('merged_into_id');

        if ($viewer !== null && Feedback::canManage($viewer)) {
            return;
        }

        $query->where(function (Builder $query) use ($viewer): void {
            $query->whereNotNull('approved_at');

            if ($viewer !== null) {
                $query->orWhere(fn (Builder $mine) => $mine
                    ->where('author_type', $viewer->getMorphClass())
                    ->where('author_id', $viewer->getKey()));
            }
        });
    }

    /**
     * Every word in the title or description.
     *
     * @param  Builder<static>  $query
     */
    public function scopeMatching(Builder $query, string $text): void
    {
        foreach (Text::words($text) as $word) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%';
            $query->where(fn (Builder $query) => $query->where('title', 'like', $like)->orWhere('body', 'like', $like));
        }
    }

    /**
     * Adds a `voted` boolean: whether this person voted.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithVoted(Builder $query, ?Model $viewer): void
    {
        if ($viewer === null) {
            return;
        }

        $query->withExists(['votes as voted' => fn (Builder $votes) => $votes
            ->where('voter_type', $viewer->getMorphClass())
            ->where('voter_id', $viewer->getKey())]);
    }

    /**
     * Pinned first, then top (votes), new or trending (votes lately).
     *
     * @param  Builder<static>  $query
     */
    public function scopeSorted(Builder $query, string $sort, bool $pinnedFirst = true): void
    {
        if ($pinnedFirst) {
            $query->orderByRaw('case when pinned_at is null then 1 else 0 end');
        }

        match ($sort) {
            'new' => $query->latest('created_at')->orderByDesc('id'),
            'trending' => $query
                ->withCount(['votes as recent_votes_count' => fn (Builder $votes) => $votes
                    ->where('created_at', '>=', Carbon::now()->subDays(max(1, (int) config('feedback.board.trending_days', 14))))])
                ->orderByDesc('recent_votes_count')->orderByDesc('votes_count')->orderByDesc('id'),
            default => $query->orderByDesc('votes_count')->orderByDesc('comments_count')->orderByDesc('id'),
        };
    }

    public function getRouteKey(): string
    {
        return $this->id.'-'.$this->slug;
    }

    /**
     * `42-dark-mode`, `42` or anything starting with the ID: links keep
     * working after a rename.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        if ($field !== null) {
            $model = parent::resolveRouteBinding($value, $field);

            return $model instanceof self ? $model : null;
        }

        return preg_match('/^(\d+)(?:-|$)/', (string) $value, $match) === 1
            ? static::query()->find((int) $match[1])
            : null;
    }

    public function url(): string
    {
        return route('feedback.requests.show', $this);
    }

    public function html(): string
    {
        return Feedback::render((string) $this->body);
    }

    public function excerpt(int $length = 180): string
    {
        return Text::excerpt($this->body, $length);
    }

    public function statusLabel(): string
    {
        return Palette::statusLabel($this->status);
    }

    public function authorName(): string
    {
        return People::name($this->author);
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped';
    }

    /**
     * Whether new votes are refused: merged, waiting for approval, or in a
     * locked status.
     */
    public function isLocked(): bool
    {
        return $this->isMerged() || ! $this->isApproved() || in_array($this->status, Palette::lockedStatuses(), true);
    }

    public function isAuthoredBy(?Model $person): bool
    {
        return $person !== null
            && $this->author_type === $person->getMorphClass()
            && (string) $this->author_id === (string) $person->getKey();
    }

    /**
     * Whether this person voted. Uses the `voted` attribute from
     * withVoted() when it was loaded.
     */
    public function hasVoter(?Model $voter): bool
    {
        if ($voter === null) {
            return false;
        }

        if (array_key_exists('voted', $this->attributes)) {
            return (bool) $this->attributes['voted'];
        }

        return $this->votes()
            ->where('voter_type', $voter->getMorphClass())
            ->where('voter_id', $voter->getKey())
            ->exists();
    }

    public static function slugFor(string $title): string
    {
        $slug = Str::slug(Str::limit($title, 80, ''));

        return $slug !== '' ? $slug : 'request';
    }
}
