<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Notifications\RequestStatusChanged;
use Ruvelo\Feedback\Tests\TestCase;

class JsonApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('feedback.api.enabled', true);
        // Tests sign in through the session; apps usually use Sanctum here.
        $app['config']->set('feedback.api.middleware', ['web']);
        $app['config']->set('feedback.api.write_middleware', []);
    }

    public function test_the_board(): void
    {
        $ada = $this->user();
        Feedback::category('Billing');
        Feedback::post($ada, 'Dark mode', 'Please', 'billing', ['Enterprise']);
        FeatureRequest::factory()->status('shipped')->titled('Shipped thing')->create();

        $this->actingAs($ada)->getJson('/api/feedback/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Dark mode')
            ->assertJsonPath('data.0.html', "<p>Please</p>\n")
            ->assertJsonPath('data.0.status', 'under_review')
            ->assertJsonPath('data.0.status_label', 'Under review')
            ->assertJsonPath('data.0.votes', 1)
            ->assertJsonPath('data.0.voted', true)
            ->assertJsonPath('data.0.author.initials', 'AL')
            ->assertJsonPath('data.0.category.slug', 'billing')
            ->assertJsonPath('data.0.tags.0.slug', 'enterprise')
            ->assertJsonPath('data.0.can.vote', true)
            ->assertJsonPath('data.0.can.manage', false)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.filters.status', 'open');

        $this->getJson('/api/feedback/requests?status=all&sort=new&per_page=1')->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.title', 'Shipped thing');
        $this->getJson('/api/feedback/requests?q=dark')->assertJsonPath('meta.total', 1);
    }

    public function test_reads_are_public_and_writes_need_a_user(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');
        Feedback::comment($request, $this->user('Tom Reyes'), 'Me too');

        $this->getJson('/api/feedback/requests/'.$request->id)->assertOk()->assertJsonPath('data.voted', false)->assertJsonPath('data.history', []);
        $this->getJson('/api/feedback/requests/'.$request->id.'/comments')->assertOk()->assertJsonPath('data.0.body', 'Me too')->assertJsonPath('data.0.author.name', 'Tom Reyes');
        $this->getJson('/api/feedback/requests/similar?q=dark')->assertOk()->assertJsonPath('data.0.title', 'Dark mode');
        $this->getJson('/api/feedback/statuses')->assertOk()->assertJsonPath('data.0.key', 'under_review')->assertJsonPath('data.0.open', true)->assertJsonPath('data.3.locked', true);
        $this->getJson('/api/feedback/categories')->assertOk();
        $this->getJson('/api/feedback/tags')->assertOk();
        $this->getJson('/api/feedback/roadmap')->assertOk()->assertJsonPath('data.0.status', 'planned')->assertJsonPath('data.0.label', 'Planned');

        $this->postJson('/api/feedback/requests', ['title' => 'Xero'])->assertUnauthorized();
        $this->postJson('/api/feedback/requests/'.$request->id.'/vote')->assertUnauthorized();
        $this->postJson('/api/feedback/requests/'.$request->id.'/comments', ['body' => 'x'])->assertUnauthorized();
        $this->patchJson('/api/feedback/requests/'.$request->id, ['status' => 'planned'])->assertUnauthorized();
    }

    public function test_signed_in_people_post_vote_and_comment(): void
    {
        $tom = $this->user('Tom Reyes');

        $id = $this->actingAs($tom)->postJson('/api/feedback/requests', ['title' => 'Xero sync', 'body' => 'Two-way'])
            ->assertCreated()->assertJsonPath('data.votes', 1)->json('data.id');
        $request = FeatureRequest::query()->findOrFail($id);
        $kenji = $this->user('Kenji Mori');

        $this->actingAs($kenji)->postJson('/api/feedback/requests/'.$id.'/vote')->assertOk()->assertExactJson(['voted' => true, 'added' => true, 'votes' => 2]);
        $this->actingAs($kenji)->postJson('/api/feedback/requests/'.$id.'/vote')->assertOk()->assertJsonPath('added', false)->assertJsonPath('votes', 2);
        $this->actingAs($kenji)->deleteJson('/api/feedback/requests/'.$id.'/vote')->assertOk()->assertExactJson(['voted' => false, 'removed' => true, 'votes' => 1]);

        $this->actingAs($kenji)->postJson('/api/feedback/requests/'.$id.'/comments', ['body' => 'Yes'])->assertCreated()->assertJsonPath('data.body', 'Yes')->assertJsonPath('data.can.delete', true);
        $this->actingAs($kenji)->postJson('/api/feedback/requests/'.$id.'/comments', ['body' => ''])->assertUnprocessable();
        $this->assertSame(1, $request->refresh()->comments_count);

        // Managing needs the gate.
        $this->actingAs($kenji)->patchJson('/api/feedback/requests/'.$id, ['status' => 'planned'])->assertForbidden();
        $this->actingAs($kenji)->postJson('/api/feedback/changelog', ['title' => 'x'])->assertForbidden();
    }

    public function test_managers_change_status_merge_and_delete(): void
    {
        Notification::fake();
        $this->managers('Maya Okafor');
        $maya = $this->user('Maya Okafor');
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');
        $duplicate = Feedback::post($this->user('Tom Reyes'), 'Dark theme');

        $this->actingAs($maya)->patchJson('/api/feedback/requests/'.$request->id, ['status' => 'planned', 'note' => 'Soon', 'pinned' => true, 'tags' => ['Enterprise']])
            ->assertOk()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.status_note', 'Soon')
            ->assertJsonPath('data.pinned', true)
            ->assertJsonPath('data.tags.0.name', 'Enterprise')
            ->assertJsonPath('data.history.0.by', 'Maya Okafor')
            ->assertJsonPath('data.can.manage', true);
        Notification::assertSentTo($ada, RequestStatusChanged::class);

        $this->actingAs($maya)->patchJson('/api/feedback/requests/'.$request->id, ['status' => 'nope'])->assertUnprocessable();

        $this->actingAs($maya)->postJson('/api/feedback/requests/'.$duplicate->id.'/merge', ['into' => $request->id])->assertOk()->assertJsonPath('data.votes', 2);
        $this->actingAs($maya)->postJson('/api/feedback/requests/'.$request->id.'/merge', ['into' => $request->id])->assertUnprocessable()->assertJsonPath('errors.into.0', 'A request can’t be merged into itself.');

        config(['feedback.board.require_approval' => true]);
        $waiting = Feedback::post($ada, 'SEPA');
        $this->actingAs($maya)->postJson('/api/feedback/requests/'.$waiting->id.'/approve')->assertOk()->assertJsonPath('data.approved', true);
        $this->actingAs($maya)->deleteJson('/api/feedback/requests/'.$waiting->id)->assertOk()->assertExactJson(['deleted' => true]);
    }

    public function test_the_changelog(): void
    {
        $this->managers('Maya Okafor');
        $maya = $this->user('Maya Okafor');
        $request = Feedback::post($this->user(), 'Exports');

        $entry = $this->actingAs($maya)->postJson('/api/feedback/changelog', ['title' => 'Exports are here', 'body' => '**CSV**', 'labels' => ['new'], 'requests' => [$request->id]])
            ->assertCreated()
            ->assertJsonPath('data.published', true)
            ->assertJsonPath('data.labels.0.label', 'New')
            ->assertJsonPath('data.requests.0.title', 'Exports')
            ->json('data');
        $this->assertSame('shipped', $request->refresh()->status);

        $this->actingAs($maya)->patchJson('/api/feedback/changelog/'.$entry['id'], ['title' => 'Exports, finally'])->assertOk()->assertJsonPath('data.title', 'Exports, finally');
        $this->actingAs($maya)->postJson('/api/feedback/changelog', ['title' => 'Draft', 'published_at' => null])->assertCreated()->assertJsonPath('data.published', false);

        $this->getJson('/api/feedback/changelog')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.html', "<p><strong>CSV</strong></p>\n");
        $this->getJson('/api/feedback/changelog/'.$entry['slug'])->assertOk();
        $this->getJson('/api/feedback/changelog/draft')->assertNotFound();

        $this->actingAs($maya)->deleteJson('/api/feedback/changelog/'.$entry['id'])->assertOk();
        $this->assertSame(1, Entry::query()->count());
    }

    public function test_whats_new_for_custom_widgets(): void
    {
        $ada = $this->user();
        Entry::factory()->create(['title' => 'Fresh', 'published_at' => Carbon::now()->subDay()]);
        Entry::factory()->create(['title' => 'Older', 'published_at' => Carbon::now()->subDays(3)]);

        $this->actingAs($ada)->getJson('/api/feedback/changelog/whats-new?limit=1')
            ->assertOk()->assertJsonPath('unread', 2)->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.title', 'Fresh')->assertJsonPath('last_seen', null);

        $this->actingAs($ada)->postJson('/api/feedback/changelog/seen')->assertOk()->assertExactJson(['unread' => 0]);
        $this->actingAs($ada)->getJson('/api/feedback/changelog/whats-new')->assertJsonPath('unread', 0);

        $this->app['auth']->forgetGuards();
        $since = Carbon::now()->subDays(2)->getTimestamp();
        $this->getJson('/api/feedback/changelog/whats-new?since='.$since)->assertJsonPath('unread', 1);
    }
}
