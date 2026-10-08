<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Illuminate\Support\Carbon;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\Comment;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Tests\TestCase;

class BoardTest extends TestCase
{
    public function test_guests_can_read_the_board_and_a_request(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode', 'For **late** nights');
        Feedback::comment($request, $this->user('Tom Reyes'), 'Me too');

        $this->get('/feedback')->assertOk()->assertSee('Dark mode')->assertSee('Tell us what to build next');

        $this->get($request->url())
            ->assertOk()
            ->assertSee('<strong>late</strong>', false)
            ->assertSee('Me too')
            ->assertSee('Sign in to vote and comment')
            ->assertDontSee('<form class="fb-vote"', false)
            ->assertDontSee('Manage this request');
    }

    public function test_guests_who_try_to_take_part_get_a_403_without_a_login_route(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');

        $this->post($request->url().'/vote')->assertForbidden();
        $this->post('/feedback/requests', ['title' => 'Xero sync'])->assertForbidden();
        $this->post($request->url().'/comments', ['body' => 'Hi'])->assertForbidden();
        $this->get('/feedback/new')->assertForbidden();
        $this->postJson($request->url().'/vote')->assertUnauthorized();

        $this->assertSame(1, $request->refresh()->votes_count);
    }

    public function test_signed_in_people_vote_and_take_their_vote_back(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');
        $tom = $this->user('Tom Reyes');

        $this->actingAs($tom)->get('/feedback')->assertSee('aria-pressed="false"', false);

        $this->actingAs($tom)->from('/feedback')->post($request->url().'/vote', ['vote' => 1])
            ->assertRedirect('/feedback')
            ->assertSessionHas('feedback.status', 'Thanks for your vote.');
        $this->assertSame(2, $request->refresh()->votes_count);

        // Retrying the same click is harmless.
        $this->actingAs($tom)->postJson($request->url().'/vote', ['vote' => 1])->assertOk()->assertExactJson(['voted' => true, 'votes' => 2]);
        $this->actingAs($tom)->get('/feedback')->assertSee('aria-pressed="true"', false);

        $this->actingAs($tom)->postJson($request->url().'/vote')->assertOk()->assertExactJson(['voted' => false, 'votes' => 1]);
    }

    public function test_voting_on_a_closed_request_says_why(): void
    {
        $request = FeatureRequest::factory()->status('closed')->create();
        $tom = $this->user('Tom Reyes');

        $this->actingAs($tom)->postJson($request->url().'/vote', ['vote' => 1])->assertStatus(409)->assertJsonPath('message', 'Voting is closed on this request.');
        $this->actingAs($tom)->from($request->url())->post($request->url().'/vote', ['vote' => 1])
            ->assertRedirect($request->url())->assertSessionHasErrors('vote');
        $this->actingAs($tom)->get($request->url())->assertSee('voting is closed');
    }

    public function test_posting_a_request(): void
    {
        $ada = $this->user();
        $category = Feedback::category('Billing');

        $response = $this->actingAs($ada)->post('/feedback/requests', ['title' => 'Dark mode', 'body' => 'Please', 'category' => $category->id]);

        $request = FeatureRequest::query()->sole();
        $response->assertRedirect($request->url())->assertSessionHas('feedback.status');
        $this->assertSame([$ada->id, 'Billing', 1], [(int) $request->author_id, $request->category?->name, $request->votes_count]);
        $this->actingAs($ada)->get($request->url())->assertSee('Thanks! Your request is on the board');
    }

    public function test_posting_validates(): void
    {
        $ada = $this->user();

        $this->actingAs($ada)->from('/feedback/new')->post('/feedback/requests', ['title' => 'ab'])->assertRedirect('/feedback/new')->assertSessionHasErrors('title');
        $this->actingAs($ada)->postJson('/feedback/requests', ['title' => 'Fine', 'category' => 999])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->actingAs($ada)->postJson('/feedback/requests', ['title' => 'Dark mode', 'body' => 'Hi'])->assertCreated()->assertJsonPath('data.title', 'Dark mode')->assertJsonPath('data.votes', 1);
    }

    public function test_the_honeypot_drops_bots_quietly(): void
    {
        $this->actingAs($this->user())->post('/feedback/requests', ['title' => 'Cheap pills', 'website' => 'http://spam.test'])
            ->assertRedirect('/feedback')->assertSessionHas('feedback.status');

        $request = Feedback::post($this->user('Tom Reyes'), 'Dark mode');
        $this->actingAs($this->user('Kenji Mori'))->post($request->url().'/comments', ['body' => 'Spam', 'website' => 'x'])->assertRedirect();

        $this->assertSame(1, FeatureRequest::query()->count());
        $this->assertSame(0, Comment::query()->count());
    }

    public function test_posting_votes_and_comments_are_rate_limited(): void
    {
        config(['feedback.rate_limits.requests' => 2, 'feedback.rate_limits.votes' => 3, 'feedback.rate_limits.comments' => 1]);
        $ada = $this->user();
        $request = Feedback::post($this->user('Tom Reyes'), 'Dark mode');

        $this->actingAs($ada)->postJson('/feedback/requests', ['title' => 'One'])->assertCreated();
        $this->actingAs($ada)->postJson('/feedback/requests', ['title' => 'Two'])->assertCreated();
        $this->actingAs($ada)->postJson('/feedback/requests', ['title' => 'Three'])->assertTooManyRequests();

        foreach ([200, 200, 200, 429] as $status) {
            $this->actingAs($ada)->postJson($request->url().'/vote')->assertStatus($status);
        }

        $this->actingAs($ada)->postJson($request->url().'/comments', ['body' => 'One'])->assertCreated();
        $this->actingAs($ada)->postJson($request->url().'/comments', ['body' => 'Two'])->assertTooManyRequests();

        // Limits are per person.
        $this->actingAs($this->user('Kenji Mori'))->postJson('/feedback/requests', ['title' => 'Four'])->assertCreated();
    }

    public function test_filters_sorting_and_search(): void
    {
        $ada = $this->user();
        $billing = Feedback::category('Billing');
        FeatureRequest::factory()->titled('Usage-based billing')->in($billing)->status('planned')->create(['votes_count' => 30, 'created_at' => Carbon::now()->subDays(30)]);
        FeatureRequest::factory()->titled('Dark mode')->create(['votes_count' => 50, 'created_at' => Carbon::now()->subDays(20)]);
        FeatureRequest::factory()->titled('Brand new idea')->create(['votes_count' => 1]);
        FeatureRequest::factory()->titled('Shipped thing')->status('shipped')->create(['votes_count' => 99]);
        $tagged = Feedback::post($ada, 'Tagged request', null, null, ['Enterprise']);

        $this->get('/feedback')->assertSeeInOrder(['Dark mode', 'Usage-based billing', 'Brand new idea'])->assertDontSee('Shipped thing');
        $this->get('/feedback?sort=new')->assertSeeInOrder(['Tagged request', 'Brand new idea', 'Dark mode', 'Usage-based billing']);
        $this->get('/feedback?status=shipped')->assertSee('Shipped thing')->assertDontSee('Dark mode');
        $this->get('/feedback?status=all')->assertSee('Shipped thing')->assertSee('Dark mode');
        $this->get('/feedback?category=billing')->assertSee('Usage-based billing')->assertDontSee('Dark mode');
        $this->get('/feedback?tag=enterprise')->assertSee('Tagged request')->assertDontSee('Dark mode');
        $this->get('/feedback?q=dark')->assertSee('Dark mode')->assertDontSee('Brand new idea')->assertSee('Clear filters');
        $this->get('/feedback?q=nothing+like+this')->assertSee('No requests match')->assertSee('title=nothing', false);
        $this->get('/feedback?status=bogus&sort=bogus&category=../x')->assertOk()->assertSeeInOrder(['Dark mode', 'Usage-based billing']);
        $this->assertSame($tagged->id, FeatureRequest::query()->whereHas('tags')->value('id'));
    }

    public function test_trending_counts_recent_votes_and_pinned_requests_come_first(): void
    {
        $voters = [$this->user('A One'), $this->user('B Two'), $this->user('C Three')];
        $old = FeatureRequest::factory()->titled('Old favourite')->create();
        $hot = FeatureRequest::factory()->titled('Hot right now')->create();
        $pinned = FeatureRequest::factory()->titled('Pinned note')->pinned()->create();

        Carbon::setTestNow(Carbon::now()->subDays(60));
        foreach ($voters as $voter) {
            Feedback::vote($old, $voter);
        }
        Carbon::setTestNow();
        Feedback::vote($hot, $voters[0]);
        Feedback::vote($hot, $voters[1]);

        $this->get('/feedback')->assertSeeInOrder(['Pinned note', 'Old favourite', 'Hot right now']);
        $this->get('/feedback?sort=trending')->assertSeeInOrder(['Pinned note', 'Hot right now', 'Old favourite']);
        $this->assertTrue($pinned->isPinned());
    }

    public function test_waiting_requests_show_to_their_author_only(): void
    {
        config(['feedback.board.require_approval' => true]);
        $ada = $this->user();
        $this->actingAs($ada)->post('/feedback/requests', ['title' => 'Dark mode'])->assertSessionHas('feedback.status', 'Thanks! Your request will appear once the team has looked at it.');
        $request = FeatureRequest::query()->sole();

        $this->actingAs($ada)->get('/feedback')->assertSee('Dark mode')->assertSee('Waiting for approval');
        $this->actingAs($ada)->get($request->url())->assertOk();
        $this->actingAs($this->user('Tom Reyes'))->get('/feedback')->assertDontSee('Dark mode');
        $this->actingAs($this->user('Kenji Mori'))->get($request->url())->assertNotFound();
        $this->actingAs($this->user('Ines Laurent'))->post($request->url().'/vote')->assertNotFound();
    }

    public function test_merged_requests_redirect_to_the_one_they_joined(): void
    {
        $ada = $this->user();
        $target = Feedback::post($ada, 'Dark mode');
        $duplicate = Feedback::post($ada, 'Dark theme');
        Feedback::merge($duplicate, $target);

        $this->get('/feedback')->assertDontSee('Dark theme');
        $this->get($duplicate->url())->assertStatus(301)->assertRedirect($target->url());
        $this->get($target->url())->assertSee('Also asked as')->assertSee('Dark theme');
        $this->get('/feedback/requests/999')->assertNotFound();
        $this->get('/feedback/requests/not-a-number')->assertNotFound();
    }

    public function test_old_links_keep_working_after_a_rename(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');
        $old = $request->url();
        Feedback::update($request, ['title' => 'Dark theme']);

        $this->get($old)->assertOk()->assertSee('Dark theme');
    }

    public function test_similar_requests_as_you_type(): void
    {
        FeatureRequest::factory()->titled('QuickBooks Online integration')->create(['votes_count' => 7]);
        FeatureRequest::factory()->titled('Dark mode')->create();

        $this->getJson('/feedback/similar?q=QuickBooks+sync')
            ->assertOk()
            ->assertJsonPath('query', 'QuickBooks sync')
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.title', 'QuickBooks Online integration')
            ->assertJsonPath('results.0.votes', 7)
            ->assertJsonPath('results.0.status_label', 'Under review')
            ->assertJsonPath('results.0.voted', false);

        // Without JavaScript, the form page lists them when a title is passed.
        $this->actingAs($this->user())->get('/feedback/new?title=QuickBooks')->assertOk()->assertSee('Similar requests')->assertSee('QuickBooks Online integration');
        $this->actingAs($this->user('Tom Reyes'))->get('/feedback/new')->assertOk()->assertSee('Post request');
    }

    public function test_comments_and_replies_through_the_page(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');
        $tom = $this->user('Tom Reyes');

        $this->actingAs($tom)->post($request->url().'/comments', ['body' => 'Me **too**'])->assertRedirect();
        $comment = Comment::query()->sole();
        $this->actingAs($tom)->post($request->url().'/comments', ['body' => 'Replying', 'parent_id' => $comment->id]);

        $this->actingAs($tom)->get($request->url().'?reply='.$comment->id)
            ->assertSee('Me <strong>too</strong>', false)
            ->assertSee('Replying')
            ->assertSee('Post reply');
        $this->actingAs($tom)->from($request->url())->post($request->url().'/comments', ['body' => ''])->assertSessionHasErrors('body');
        $this->assertSame(2, $request->refresh()->comments_count);
    }

    public function test_only_the_author_or_a_manager_deletes_a_comment(): void
    {
        $this->managers('Maya Okafor');
        $request = Feedback::post($this->user(), 'Dark mode');
        $tom = $this->user('Tom Reyes');
        $mine = Feedback::comment($request, $tom, 'Mine');
        $theirs = Feedback::comment($request, $this->user('Kenji Mori'), 'Theirs');

        $this->actingAs($tom)->delete('/feedback/comments/'.$theirs->id)->assertForbidden();
        $this->actingAs($tom)->delete('/feedback/comments/'.$mine->id)->assertRedirect();
        $this->actingAs($this->user('Maya Okafor'))->deleteJson('/feedback/comments/'.$theirs->id)->assertOk();

        $this->assertSame(0, Comment::query()->count());
    }

    public function test_everything_people_write_is_escaped(): void
    {
        $evil = '<script>alert("x")</script>';
        $ada = $this->user('<img src=x onerror=alert(1)> Ada', ['email' => 'evil@example.com']);
        $request = Feedback::post($ada, $evil.' title', "Body {$evil}\n\n[click](javascript:alert(1)) ![pic](https://evil.test/track.png)");
        Feedback::comment($request, $ada, "Comment {$evil}");
        Feedback::changeStatus($request, 'planned', "Note {$evil}");
        Feedback::category($evil.' cat');

        foreach (['/feedback', $request->url(), '/feedback/roadmap'] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString($evil, $html, $url);
            $this->assertStringNotContainsString('<img src=x', $html, $url);
            $this->assertStringNotContainsString('javascript:alert', $html, $url);
        }

        $html = (string) $this->get($request->url())->getContent();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<img src="https://evil.test', $html, 'Images in requests become links.');
        $this->assertStringContainsString('href="https://evil.test/track.png"', $html);
    }
}
