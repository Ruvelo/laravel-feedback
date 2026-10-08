<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * The board's filters, read from a request's query string, and the query
 * they make. Shared by the page and the JSON API.
 *
 * @internal
 */
final class Board
{
    public const SORTS = ['top' => 'Top', 'new' => 'New', 'trending' => 'Trending'];

    /**
     * @param  string  $status  'open', 'all' or a status key
     */
    public function __construct(
        public readonly string $status = 'open',
        public readonly ?string $category = null,
        public readonly ?string $tag = null,
        public readonly string $q = '',
        public readonly string $sort = 'top',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $status = $request->query('status');
        $sort = $request->query('sort');
        $default = (string) config('feedback.board.sort', 'top');

        return new self(
            status: is_string($status) && ($status === 'all' || array_key_exists($status, Palette::statuses())) ? $status : 'open',
            category: self::slug($request->query('category')),
            tag: self::slug($request->query('tag')),
            q: is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 200) : '',
            sort: is_string($sort) && isset(self::SORTS[$sort]) ? $sort : (isset(self::SORTS[$default]) ? $default : 'top'),
        );
    }

    /**
     * @return Builder<FeatureRequest>
     */
    public function query(?Model $viewer): Builder
    {
        return Feedback::requests($viewer)
            ->with(['category', 'tags'])
            ->withVoted($viewer)
            ->when($this->status === 'open', fn (Builder $query) => $query->whereIn('status', Palette::openStatuses()))
            ->when($this->status !== 'open' && $this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->category !== null, fn (Builder $query) => $query->whereHas('category', fn (Builder $category) => $category->where('slug', $this->category)))
            ->when($this->tag !== null, fn (Builder $query) => $query->whereHas('tags', fn (Builder $tag) => $tag->where('slug', $this->tag)))
            ->when($this->q !== '', fn (Builder $query) => $query->matching($this->q))
            ->sorted($this->sort, pinnedFirst: $this->q === '');
    }

    /**
     * The query string for these filters with some changed; null removes
     * one. Defaults are left out so URLs stay short.
     *
     * @param  array<string, string|null>  $changes
     * @return array<string, string>
     */
    public function with(array $changes = []): array
    {
        $values = array_merge([
            'status' => $this->status,
            'category' => $this->category,
            'tag' => $this->tag,
            'q' => $this->q,
            'sort' => $this->sort,
        ], $changes);

        return array_filter([
            'status' => $values['status'] === 'open' ? null : $values['status'],
            'category' => $values['category'],
            'tag' => $values['tag'],
            'q' => $values['q'] === '' ? null : $values['q'],
            'sort' => $values['sort'] === (string) config('feedback.board.sort', 'top') ? null : $values['sort'],
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    public function isFiltered(): bool
    {
        return $this->status !== 'open' || $this->category !== null || $this->tag !== null || $this->q !== '';
    }

    private static function slug(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-z0-9-]{1,100}$/', $value) === 1 ? $value : null;
    }
}
