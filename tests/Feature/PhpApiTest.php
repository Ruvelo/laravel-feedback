<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Ruvelo\Feedback\Events\CategoryDeleted;
use Ruvelo\Feedback\Events\CategorySaved;
use Ruvelo\Feedback\Events\CommentDeleted;
use Ruvelo\Feedback\Events\CommentPosted;
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
use Ruvelo\Feedback\Exceptions\FeedbackException;
use Ruvelo\Feedback\Exceptions\InvalidInput;
use Ruvelo\Feedback\Exceptions\InvalidParent;
use Ruvelo\Feedback\Exceptions\InvalidStatus;
use Ruvelo\Feedback\Exceptions\NotAllowed;
use Ruvelo\Feedback\Exceptions\VotingClosed;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\Comment;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Models\StatusChange;
use Ruvelo\Feedback\Models\Tag;
use Ruvelo\Feedback\Models\Vote;
use Ruvelo\Feedback\Tests\TestCase;

class PhpApiTest extends TestCase
{
    public function test_posting_a_request_votes_for_it_and_fires_an_event(): void
    {
        Event::fake([RequestPosted::class]);
        $ada = $this->user();
        Feedback::category('Billing');

        $request = Feedback::post($ada, '  Dark   mode ', 'For **late** nights', 'billing', ['Enterprise', 'Quick win']);

        $this->assertSame('Dark mode', $request->title);
        $this->assertSame('dark-mode', $request->slug);
        $this->assertSame('under_review', $request->status);
        $this->assertTrue($request->isApproved());
        $this->assertSame('Billing', $request->category?->name);
        $this->assertSame(['enterprise', 'quick-win'], $request->tags()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(1, $request->votes_count);
        $this->assertTrue($request->hasVoter($ada));
        $this->assertTrue($request->isAuthoredBy($ada));
        Event::assertDispatched(RequestPosted::class, fn (RequestPosted $event) => $event->request->is($request));
    }

    public function test_titles_and_descriptions_are_validated(): void
    {
        $ada = $this->user();

        foreach (['', 'ab', str_repeat('x', 161)] as $title) {
            try {
                Feedback::post($ada, $title);
                $this->fail("“{$title}” was accepted.");
            } catch (InvalidInput $e) {
                $this->assertSame('title', $e->field());
                $this->assertInstanceOf(FeedbackException::class, $e);
            }
        }

        $this->expectException(InvalidInput::class);
        Feedback::post($ada, 'Fine title', str_repeat('x', 10001));
    }

    public function test_an_unknown_category_is_refused(): void
    {
        $this->expectException(CategoryNotFound::class);

        Feedback::post($this->user(), 'Dark mode', null, 'Nope');
    }

    public function test_requests_wait_for_approval_when_it_is_on_except_from_managers(): void
    {
        Event::fake([RequestApproved::class]);
        config(['feedback.board.require_approval' => true]);
        $this->managers('Maya Okafor');

        $waiting = Feedback::post($this->user(), 'Dark mode');
        $managers = Feedback::post($this->user('Maya Okafor'), 'Xero sync');

        $this->assertFalse($waiting->isApproved());
        $this->assertTrue($waiting->isLocked());
        $this->assertTrue($managers->isApproved());

        Feedback::approve($waiting);
        Feedback::approve($waiting);

        $this->assertTrue($waiting->refresh()->isApproved());
        Event::assertDispatchedTimes(RequestApproved::class, 1);
    }

    public function test_one_vote_per_person(): void
    {
        Event::fake([RequestVoted::class]);
        $request = Feedback::post($this->user(), 'Dark mode');
        $tom = $this->user('Tom Reyes');

        $this->assertTrue(Feedback::vote($request, $tom));
        $this->assertFalse(Feedback::vote($request, $tom));
        $this->assertFalse(Feedback::vote($request->fresh() ?? $request, $tom));

        $this->assertSame(2, $request->refresh()->votes_count);
        $this->assertSame(1, Vote::query()->where('voter_id', $tom->id)->count());
        Event::assertDispatchedTimes(RequestVoted::class, 1);
    }

    public function test_the_database_refuses_a_second_vote_too(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');
        $tom = $this->user('Tom Reyes');
        Feedback::vote($request, $tom);

        $this->expectException(QueryException::class);

        DB::table(Feedback::table('votes'))->insert(['request_id' => $request->id, 'voter_type' => $tom->getMorphClass(), 'voter_id' => $tom->id]);
    }

    public function test_votes_can_be_taken_back_and_toggled(): void
    {
        Event::fake([RequestVoted::class]);
        $request = Feedback::post($this->user(), 'Dark mode');
        $tom = $this->user('Tom Reyes');

        $this->assertTrue(Feedback::toggleVote($request, $tom));
        $this->assertSame(2, $request->refresh()->votes_count);
        $this->assertFalse(Feedback::toggleVote($request, $tom));
        $this->assertSame(1, $request->refresh()->votes_count);
        $this->assertFalse(Feedback::unvote($request, $tom));

        Event::assertDispatched(RequestVoted::class, fn (RequestVoted $event) => $event->added === false && $event->voter->is($tom));
    }

    public function test_shipped_closed_merged_and_waiting_requests_take_no_votes(): void
    {
        $ada = $this->user();
        $tom = $this->user('Tom Reyes');

        foreach (['shipped', 'closed'] as $status) {
            $request = FeatureRequest::factory()->status($status)->create();
            try {
                Feedback::vote($request, $tom);
                $this->fail("Voted on a {$status} request.");
            } catch (VotingClosed $e) {
                $this->assertSame(409, $e->status());
            }
        }

        $target = Feedback::post($ada, 'Dark mode');
        $duplicate = Feedback::post($ada, 'Dark theme');
        Feedback::merge($duplicate, $target);

        try {
            Feedback::vote($duplicate->refresh(), $tom);
            $this->fail('Voted on a merged request.');
        } catch (VotingClosed $e) {
            $this->assertStringContainsString('merged', $e->getMessage());
        }

        $this->expectException(VotingClosed::class);
        Feedback::vote(FeatureRequest::factory()->pending()->create(), $tom);
    }

    public function test_a_vote_on_a_locked_request_can_still_be_taken_back(): void
    {
        $tom = $this->user('Tom Reyes');
        $request = Feedback::post($this->user(), 'Dark mode');
        Feedback::vote($request, $tom);
        Feedback::changeStatus($request, 'closed');

        $this->assertTrue(Feedback::unvote($request, $tom));
        $this->assertSame(1, $request->refresh()->votes_count);
    }

    public function test_status_changes_are_recorded_with_their_note(): void
    {
        Event::fake([StatusChanged::class]);
        $maya = $this->user('Maya Okafor');
        $request = Feedback::post($this->user(), 'Dark mode');

        Feedback::changeStatus($request, 'planned', '  Starting in March.  ', $maya);

        $this->assertSame('planned', $request->status);
        $this->assertSame('Starting in March.', $request->status_note);
        $this->assertNotNull($request->status_changed_at);
        $change = StatusChange::query()->sole();
        $this->assertSame([null, 'under_review', 'planned', 'Maya Okafor'], [null, $change->from, $change->to, $change->changerName()]);
        Event::assertDispatched(StatusChanged::class, fn (StatusChanged $event) => $event->from === 'under_review' && $event->to === 'planned' && $event->by?->is($maya));
    }

    public function test_the_same_status_without_a_note_changes_nothing_and_with_a_note_posts_an_update(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');
        Feedback::changeStatus($request, 'planned', 'First note');

        Feedback::changeStatus($request, 'planned');
        $this->assertSame(1, StatusChange::query()->count());
        $this->assertSame('First note', $request->status_note);

        Feedback::changeStatus($request, 'planned', 'Design is done.');
        $this->assertSame(2, StatusChange::query()->count());
        $this->assertSame('Design is done.', $request->refresh()->status_note);
    }

    public function test_unknown_statuses_are_refused_and_custom_ones_work(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');

        try {
            Feedback::changeStatus($request, 'done');
            $this->fail('Accepted an unknown status.');
        } catch (InvalidStatus $e) {
            $this->assertStringContainsString('under_review, planned', $e->getMessage());
        }

        config(['feedback.statuses.beta' => ['label' => 'In beta', 'color' => '#0f766e']]);
        Feedback::changeStatus($request, 'beta');
        $this->assertSame('In beta', $request->statusLabel());
    }

    public function test_merging_carries_votes_over_without_double_counting(): void
    {
        Event::fake([RequestsMerged::class]);
        $ada = $this->user();
        $tom = $this->user('Tom Reyes');
        $ines = $this->user('Inès Laurent');
        $kenji = $this->user('Kenji Mori');

        $target = Feedback::post($ada, 'Dark mode');          // Ada
        Feedback::vote($target, $tom);                          // Ada, Tom
        $duplicate = Feedback::post($ines, 'Dark theme please'); // Inès
        Feedback::vote($duplicate, $tom);                       // Inès, Tom
        Feedback::vote($duplicate, $kenji);                     // Inès, Tom, Kenji
        $comment = Feedback::comment($duplicate, $kenji, 'Yes please');

        $result = Feedback::merge($duplicate, $target, $ada);

        $this->assertTrue($result->is($target));
        $this->assertSame(4, $target->refresh()->votes_count); // Ada, Tom, Inès, Kenji
        $this->assertSame(0, $duplicate->refresh()->votes_count);
        $this->assertSame($target->id, $duplicate->merged_into_id);
        $this->assertTrue($target->hasVoter($ines));
        $this->assertTrue($target->hasVoter($kenji));
        $this->assertSame($target->id, $comment->refresh()->request_id);
        $this->assertSame(1, $target->comments_count);
        $this->assertSame(['Dark theme please'], $target->duplicates()->pluck('title')->all());
        Event::assertDispatched(RequestsMerged::class, fn ($event) => $event->movedVotes === 2 && $event->duplicate->is($duplicate));
    }

    public function test_requests_merged_into_a_duplicate_follow_it(): void
    {
        $ada = $this->user();
        $a = Feedback::post($ada, 'Dark mode');
        $b = Feedback::post($ada, 'Dark theme');
        $c = Feedback::post($ada, 'Night mode');

        Feedback::merge($c, $b);
        Feedback::merge($b, $a);

        $this->assertSame($a->id, $c->refresh()->merged_into_id);
        $this->assertEqualsCanonicalizing(['Dark theme', 'Night mode'], $a->duplicates()->pluck('title')->all());
    }

    public function test_merge_refuses_nonsense(): void
    {
        $ada = $this->user();
        $a = Feedback::post($ada, 'Dark mode');
        $b = Feedback::post($ada, 'Dark theme');

        foreach ([[$a, $a], [$b, $a], [$a, $b]] as $i => [$from, $into]) {
            try {
                if ($i === 1) {
                    Feedback::merge($b, $a);
                    Feedback::merge($b->refresh(), $a);
                } elseif ($i === 2) {
                    Feedback::merge($a, $b->refresh());
                } else {
                    Feedback::merge($from, $into);
                }
                $this->fail('Merge '.$i.' went through.');
            } catch (CannotMerge $e) {
                $this->assertSame('into', $e->field());
            }
        }
    }

    public function test_comments_and_replies(): void
    {
        Event::fake([CommentPosted::class, CommentDeleted::class]);
        $this->managers('Maya Okafor');
        $request = Feedback::post($this->user(), 'Dark mode');

        $first = Feedback::comment($request, $this->user('Tom Reyes'), 'Me too');
        $reply = Feedback::comment($request, $this->user('Maya Okafor'), 'Planned!', $first);
        $nested = Feedback::comment($request, $this->user('Kenji Mori'), 'Great', $reply);

        $this->assertFalse($first->official);
        $this->assertTrue($reply->official);
        $this->assertSame($first->id, $reply->parent_id);
        $this->assertSame($first->id, $nested->parent_id, 'Replies to replies join the same thread.');
        $this->assertSame(3, $request->refresh()->comments_count);

        Feedback::deleteComment($first);
        $this->assertSame(0, Comment::query()->count());
        $this->assertSame(0, $request->refresh()->comments_count);
        Event::assertDispatchedTimes(CommentPosted::class, 3);
        Event::assertDispatched(CommentDeleted::class);
    }

    public function test_comments_are_validated(): void
    {
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');
        $other = Feedback::post($ada, 'Xero sync');
        $elsewhere = Feedback::comment($other, $ada, 'Hi');

        foreach (['   ', str_repeat('x', 5001)] as $body) {
            try {
                Feedback::comment($request, $ada, $body);
                $this->fail('Comment accepted.');
            } catch (InvalidInput $e) {
                $this->assertSame('body', $e->field());
            }
        }

        $this->expectException(InvalidParent::class);
        Feedback::comment($request, $ada, 'Reply', $elsewhere);
    }

    public function test_editing_pinning_and_deleting(): void
    {
        Event::fake([RequestUpdated::class, RequestPinned::class, RequestDeleted::class]);
        $ada = $this->user();
        Feedback::category('Billing');
        $request = Feedback::post($ada, 'Dark mode', null, null, ['Old']);
        Feedback::comment($request, $ada, 'Hi');

        Feedback::update($request, ['title' => 'Dark theme', 'body' => 'Please', 'category' => 'Billing', 'tags' => ['New tag']]);
        $this->assertSame(['dark-theme', 'Please', 'Billing'], [$request->slug, $request->body, $request->category?->name]);
        $this->assertSame(['new-tag'], $request->tags->pluck('slug')->all());

        Feedback::pin($request);
        Feedback::pin($request);
        $this->assertTrue($request->isPinned());
        Feedback::pin($request, false);
        $this->assertFalse($request->isPinned());
        Event::assertDispatchedTimes(RequestPinned::class, 2);

        Feedback::delete($request);
        $this->assertSame(0, FeatureRequest::query()->count());
        $this->assertSame(0, Vote::query()->count());
        $this->assertSame(0, Comment::query()->count());
        Event::assertDispatched(RequestUpdated::class);
        Event::assertDispatched(RequestDeleted::class);
    }

    public function test_categories_and_tags(): void
    {
        Event::fake([CategorySaved::class, CategoryDeleted::class, TagSaved::class, TagDeleted::class]);

        $billing = Feedback::category('Billing');
        $this->assertTrue(Feedback::category('billing')->is($billing));
        $this->assertSame(2, Feedback::category('Reporting')->position);
        Feedback::renameCategory($billing, 'Billing and plans', 5);
        $this->assertSame([5, 'billing'], [$billing->refresh()->position, $billing->slug]);

        $request = Feedback::post($this->user(), 'Dark mode', null, $billing, ['Enterprise']);
        Feedback::deleteCategory($billing);
        $this->assertNull($request->refresh()->category_id);

        $tag = Tag::query()->sole();
        $this->assertTrue(Feedback::tag('enterprise')->is($tag));
        Feedback::renameTag($tag, 'Large teams');
        Feedback::deleteTag($tag);
        $this->assertSame(0, $request->tags()->count());

        Event::assertDispatched(CategorySaved::class);
        Event::assertDispatched(CategoryDeleted::class);
        Event::assertDispatched(TagSaved::class);
        Event::assertDispatched(TagDeleted::class);

        $this->expectException(InvalidInput::class);
        Feedback::category('  ');
    }

    public function test_similar_requests_rank_the_most_specific_match_first(): void
    {
        $ada = $this->user();
        $xero = FeatureRequest::factory()->titled('Two-way Xero sync')->votedBy($ada, $this->user('Tom Reyes'))->create();
        $quickbooks = FeatureRequest::factory()->titled('QuickBooks Online integration')->create();
        FeatureRequest::factory()->titled('Dark mode')->create();
        FeatureRequest::factory()->titled('QuickBooks export')->pending()->create();

        $similar = Feedback::similar('Sync invoices with QuickBooks');

        $this->assertSame([$quickbooks->id, $xero->id], $similar->pluck('id')->all());
        $this->assertCount(0, Feedback::similar('ab'));
        $this->assertCount(1, Feedback::similar('quickbooks', 1));
    }

    public function test_the_roadmap_groups_requests_by_status(): void
    {
        $ada = $this->user();
        FeatureRequest::factory()->status('planned')->titled('Less votes')->create(['votes_count' => 1]);
        FeatureRequest::factory()->status('planned')->titled('More votes')->create(['votes_count' => 9]);
        FeatureRequest::factory()->status('in_progress')->titled('Building')->create();
        FeatureRequest::factory()->status('shipped')->titled('Done')->create();
        FeatureRequest::factory()->status('under_review')->titled('Not on the roadmap')->create();
        FeatureRequest::factory()->status('planned')->titled('Waiting')->pending()->create();

        $roadmap = Feedback::roadmap($ada);

        $this->assertSame(['planned', 'in_progress', 'shipped'], array_keys($roadmap));
        $this->assertSame(['More votes', 'Less votes'], $roadmap['planned']->pluck('title')->all());
        $this->assertSame(['Building'], $roadmap['in_progress']->pluck('title')->all());
        $this->assertSame(['Done'], $roadmap['shipped']->pluck('title')->all());
    }

    public function test_board_visibility(): void
    {
        $this->managers('Maya Okafor');
        $ada = $this->user();
        $tom = $this->user('Tom Reyes');
        FeatureRequest::factory()->titled('Public')->create();
        FeatureRequest::factory()->titled('Mine, waiting')->pending()->by($ada)->create();
        $merged = FeatureRequest::factory()->titled('Merged')->create();
        $merged->forceFill(['merged_into_id' => FeatureRequest::query()->value('id')])->save();

        $this->assertSame(['Public'], Feedback::requests()->pluck('title')->all());
        $this->assertSame(['Public'], Feedback::requests($tom)->pluck('title')->all());
        $this->assertEqualsCanonicalizing(['Public', 'Mine, waiting'], Feedback::requests($ada)->pluck('title')->all());
        $this->assertEqualsCanonicalizing(['Public', 'Mine, waiting'], Feedback::requests($this->user('Maya Okafor'))->pluck('title')->all());
    }

    public function test_stats(): void
    {
        $ada = $this->user();
        Carbon::setTestNow(Carbon::now()->subWeeks(2));
        $old = Feedback::post($ada, 'Dark mode');
        Carbon::setTestNow();
        Feedback::vote($old, $this->user('Tom Reyes'));
        Feedback::changeStatus(Feedback::post($ada, 'Xero sync'), 'shipped');
        FeatureRequest::factory()->pending()->create();

        $stats = Feedback::stats(4);

        $this->assertSame(1, $stats['statuses']['shipped']);
        $this->assertSame(1, $stats['statuses']['under_review']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(3, $stats['votes']);
        $this->assertSame(2, $stats['voters']);
        $this->assertCount(4, $stats['weeks']);
        $this->assertSame(3, array_sum(array_column($stats['weeks'], 'votes')));
        $this->assertSame(1, $stats['weeks'][1]['votes'] + $stats['weeks'][2]['votes']);
        $this->assertSame(['Dark mode'], $stats['top']->pluck('title')->all());
    }

    public function test_permissions_default_and_can_be_overridden(): void
    {
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');

        $this->assertFalse(Feedback::canManage($ada), 'Nobody manages until the gate is defined.');
        $this->assertTrue(Feedback::allows('feedback-post', $ada));
        $this->assertTrue(Feedback::allows('feedback-vote', $ada, $request));
        $this->assertFalse(Feedback::allows('feedback-post', null));

        Gate::define('feedback-vote', fn ($user, FeatureRequest $request) => $request->title !== 'Dark mode');
        $this->assertFalse(Feedback::allows('feedback-vote', $ada, $request));

        $this->expectException(NotAllowed::class);
        Feedback::authorize('feedback-vote', $ada, $request);
    }

    public function test_route_keys_survive_a_rename(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');
        $key = $request->getRouteKey();
        $this->assertSame($request->id.'-dark-mode', $key);

        Feedback::update($request, ['title' => 'Dark theme']);

        $this->assertTrue((new FeatureRequest)->resolveRouteBinding($key)?->is($request));
        $this->assertTrue((new FeatureRequest)->resolveRouteBinding((string) $request->id)?->is($request));
        $this->assertNull((new FeatureRequest)->resolveRouteBinding('dark-mode'));
    }

    public function test_factories(): void
    {
        $ada = $this->user();
        $tom = $this->user('Tom Reyes');

        $request = FeatureRequest::factory()->by($ada)->status('planned', 'Soon')->pinned()->votedBy($ada, $tom)->create();

        $this->assertTrue($request->isAuthoredBy($ada));
        $this->assertSame(['planned', 'Soon', 2], [$request->status, $request->status_note, $request->refresh()->votes_count]);
        $this->assertTrue($request->isPinned());
        $this->assertFalse(FeatureRequest::factory()->pending()->create()->isApproved());

        $entry = Entry::factory()->labels('new', 'fixed')->create();
        $this->assertTrue($entry->isPublished());
        $this->assertSame(['new', 'fixed'], $entry->labelKeys());
        $this->assertFalse(Entry::factory()->draft()->create()->isPublished());
        $this->assertTrue(Entry::factory()->publishedAt(now()->addDay())->create()->isScheduled());
    }
}
