<?php

declare(strict_types=1);

namespace Ruvelo\Feedback;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Ruvelo\Comments\Comments;
use Ruvelo\Comments\CommentsServiceProvider;
use Ruvelo\Comments\Models\Comment as RuveloComment;
use Ruvelo\Feedback\Events\CategoryDeleted;
use Ruvelo\Feedback\Events\CategorySaved;
use Ruvelo\Feedback\Events\CommentDeleted;
use Ruvelo\Feedback\Events\CommentPosted;
use Ruvelo\Feedback\Events\EntryDeleted;
use Ruvelo\Feedback\Events\EntryPublished;
use Ruvelo\Feedback\Events\EntrySaved;
use Ruvelo\Feedback\Events\RequestApproved;
use Ruvelo\Feedback\Events\RequestDeleted;
use Ruvelo\Feedback\Events\RequestPinned;
use Ruvelo\Feedback\Events\RequestPosted;
use Ruvelo\Feedback\Events\RequestsMerged;
use Ruvelo\Feedback\Events\RequestUpdated;
use Ruvelo\Feedback\Events\RequestVoted;
use Ruvelo\Feedback\Events\StatusChanged;
use Ruvelo\Feedback\Events\TagDeleted;
use Ruvelo\Feedback\Events\TagSaved;
use Ruvelo\Feedback\Exceptions\CannotMerge;
use Ruvelo\Feedback\Exceptions\CategoryNotFound;
use Ruvelo\Feedback\Exceptions\InvalidInput;
use Ruvelo\Feedback\Exceptions\InvalidLabel;
use Ruvelo\Feedback\Exceptions\InvalidParent;
use Ruvelo\Feedback\Exceptions\InvalidStatus;
use Ruvelo\Feedback\Exceptions\NotAllowed;
use Ruvelo\Feedback\Exceptions\VotingClosed;
use Ruvelo\Feedback\Integrations\CommentableRequest;
use Ruvelo\Feedback\Markdown\Renderer;
use Ruvelo\Feedback\Models\Category;
use Ruvelo\Feedback\Models\Comment;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Models\Seen;
use Ruvelo\Feedback\Models\StatusChange;
use Ruvelo\Feedback\Models\Tag;
use Ruvelo\Feedback\Models\Vote;
use Ruvelo\Feedback\Notifications\Notifier;
use Ruvelo\Feedback\Support\Palette;

/**
 * The feedback board, roadmap and changelog's public PHP API, and the one
 * write path: the routes use it too. It doesn't check gates or rate limits
 * (imports and seeders shouldn't be slowed down); call allows() or
 * authorize() when acting for a user.
 *
 *     $request = Feedback::post($user, 'Dark mode', 'My eyes, at 2am.');
 *     Feedback::vote($request, $otherUser);
 *     Feedback::changeStatus($request, 'planned', 'Starting in March.', by: $admin);
 *     Feedback::publish('Dark mode is here', $markdown, ['new'], requests: [$request]);
 */
final class Feedback
{
    // --- Requests -----------------------------------------------------------

    /**
     * Post a request. The author votes for it straight away. It waits for
     * a manager when feedback.board.require_approval is on, unless
     * $approved says otherwise (managers' own requests skip the queue).
     *
     * @param  Category|string|null  $category  a category, its slug or name
     * @param  list<Tag|string>  $tags  tags, their slugs or names; new names are created
     *
     * @throws InvalidInput
     * @throws CategoryNotFound
     */
    public static function post(?Model $author, string $title, ?string $body = null, Category|string|null $category = null, array $tags = [], ?bool $approved = null): FeatureRequest
    {
        $title = self::cleanTitle($title);
        $body = self::cleanBody($body);
        $approved ??= ! config('feedback.board.require_approval', false) || ($author !== null && self::canManage($author));

        $request = DB::transaction(function () use ($author, $title, $body, $category, $tags, $approved): FeatureRequest {
            $request = new FeatureRequest([
                'title' => $title,
                'slug' => FeatureRequest::slugFor($title),
                'body' => $body,
                'status' => 'under_review',
                'category_id' => $category === null ? null : self::resolveCategory($category)->id,
                'approved_at' => $approved ? Carbon::now() : null,
            ]);

            if ($author !== null) {
                $request->author()->associate($author);
            }

            $request->save();

            if ($tags !== []) {
                $request->tags()->sync(self::resolveTags($tags));
            }

            if ($author !== null) {
                Vote::query()->create(['request_id' => $request->id, 'voter_type' => $author->getMorphClass(), 'voter_id' => $author->getKey()]);
                self::recount($request);
            }

            return $request;
        });

        event(new RequestPosted($request));

        return $request;
    }

    /**
     * Change a request's title, description, category or tags.
     *
     * @param  array{title?: string, body?: string|null, category?: Category|string|null, tags?: list<Tag|string>}  $changes
     *
     * @throws InvalidInput
     * @throws CategoryNotFound
     */
    public static function update(FeatureRequest $request, array $changes): FeatureRequest
    {
        if (array_key_exists('title', $changes)) {
            $request->title = self::cleanTitle($changes['title']);
            $request->slug = FeatureRequest::slugFor($request->title);
        }
        if (array_key_exists('body', $changes)) {
            $request->body = self::cleanBody($changes['body']);
        }
        if (array_key_exists('category', $changes)) {
            $request->category_id = $changes['category'] === null ? null : self::resolveCategory($changes['category'])->id;
        }

        DB::transaction(function () use ($request, $changes): void {
            $request->save();

            if (array_key_exists('tags', $changes)) {
                $request->tags()->sync(self::resolveTags($changes['tags']));
                $request->unsetRelation('tags');
            }
        });

        event(new RequestUpdated($request, $changes));

        return $request;
    }

    /**
     * Let a waiting request onto the board.
     */
    public static function approve(FeatureRequest $request): FeatureRequest
    {
        if (! $request->isApproved()) {
            $request->forceFill(['approved_at' => Carbon::now()])->save();
            event(new RequestApproved($request));
        }

        return $request;
    }

    /**
     * Delete a request with its votes, comments and history.
     */
    public static function delete(FeatureRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            if (self::commentsDriver() === 'ruvelo') {
                RuveloComment::query()
                    ->where('commentable_type', (new CommentableRequest)->getMorphClass())
                    ->where('commentable_id', $request->id)
                    ->delete();
            }

            $request->delete();
        });

        event(new RequestDeleted($request));
    }

    public static function pin(FeatureRequest $request, bool $pinned = true): FeatureRequest
    {
        if ($request->isPinned() !== $pinned) {
            $request->forceFill(['pinned_at' => $pinned ? Carbon::now() : null])->save();
            event(new RequestPinned($request, $pinned));
        }

        return $request;
    }

    // --- Votes --------------------------------------------------------------

    /**
     * Add a vote. True when it was added, false if they'd already voted.
     *
     * @throws VotingClosed
     */
    public static function vote(FeatureRequest $request, Model $voter): bool
    {
        if ($request->isLocked()) {
            throw new VotingClosed($request);
        }

        // The unique index keeps a second vote out, even from two clicks
        // at once.
        $inserted = Vote::query()->insertOrIgnore([
            'request_id' => $request->id,
            'voter_type' => $voter->getMorphClass(),
            'voter_id' => $voter->getKey(),
            'created_at' => Carbon::now(),
        ]);

        if ($inserted === 0) {
            return false;
        }

        self::recount($request);
        event(new RequestVoted($request, $voter, true));

        return true;
    }

    /**
     * Take a vote back. True when there was one. Works on locked requests
     * too: people may always change their mind.
     */
    public static function unvote(FeatureRequest $request, Model $voter): bool
    {
        $deleted = Vote::query()
            ->where('request_id', $request->id)
            ->where('voter_type', $voter->getMorphClass())
            ->where('voter_id', $voter->getKey())
            ->delete();

        if ($deleted === 0) {
            return false;
        }

        self::recount($request);
        event(new RequestVoted($request, $voter, false));

        return true;
    }

    /**
     * Vote, or take the vote back. Returns whether they're voting now.
     *
     * @throws VotingClosed
     */
    public static function toggleVote(FeatureRequest $request, Model $voter): bool
    {
        if ($request->hasVoter($voter) || $request->votes()->where('voter_type', $voter->getMorphClass())->where('voter_id', $voter->getKey())->exists()) {
            self::unvote($request, $voter);

            return false;
        }

        self::vote($request, $voter);

        return true;
    }

    // --- Status -------------------------------------------------------------

    /**
     * Move a request to another status, with an optional public note. Its
     * author and voters are notified (except $by), unless $notify is false.
     * Changing to the same status with a note posts the note as an update.
     *
     * @throws InvalidStatus
     */
    public static function changeStatus(FeatureRequest $request, string $status, ?string $note = null, ?Model $by = null, bool $notify = true): FeatureRequest
    {
        if (! array_key_exists($status, Palette::statuses())) {
            throw new InvalidStatus($status);
        }

        $note = $note !== null && trim($note) !== '' ? trim($note) : null;
        $from = $request->status;

        if ($from === $status && $note === null) {
            return $request;
        }

        DB::transaction(function () use ($request, $status, $note, $by, $from): void {
            $change = new StatusChange(['request_id' => $request->id, 'from' => $from, 'to' => $status, 'note' => $note]);
            if ($by !== null) {
                $change->changer()->associate($by);
            }
            $change->save();

            $request->forceFill([
                'status' => $status,
                'status_note' => $note ?? ($from === $status ? $request->status_note : null),
                'status_changed_at' => Carbon::now(),
            ])->save();
        });

        event(new StatusChanged($request, $from, $status, $note, $by));

        if ($notify) {
            Notifier::statusChanged($request, $from, $status, $note, $by);
        }

        return $request;
    }

    /**
     * Mark a request as shipped, linked to the changelog entry that
     * announces it.
     *
     * @throws InvalidStatus when there's no `shipped` status
     */
    public static function ship(FeatureRequest $request, ?Entry $entry = null, ?string $note = null, ?Model $by = null, bool $notify = true): FeatureRequest
    {
        if ($entry !== null && $request->entry_id !== $entry->id) {
            $request->forceFill(['entry_id' => $entry->id])->save();
            $request->setRelation('entry', $entry);
        }

        if ($request->status === 'shipped' && $note === null) {
            return $request;
        }

        return self::changeStatus($request, 'shipped', $note, $by, $notify);
    }

    // --- Merging ------------------------------------------------------------

    /**
     * Merge a duplicate into another request. Its votes move across (people
     * who voted for both keep one), its author becomes a voter, its
     * comments move too, and its page redirects to the other request.
     *
     * @throws CannotMerge
     */
    public static function merge(FeatureRequest $duplicate, FeatureRequest $into, ?Model $by = null): FeatureRequest
    {
        if ($duplicate->is($into)) {
            throw new CannotMerge('A request can’t be merged into itself.');
        }
        if ($duplicate->isMerged()) {
            throw new CannotMerge('“'.$duplicate->title.'” was already merged.');
        }
        if ($into->isMerged()) {
            throw new CannotMerge('“'.$into->title.'” was merged into another request. Merge into that one instead.');
        }

        $moved = DB::transaction(function () use ($duplicate, $into): int {
            $votes = Feedback::table('votes');
            $already = Vote::query()->where('request_id', $into->id)->select(['voter_type', 'voter_id'])->get()
                ->map(fn (Vote $vote): string => $vote->voter_type.':'.$vote->voter_id)->all();

            $moved = 0;
            foreach (Vote::query()->where('request_id', $duplicate->id)->orderBy('id')->get() as $vote) {
                $key = $vote->voter_type.':'.$vote->voter_id;
                if (in_array($key, $already, true)) {
                    $vote->delete();

                    continue;
                }
                DB::table($votes)->where('id', $vote->id)->update(['request_id' => $into->id]);
                $already[] = $key;
                $moved++;
            }

            if ($duplicate->author_type !== null && $duplicate->author_id !== null
                && ! in_array($duplicate->author_type.':'.$duplicate->author_id, $already, true)) {
                Vote::query()->create(['request_id' => $into->id, 'voter_type' => $duplicate->author_type, 'voter_id' => $duplicate->author_id]);
                $moved++;
            }

            Comment::query()->where('request_id', $duplicate->id)->update(['request_id' => $into->id]);

            if (Feedback::commentsDriver() === 'ruvelo') {
                RuveloComment::query()
                    ->where('commentable_type', (new CommentableRequest)->getMorphClass())
                    ->where('commentable_id', $duplicate->id)
                    ->update(['commentable_id' => $into->id]);
            }

            FeatureRequest::query()->where('merged_into_id', $duplicate->id)->update(['merged_into_id' => $into->id]);

            $duplicate->forceFill(['merged_into_id' => $into->id, 'pinned_at' => null])->save();

            self::recount($duplicate);
            self::recount($into);

            return $moved;
        });

        event(new RequestsMerged($duplicate, $into, $moved, $by));

        return $into;
    }

    // --- Comments -----------------------------------------------------------

    /**
     * Comment on a request, or reply to a comment. Replies to replies join
     * the same thread (one level deep). Comments from managers are marked
     * official.
     *
     * @throws InvalidInput
     * @throws InvalidParent
     */
    public static function comment(FeatureRequest $request, ?Model $author, string $body, ?Comment $parent = null, ?bool $official = null): Comment
    {
        $body = trim($body);
        $max = (int) config('feedback.max_length.comment', 5000);

        if ($body === '') {
            throw new InvalidInput('Write something first.', 'body');
        }
        if (mb_strlen($body) > $max) {
            throw new InvalidInput("Keep comments under {$max} characters.", 'body');
        }
        if ($parent !== null && $parent->request_id !== $request->id) {
            throw new InvalidParent;
        }

        $comment = new Comment([
            'request_id' => $request->id,
            'parent_id' => $parent === null ? null : ($parent->parent_id ?? $parent->id),
            'body' => $body,
            'official' => $official ?? ($author !== null && self::canManage($author)),
        ]);
        if ($author !== null) {
            $comment->author()->associate($author);
        }

        DB::transaction(function () use ($comment, $request): void {
            $comment->save();
            self::recount($request);
        });

        event(new CommentPosted($comment));

        return $comment;
    }

    /**
     * Delete a comment and its replies.
     */
    public static function deleteComment(Comment $comment): void
    {
        DB::transaction(function () use ($comment): void {
            $comment->delete();
            self::recount($comment->request);
        });

        event(new CommentDeleted($comment));
    }

    // --- Categories and tags ------------------------------------------------

    /**
     * Find a category by slug or name, or create it. A given position
     * updates it.
     *
     * @throws InvalidInput
     */
    public static function category(string $name, ?int $position = null): Category
    {
        $name = trim($name);
        $slug = Str::slug($name);

        if ($name === '' || $slug === '') {
            throw new InvalidInput('Give the category a name.', 'name');
        }

        $category = Category::query()->where('slug', $slug)->orWhere('name', $name)->first()
            ?? new Category(['name' => $name, 'slug' => $slug, 'position' => $position ?? ((int) Category::query()->max('position') + 1)]);

        if ($position !== null) {
            $category->position = $position;
        }

        if ($category->isDirty()) {
            $category->save();
            event(new CategorySaved($category));
        }

        return $category;
    }

    /**
     * @throws InvalidInput
     */
    public static function renameCategory(Category $category, string $name, ?int $position = null): Category
    {
        $name = trim($name);
        if ($name === '' || Str::slug($name) === '') {
            throw new InvalidInput('Give the category a name.', 'name');
        }

        $category->name = $name;
        if ($position !== null) {
            $category->position = $position;
        }
        $category->save();
        event(new CategorySaved($category));

        return $category;
    }

    public static function deleteCategory(Category $category): void
    {
        FeatureRequest::query()->where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();
        event(new CategoryDeleted($category));
    }

    /**
     * Find a tag by slug or name, or create it.
     *
     * @throws InvalidInput
     */
    public static function tag(string $name): Tag
    {
        $name = trim($name);
        $slug = Str::slug($name);

        if ($name === '' || $slug === '') {
            throw new InvalidInput('Give the tag a name.', 'name');
        }

        $tag = Tag::query()->where('slug', $slug)->orWhere('name', $name)->first();
        if ($tag !== null) {
            return $tag;
        }

        $tag = Tag::query()->create(['name' => $name, 'slug' => $slug]);
        event(new TagSaved($tag));

        return $tag;
    }

    /**
     * @throws InvalidInput
     */
    public static function renameTag(Tag $tag, string $name): Tag
    {
        $name = trim($name);
        if ($name === '' || Str::slug($name) === '') {
            throw new InvalidInput('Give the tag a name.', 'name');
        }

        $tag->name = $name;
        $tag->save();
        event(new TagSaved($tag));

        return $tag;
    }

    public static function deleteTag(Tag $tag): void
    {
        $tag->requests()->detach();
        $tag->delete();
        event(new TagDeleted($tag));
    }

    // --- Changelog ----------------------------------------------------------

    /**
     * Publish a changelog entry now (or at $at). Requests passed in are
     * linked to it and, once it's live, marked shipped: their voters hear
     * about it unless $notify is false.
     *
     * @param  list<string>  $labels  keys of feedback.changelog.labels
     * @param  iterable<FeatureRequest|int>  $requests
     *
     * @throws InvalidInput
     * @throws InvalidLabel
     */
    public static function publish(string $title, string $body, array $labels = [], ?string $coverUrl = null, ?CarbonInterface $at = null, iterable $requests = [], ?Model $author = null, bool $notify = true): Entry
    {
        return self::saveEntry(new Entry, [
            'title' => $title,
            'body' => $body,
            'labels' => $labels,
            'cover_url' => $coverUrl,
            'published_at' => $at ?? Carbon::now(),
        ], $requests, $author, $notify);
    }

    /**
     * Create or change a changelog entry: the one write path for entries.
     * A null `published_at` keeps it a draft; a future one schedules it.
     * $requests, when given, replaces the linked requests. Linked requests
     * are marked shipped once the entry is live.
     *
     * @param  array{title?: string, body?: string, labels?: list<string>, cover_url?: string|null, published_at?: CarbonInterface|string|null}  $attributes
     * @param  iterable<FeatureRequest|int>|null  $requests
     *
     * @throws InvalidInput
     * @throws InvalidLabel
     */
    public static function saveEntry(Entry $entry, array $attributes, ?iterable $requests = null, ?Model $author = null, bool $notify = true): Entry
    {
        $wasPublished = $entry->exists && $entry->getOriginal('published_at') !== null
            && Carbon::parse($entry->getOriginal('published_at'))->lessThanOrEqualTo(Carbon::now());

        if (array_key_exists('title', $attributes)) {
            $title = trim($attributes['title']);
            if ($title === '') {
                throw new InvalidInput('Give the entry a title.', 'title');
            }
            $entry->title = Str::limit($title, 255, '');
        }
        if (array_key_exists('body', $attributes)) {
            $entry->body = trim($attributes['body']);
        }
        if (array_key_exists('labels', $attributes)) {
            $known = Palette::changelogLabels();
            foreach ($attributes['labels'] as $label) {
                if (! isset($known[$label])) {
                    throw new InvalidLabel($label);
                }
            }
            $entry->labels = array_values(array_unique($attributes['labels']));
        }
        if (array_key_exists('cover_url', $attributes)) {
            $entry->cover_url = self::cleanUrl($attributes['cover_url']);
        }
        if (array_key_exists('published_at', $attributes)) {
            $at = $attributes['published_at'];
            $entry->published_at = $at === null || $at === '' ? null : Carbon::parse($at);
        }

        if (! $entry->exists) {
            if (! isset($entry->title)) {
                throw new InvalidInput('Give the entry a title.', 'title');
            }
            $entry->body ??= '';
            $entry->slug = self::uniqueEntrySlug($entry->title);
            if ($author !== null) {
                $entry->author()->associate($author);
            }
        }

        $linked = $requests === null ? null : self::resolveRequests($requests);

        DB::transaction(function () use ($entry, $linked): void {
            $entry->save();

            if ($linked !== null) {
                FeatureRequest::query()->where('entry_id', $entry->id)->whereNotIn('id', $linked->modelKeys())->update(['entry_id' => null]);
                FeatureRequest::query()->whereKey($linked->modelKeys())->update(['entry_id' => $entry->id]);
            }
        });

        $entry->unsetRelation('requests');

        event(new EntrySaved($entry));

        if ($entry->isPublished()) {
            if (! $wasPublished) {
                event(new EntryPublished($entry));
            }

            foreach ($entry->requests()->with('entry')->get() as $request) {
                if ($request->status !== 'shipped' && ! $request->isMerged()) {
                    self::ship($request, $entry, null, $author, $notify);
                }
            }
        }

        return $entry;
    }

    public static function deleteEntry(Entry $entry): void
    {
        $entry->delete();
        event(new EntryDeleted($entry));
    }

    // --- Reading ------------------------------------------------------------

    /**
     * A request by ID (merged ones too).
     */
    public static function find(int|string $id): ?FeatureRequest
    {
        return FeatureRequest::query()->find($id);
    }

    /**
     * Requests this person may see on the board: approved ones, their own
     * waiting ones, all of them for managers. Never merged ones.
     *
     * @return Builder<FeatureRequest>
     */
    public static function requests(?Model $viewer = null): Builder
    {
        return FeatureRequest::query()->visibleTo($viewer);
    }

    /**
     * Requests whose titles look like this one, most voted first: shown
     * while someone types, so they vote instead of posting a duplicate.
     *
     * @return Collection<int, FeatureRequest>
     */
    public static function similar(string $title, ?int $limit = null, ?Model $viewer = null): Collection
    {
        $limit ??= max(1, (int) config('feedback.board.similar', 5));
        $words = Support\Text::words($title);

        if ($words === [] || mb_strlen(trim($title)) < 3) {
            return new Collection;
        }

        $candidates = FeatureRequest::query()->listed()
            ->where(function (Builder $query) use ($words): void {
                foreach ($words as $word) {
                    $query->orWhere('title', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%');
                }
            })
            ->withVoted($viewer)
            ->orderByDesc('votes_count')
            ->limit(50)
            ->get();

        // Most words in common first; votes break ties.
        return $candidates
            ->sortByDesc(function (FeatureRequest $request) use ($words): int {
                $title = mb_strtolower($request->title);
                $score = 0;
                foreach ($words as $word) {
                    $score += str_contains($title, $word) ? 1 : 0;
                }

                return $score * 100000 + $request->votes_count;
            })
            ->take($limit)
            ->values();
    }

    /**
     * The roadmap: requests by status, most voted first (shipped ones most
     * recently shipped first).
     *
     * @return array<string, Collection<int, FeatureRequest>>
     */
    public static function roadmap(?Model $viewer = null, ?int $limit = null): array
    {
        $limit ??= max(1, (int) config('feedback.roadmap.limit', 20));
        $columns = [];

        foreach (Palette::roadmapStatuses() as $status) {
            $columns[$status] = FeatureRequest::query()->listed()
                ->where('status', $status)
                ->with(['category', 'entry'])
                ->withVoted($viewer)
                ->when($status === 'shipped',
                    fn (Builder $query) => $query->orderByDesc('status_changed_at')->orderByDesc('id'),
                    fn (Builder $query) => $query->orderByDesc('votes_count')->orderByDesc('id'))
                ->limit($limit)
                ->get();
        }

        return $columns;
    }

    /**
     * Published changelog entries, newest first.
     *
     * @return Builder<Entry>
     */
    public static function changelog(): Builder
    {
        return Entry::query()->published()->latestFirst();
    }

    /**
     * A published entry by its slug.
     */
    public static function entry(string $slug): ?Entry
    {
        return Entry::query()->published()->where('slug', $slug)->first();
    }

    /**
     * When this person last looked at the changelog.
     */
    public static function lastSeen(Model $viewer): ?Carbon
    {
        $seen = Seen::query()->where('viewer_type', $viewer->getMorphClass())->where('viewer_id', $viewer->getKey())->first();

        return $seen?->seen_at;
    }

    /**
     * Remember that this person has seen the changelog, as of now.
     */
    public static function markSeen(Model $viewer, ?CarbonInterface $at = null): void
    {
        Seen::query()->updateOrCreate(
            ['viewer_type' => $viewer->getMorphClass(), 'viewer_id' => $viewer->getKey()],
            ['seen_at' => $at ?? Carbon::now()],
        );
    }

    /**
     * How many entries came out since this person last looked: since
     * $since if given, else their stored last-seen time, else the last
     * feedback.widget.fresh_days days.
     */
    public static function unreadCount(?Model $viewer = null, ?CarbonInterface $since = null): int
    {
        $since ??= ($viewer !== null ? self::lastSeen($viewer) : null)
            ?? Carbon::now()->subDays(max(0, (int) config('feedback.widget.fresh_days', 30)));

        return Entry::query()->published()->where('published_at', '>', $since)->count();
    }

    /**
     * Numbers for the admin area: requests per status, the most voted open
     * requests, and votes and new requests per week.
     *
     * @return array{statuses: array<string, int>, pending: int, top: Collection<int, FeatureRequest>, weeks: list<array{start: Carbon, votes: int, requests: int}>, votes: int, voters: int}
     */
    public static function stats(int $weeks = 12): array
    {
        $statuses = array_fill_keys(array_keys(Palette::statuses()), 0);
        foreach (FeatureRequest::query()->listed()->selectRaw('status, count(*) as total')->groupBy('status')->toBase()->get() as $row) {
            $status = $row->status ?? null;
            $total = $row->total ?? 0;
            if (is_string($status)) {
                $statuses[$status] = is_numeric($total) ? (int) $total : 0;
            }
        }

        $start = Carbon::now()->startOfWeek()->subWeeks($weeks - 1);
        $votes = array_fill(0, $weeks, 0);
        $posted = array_fill(0, $weeks, 0);

        $bucket = function (?Carbon $at) use ($start, $weeks): ?int {
            $index = $at === null ? -1 : (int) floor($start->diffInDays($at) / 7);

            return $index >= 0 && $index < $weeks ? $index : null;
        };

        Vote::query()->where('created_at', '>=', $start)->select('created_at')->orderBy('id')
            ->each(function (Vote $vote) use (&$votes, $bucket): void {
                $index = $bucket($vote->created_at);
                if ($index !== null) {
                    $votes[$index]++;
                }
            });

        FeatureRequest::query()->whereNull('merged_into_id')->where('created_at', '>=', $start)->select('created_at')->orderBy('id')
            ->each(function (FeatureRequest $request) use (&$posted, $bucket): void {
                $index = $bucket($request->created_at);
                if ($index !== null) {
                    $posted[$index]++;
                }
            });

        $series = [];
        for ($i = 0; $i < $weeks; $i++) {
            $series[] = ['start' => $start->copy()->addWeeks($i), 'votes' => $votes[$i], 'requests' => $posted[$i]];
        }

        return [
            'statuses' => $statuses,
            'pending' => FeatureRequest::query()->whereNull('merged_into_id')->whereNull('approved_at')->count(),
            'top' => FeatureRequest::query()->listed()->whereIn('status', Palette::openStatuses())->orderByDesc('votes_count')->orderByDesc('id')->limit(10)->get(),
            'weeks' => $series,
            'votes' => Vote::query()->count(),
            'voters' => DB::query()->fromSub(Vote::query()->select(['voter_type', 'voter_id'])->distinct(), 'voters')->count(),
        ];
    }

    // --- Permissions --------------------------------------------------------

    /**
     * Whether the user may do this. Each ability is a gate you can define;
     * until you do, these defaults apply:
     *
     *   feedback-manage   nobody
     *   feedback-post     any signed-in user
     *   feedback-vote     any signed-in user ($user, $request)
     *   feedback-comment  any signed-in user ($user, $request)
     */
    public static function allows(string $ability, ?Authenticatable $user, mixed ...$arguments): bool
    {
        if ($user === null) {
            return false;
        }

        if (Gate::has($ability)) {
            return Gate::forUser($user)->allows($ability, $arguments);
        }

        return match ($ability) {
            'feedback-post', 'feedback-vote', 'feedback-comment' => true,
            default => false,
        };
    }

    /**
     * @throws NotAllowed
     */
    public static function authorize(string $ability, ?Authenticatable $user, mixed ...$arguments): void
    {
        if (! self::allows($ability, $user, ...$arguments)) {
            throw new NotAllowed($ability);
        }
    }

    /**
     * Whether the user may manage the board and changelog. Define a
     * `feedback-manage` gate to decide; until you do, nobody can.
     */
    public static function canManage(mixed $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof Authenticatable && self::allows('feedback-manage', $user);
    }

    // --- Helpers ------------------------------------------------------------

    /**
     * Markdown to safe HTML. $trusted keeps images (changelog entries);
     * otherwise they become links.
     */
    public static function render(string $markdown, bool $trusted = false): string
    {
        return app(Renderer::class)->render($markdown, $trusted);
    }

    /**
     * Which comment thread requests use: 'builtin', 'ruvelo'
     * (ruvelo/laravel-comments) or null when comments are off.
     */
    public static function commentsDriver(): ?string
    {
        $driver = config('feedback.comments.driver', 'auto');

        if (! is_string($driver) || in_array($driver, ['', 'off', 'none', 'false'], true)) {
            return null;
        }

        $ruvelo = class_exists(Comments::class) && app()->getProviders(CommentsServiceProvider::class) !== [];

        return match ($driver) {
            'builtin' => 'builtin',
            default => $ruvelo ? 'ruvelo' : 'builtin',
        };
    }

    /**
     * Whether a section (board, roadmap, changelog) is switched on.
     */
    public static function enabled(string $section): bool
    {
        return (bool) config('feedback.sections.'.$section, true);
    }

    /**
     * A table name with the configured prefix.
     */
    public static function table(string $name): string
    {
        return config('feedback.table_prefix', 'feedback_').$name;
    }

    /**
     * The view every page extends: the package's own page, or a bridge into
     * the host app's layout.
     *
     * @internal
     */
    public static function layout(): string
    {
        return config('feedback.layout') ? 'feedback::embedded' : 'feedback::layout';
    }

    /**
     * Recount a request's votes and comments.
     *
     * @internal
     */
    public static function recount(FeatureRequest $request): void
    {
        $comments = match (self::commentsDriver()) {
            'ruvelo' => Comments::countFor(CommentableRequest::wrap($request)),
            default => $request->comments()->count(),
        };

        $request->forceFill([
            'votes_count' => $request->votes()->count(),
            'comments_count' => $comments,
        ])->saveQuietly();
    }

    /**
     * @throws InvalidInput
     */
    private static function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        $max = (int) config('feedback.max_length.title', 160);

        if (mb_strlen($title) < 3) {
            throw new InvalidInput('Give your request a title of at least 3 characters.', 'title');
        }
        if (mb_strlen($title) > $max) {
            throw new InvalidInput("Keep the title under {$max} characters.", 'title');
        }

        return $title;
    }

    /**
     * @throws InvalidInput
     */
    private static function cleanBody(?string $body): ?string
    {
        $body = $body === null ? null : trim($body);
        $max = (int) config('feedback.max_length.body', 10000);

        if ($body !== null && mb_strlen($body) > $max) {
            throw new InvalidInput("Keep the description under {$max} characters.", 'body');
        }

        return $body === '' ? null : $body;
    }

    /**
     * @throws InvalidInput
     */
    private static function cleanUrl(?string $url): ?string
    {
        $url = $url === null ? '' : trim($url);

        if ($url === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new InvalidInput('The cover image needs an http or https address.', 'cover_url');
        }

        return $url;
    }

    private static function uniqueEntrySlug(string $title): string
    {
        $base = Entry::slugFor($title);
        $slug = $base;

        for ($i = 2; Entry::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * @throws CategoryNotFound
     */
    private static function resolveCategory(Category|string $category): Category
    {
        if ($category instanceof Category) {
            return $category;
        }

        return Category::query()->where('slug', Str::slug($category))->orWhere('name', $category)->first()
            ?? (ctype_digit($category) ? Category::query()->find((int) $category) : null)
            ?? throw new CategoryNotFound($category);
    }

    /**
     * @param  iterable<Tag|string>  $tags
     * @return list<int>
     */
    private static function resolveTags(iterable $tags): array
    {
        $ids = [];
        foreach ($tags as $tag) {
            $ids[] = ($tag instanceof Tag ? $tag : self::tag($tag))->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  iterable<FeatureRequest|int>  $requests
     * @return Collection<int, FeatureRequest>
     */
    private static function resolveRequests(iterable $requests): Collection
    {
        $ids = [];
        foreach ($requests as $request) {
            $ids[] = $request instanceof FeatureRequest ? $request->id : (int) $request;
        }

        return FeatureRequest::query()->whereKey(array_values(array_unique($ids)))->get();
    }
}
