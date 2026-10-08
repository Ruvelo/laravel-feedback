<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Ruvelo\Feedback\Database\Factories\EntryFactory;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Support\People;
use Ruvelo\Feedback\Support\Text;

/**
 * A changelog entry. Published once `published_at` has passed; a draft
 * while it's null.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $body
 * @property list<string>|null $labels
 * @property string|null $cover_url
 * @property string|null $author_type
 * @property int|string|null $author_id
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model|null $author
 * @property-read Collection<int, FeatureRequest> $requests
 */
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Feedback::table('entries');
    }

    protected function casts(): array
    {
        return [
            'labels' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EntryFactory
    {
        return EntryFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The requests that shipped in this entry.
     *
     * @return HasMany<FeatureRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(FeatureRequest::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', Carbon::now());
    }

    /**
     * Newest first.
     *
     * @param  Builder<static>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lessThanOrEqualTo(Carbon::now());
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    /**
     * @return list<string>
     */
    public function labelKeys(): array
    {
        return array_values(array_filter((array) $this->labels, 'is_string'));
    }

    public function url(): string
    {
        return route('feedback.changelog.show', $this);
    }

    public function html(): string
    {
        return Feedback::render($this->body, trusted: true);
    }

    public function excerpt(int $length = 220): string
    {
        return Text::excerpt($this->body, $length);
    }

    public function authorName(): string
    {
        return People::name($this->author, 'The team');
    }

    public static function slugFor(string $title): string
    {
        $slug = Str::slug(Str::limit($title, 80, ''));

        return $slug !== '' ? $slug : 'update';
    }
}
