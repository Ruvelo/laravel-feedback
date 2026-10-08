<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\Category;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Models\Tag;
use Ruvelo\Feedback\Notifications\RequestStatusChanged;
use Ruvelo\Feedback\Tests\Fixtures\User;
use Ruvelo\Feedback\Tests\TestCase;

class ManageTest extends TestCase
{
    private function maya(): User
    {
        $this->managers('Maya Okafor');

        return User::query()->where('name', 'Maya Okafor')->first() ?? $this->user('Maya Okafor');
    }

    public function test_nobody_manages_until_the_gate_is_defined(): void
    {
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');

        foreach (['/feedback/manage', '/feedback/manage/changelog', '/feedback/manage/categories', '/feedback/manage/stats', '/feedback/manage/changelog/new'] as $url) {
            $this->actingAs($ada)->get($url)->assertForbidden();
        }
        $this->actingAs($ada)->post('/feedback/manage/requests/'.$request->id.'/status', ['status' => 'planned'])->assertForbidden();
        $this->actingAs($ada)->get('/feedback')->assertDontSee('>Manage</a>', false);

        $this->assertSame('under_review', $request->refresh()->status);
    }

    public function test_guests_get_a_403_without_a_login_route_and_401_from_json(): void
    {
        $this->get('/feedback/manage')->assertForbidden();
        $this->getJson('/feedback/manage')->assertUnauthorized();
    }

    public function test_managers_see_the_admin_area(): void
    {
        $maya = $this->maya();
        Feedback::post($this->user(), 'Dark mode');
        config(['feedback.board.require_approval' => true]);
        Feedback::post($this->user('Tom Reyes'), 'SEPA Instant');

        $this->actingAs($maya)->get('/feedback')->assertSee('>Manage</a>', false);
        $this->actingAs($maya)->get('/feedback/manage')->assertOk()->assertSee('Dark mode')->assertSee('SEPA Instant')->assertSee('1 waiting');
        $this->actingAs($maya)->get('/feedback/manage?tab=pending')->assertOk()->assertSee('SEPA Instant')->assertDontSee('Dark mode</a>', false)->assertSee('Approve');
        $this->actingAs($maya)->get('/feedback/manage?status=planned')->assertOk()->assertSee('No requests match');
        $this->actingAs($maya)->get('/feedback/manage/stats')->assertOk()->assertSee('Votes per week')->assertSee('Top open requests');
    }

    public function test_changing_status_with_a_note_tells_voters(): void
    {
        Notification::fake();
        $maya = $this->maya();
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');

        $this->actingAs($maya)->get($request->url())->assertSee('Manage this request');
        $this->actingAs($maya)->post('/feedback/manage/requests/'.$request->id.'/status', ['status' => 'planned', 'note' => 'Next **month**', 'notify' => '1'])
            ->assertRedirect($request->url())
            ->assertSessionHas('feedback.status', 'Status changed to Planned. Voters will hear about it.');

        $this->assertSame('planned', $request->refresh()->status);
        Notification::assertSentTo($ada, RequestStatusChanged::class);
        $this->get($request->url())->assertSee('Next <strong>month</strong>', false)->assertSee('Maya Okafor');

        $this->actingAs($maya)->post('/feedback/manage/requests/'.$request->id.'/status', ['status' => 'in_progress', 'notify' => '0']);
        Notification::assertSentToTimes($ada, RequestStatusChanged::class, 1);

        $this->actingAs($maya)->from($request->url())->post('/feedback/manage/requests/'.$request->id.'/status', ['status' => 'bogus'])->assertSessionHasErrors('status');
    }

    public function test_shipping_with_a_changelog_entry(): void
    {
        $maya = $this->maya();
        $request = Feedback::post($this->user(), 'Usage-based billing');
        $entry = Feedback::publish('Usage-based billing is here', 'Body');

        $this->actingAs($maya)->post('/feedback/manage/requests/'.$request->id.'/status', ['status' => 'shipped', 'entry' => $entry->id]);

        $request->refresh();
        $this->assertSame(['shipped', $entry->id], [$request->status, $request->entry_id]);
        $this->get($request->url())->assertSee('Shipped on')->assertSee('Usage-based billing is here');
        $this->get('/feedback/roadmap')->assertSee('Usage-based billing is here');
    }

    public function test_pin_approve_edit_merge_and_delete(): void
    {
        $maya = $this->maya();
        $ada = $this->user();
        $target = Feedback::post($ada, 'Dark mode');
        $duplicate = Feedback::post($this->user('Tom Reyes'), 'Dark theme');
        config(['feedback.board.require_approval' => true]);
        $waiting = Feedback::post($ada, 'SEPA');

        $this->actingAs($maya)->post('/feedback/manage/requests/'.$target->id.'/pin', ['pinned' => 1])->assertRedirect();
        $this->assertTrue($target->refresh()->isPinned());

        $this->actingAs($maya)->from('/feedback/manage')->post('/feedback/manage/requests/'.$waiting->id.'/approve')->assertRedirect('/feedback/manage');
        $this->assertTrue($waiting->refresh()->isApproved());

        Feedback::category('Billing');
        Feedback::tag('Old');
        $this->actingAs($maya)->get('/feedback/manage/requests/'.$target->id.'/edit')->assertOk()->assertSee('Save changes');
        $this->actingAs($maya)->put('/feedback/manage/requests/'.$target->id, [
            'title' => 'Dark mode everywhere', 'body' => 'Edited', 'category' => Category::query()->value('id'), 'tags' => ['old'], 'new_tags' => 'Enterprise, Quick win',
        ])->assertRedirect();
        $target->refresh();
        $this->assertSame(['Dark mode everywhere', 'Edited', 'Billing'], [$target->title, $target->body, $target->category?->name]);
        $this->assertEqualsCanonicalizing(['old', 'enterprise', 'quick-win'], $target->tags->pluck('slug')->all());

        $this->actingAs($maya)->post('/feedback/manage/requests/'.$duplicate->id.'/merge', ['into' => $target->id])
            ->assertRedirect($target->url())->assertSessionHas('feedback.status');
        $this->assertSame(2, $target->refresh()->votes_count);
        $this->actingAs($maya)->from($target->url())->post('/feedback/manage/requests/'.$target->id.'/merge', ['into' => $target->id])->assertSessionHasErrors('into');

        $this->actingAs($maya)->delete('/feedback/manage/requests/'.$target->id)->assertRedirect('/feedback/manage');
        $this->assertSame(['SEPA'], FeatureRequest::query()->pluck('title')->all());
    }

    public function test_the_changelog_editor(): void
    {
        $maya = $this->maya();
        $request = Feedback::post($this->user(), 'Exports');

        $this->actingAs($maya)->get('/feedback/manage/changelog/new?requests[]='.$request->id)->assertOk()->assertSee('Exports')->assertSee('checked', false);

        $this->actingAs($maya)->post('/feedback/manage/changelog/preview', ['body' => "**Hi** <b>x</b>\n\n![a](https://cdn.test/a.png)"])
            ->assertOk()->assertSee('<strong>Hi</strong>', false)->assertSee('&lt;b&gt;', false)->assertSee('<img src="https://cdn.test/a.png"', false);

        // The no-JavaScript preview keeps what was typed.
        $this->actingAs($maya)->post('/feedback/manage/changelog', ['title' => 'Draft title', 'body' => '**Bold**', 'action' => 'preview'])
            ->assertOk()->assertSee('Draft title')->assertSee('<strong>Bold</strong>', false);
        $this->assertSame(0, Entry::query()->count());

        $this->actingAs($maya)->post('/feedback/manage/changelog', [
            'title' => 'Exports are here', 'body' => 'Export **everything**', 'labels' => ['new', 'improved'], 'state' => 'published',
            'published_at' => now()->subMinute()->format('Y-m-d\TH:i'), 'requests' => [$request->id], 'notify' => '1', 'action' => 'save',
        ])->assertRedirect();

        $entry = Entry::query()->sole();
        $this->assertSame(['new', 'improved'], $entry->labelKeys());
        $this->assertTrue($entry->isPublished());
        $this->assertSame('shipped', $request->refresh()->status);
        $this->assertSame((string) $entry->author_id, (string) $maya->id);

        $this->actingAs($maya)->get('/feedback/manage/changelog')->assertOk()->assertSee('Exports are here')->assertSee('Published');
        $this->actingAs($maya)->put('/feedback/manage/changelog/'.$entry->slug, ['title' => 'Exports, now', 'state' => 'draft'])->assertSessionHas('feedback.status', 'Draft saved. Only managers can see it.');
        $this->assertFalse($entry->refresh()->isPublished());

        $this->actingAs($maya)->from('/feedback/manage/changelog/new')->post('/feedback/manage/changelog', ['title' => 'Bad cover', 'cover_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('cover_url');
        $this->actingAs($maya)->from('/feedback/manage/changelog/new')->post('/feedback/manage/changelog', ['title' => 'Bad label', 'labels' => ['shiny']])
            ->assertSessionHasErrors('labels.0');

        $this->actingAs($maya)->delete('/feedback/manage/changelog/'.$entry->slug)->assertRedirect('/feedback/manage/changelog');
        $this->assertSame(0, Entry::query()->count());
    }

    public function test_categories_and_tags_pages(): void
    {
        $maya = $this->maya();

        $this->actingAs($maya)->post('/feedback/manage/categories', ['name' => 'Billing'])->assertRedirect('/feedback/manage/categories');
        $category = Category::query()->sole();
        $this->actingAs($maya)->put('/feedback/manage/categories/'.$category->slug, ['name' => 'Billing and plans', 'position' => 3]);
        $this->assertSame(['Billing and plans', 3], [$category->refresh()->name, $category->position]);

        $this->actingAs($maya)->post('/feedback/manage/tags', ['name' => 'Enterprise']);
        $tag = Tag::query()->sole();
        $this->actingAs($maya)->put('/feedback/manage/tags/'.$tag->slug, ['name' => 'Large teams']);
        $this->actingAs($maya)->get('/feedback/manage/categories')->assertOk()->assertSee('Billing and plans', false)->assertSee('Large teams');

        $this->actingAs($maya)->delete('/feedback/manage/categories/'.$category->slug);
        $this->actingAs($maya)->delete('/feedback/manage/tags/'.$tag->slug);
        $this->assertSame([0, 0], [Category::query()->count(), Tag::query()->count()]);

        $this->actingAs($maya)->from('/feedback/manage/categories')->post('/feedback/manage/categories', ['name' => ''])->assertSessionHasErrors('name');
    }
}
