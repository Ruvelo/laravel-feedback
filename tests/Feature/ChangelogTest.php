<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Ruvelo\Feedback\Events\EntryDeleted;
use Ruvelo\Feedback\Events\EntryPublished;
use Ruvelo\Feedback\Events\EntrySaved;
use Ruvelo\Feedback\Exceptions\InvalidInput;
use Ruvelo\Feedback\Exceptions\InvalidLabel;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\Seen;
use Ruvelo\Feedback\Support\SeenCookie;
use Ruvelo\Feedback\Tests\TestCase;

class ChangelogTest extends TestCase
{
    public function test_publishing_ships_the_linked_requests(): void
    {
        Event::fake([EntrySaved::class, EntryPublished::class]);
        $ada = $this->user();
        $request = Feedback::post($ada, 'Usage-based billing');

        $entry = Feedback::publish('Usage-based billing is here', 'Body', ['new'], 'https://cdn.test/cover.png', requests: [$request]);

        $this->assertTrue($entry->isPublished());
        $this->assertSame('usage-based-billing-is-here', $entry->slug);
        $this->assertSame('https://cdn.test/cover.png', $entry->cover_url);
        $request->refresh();
        $this->assertSame('shipped', $request->status);
        $this->assertSame($entry->id, $request->entry_id);
        Event::assertDispatchedTimes(EntryPublished::class, 1);

        Feedback::saveEntry($entry, ['title' => 'Usage-based billing is live']);
        Event::assertDispatchedTimes(EntryPublished::class, 1);
        Event::assertDispatchedTimes(EntrySaved::class, 2);
    }

    public function test_drafts_and_scheduled_entries_link_requests_without_shipping_them(): void
    {
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');

        $draft = Feedback::saveEntry(new Entry, ['title' => 'Dark mode', 'body' => 'Soon', 'published_at' => null], [$request]);
        $this->assertFalse($draft->isPublished());
        $this->assertSame('under_review', $request->refresh()->status);
        $this->assertSame($draft->id, $request->entry_id);

        $scheduled = Feedback::publish('Next week', 'Body', at: Carbon::now()->addWeek());
        $this->assertTrue($scheduled->isScheduled());
        $this->assertNull(Feedback::entry($scheduled->slug));

        Feedback::saveEntry($draft, ['published_at' => Carbon::now()]);
        $this->assertSame('shipped', $request->refresh()->status);
        $this->assertCount(0, $ada->notifications()->where('data', 'like', '%under_review%')->get());
    }

    public function test_relinking_replaces_the_list_and_slugs_stay_unique(): void
    {
        $ada = $this->user();
        $a = Feedback::post($ada, 'Dark mode');
        $b = Feedback::post($ada, 'Xero sync');
        $entry = Feedback::saveEntry(new Entry, ['title' => 'Update', 'published_at' => null], [$a]);

        Feedback::saveEntry($entry, [], [$b->id]);

        $this->assertNull($a->refresh()->entry_id);
        $this->assertSame($entry->id, $b->refresh()->entry_id);
        $this->assertSame('update-2', Feedback::publish('Update', 'Again')->slug);
    }

    public function test_entries_are_validated(): void
    {
        try {
            Feedback::publish('Title', 'Body', ['shiny']);
            $this->fail('Accepted an unknown label.');
        } catch (InvalidLabel $e) {
            $this->assertStringContainsString('new, improved, fixed', $e->getMessage());
        }

        foreach (['javascript:alert(1)', 'not a url', 'ftp://x.test/a.png'] as $url) {
            try {
                Feedback::publish('Title', 'Body', coverUrl: $url);
                $this->fail("Accepted {$url}");
            } catch (InvalidInput $e) {
                $this->assertSame('cover_url', $e->field());
            }
        }

        $this->expectException(InvalidInput::class);
        Feedback::publish('  ', 'Body');
    }

    public function test_deleting_an_entry_keeps_its_requests_shipped(): void
    {
        Event::fake([EntryDeleted::class]);
        $request = Feedback::post($this->user(), 'Dark mode');
        $entry = Feedback::publish('Dark mode', 'Body', requests: [$request]);

        Feedback::deleteEntry($entry);

        $request->refresh();
        $this->assertSame(['shipped', null], [$request->status, $request->entry_id]);
        Event::assertDispatched(EntryDeleted::class);
    }

    public function test_the_changelog_page_lists_published_entries_newest_first(): void
    {
        Entry::factory()->create(['title' => 'Older', 'published_at' => Carbon::now()->subDays(5)]);
        Entry::factory()->labels('fixed')->create(['title' => 'Newer', 'published_at' => Carbon::now()->subDay()]);
        Entry::factory()->draft()->create(['title' => 'Secret draft']);
        Entry::factory()->publishedAt(Carbon::now()->addDay())->create(['title' => 'Tomorrow']);

        $this->get('/feedback/changelog')
            ->assertOk()
            ->assertSeeInOrder(['Newer', 'Older'])
            ->assertDontSee('Secret draft')
            ->assertDontSee('Tomorrow')
            ->assertSee('application/atom+xml', false);

        $this->get('/feedback/changelog?label=fixed')->assertSee('Newer')->assertDontSee('Older');
    }

    public function test_entry_pages_hide_drafts_except_from_managers(): void
    {
        $this->managers('Maya Okafor');
        $draft = Entry::factory()->draft()->create(['title' => 'Secret draft']);
        $request = Feedback::post($this->user(), 'Exports');
        $entry = Feedback::publish('Exports are here', "## What changed\n\n![Chart](https://cdn.test/chart.png)", requests: [$request]);

        $this->get($draft->url())->assertNotFound();
        $this->actingAs($this->user('Maya Okafor'))->get($draft->url())->assertOk()->assertSee('Only managers can see this');

        $this->get($entry->url())
            ->assertOk()
            ->assertSee('<img src="https://cdn.test/chart.png" alt="Chart"', false)
            ->assertSee('You asked for this')
            ->assertSee('Exports');
    }

    public function test_feeds(): void
    {
        Entry::factory()->labels('new')->create(['title' => 'Tom & Jerry <script>', 'body' => '**Bold** move', 'cover_url' => 'https://cdn.test/a.png']);

        foreach (['/feedback/changelog/feed.atom' => 'application/atom+xml', '/feedback/changelog/feed.rss' => 'application/rss+xml'] as $url => $type) {
            $response = $this->get($url)->assertOk();
            $this->assertStringStartsWith($type, (string) $response->headers->get('Content-Type'));

            $xml = simplexml_load_string((string) $response->getContent());
            $this->assertNotFalse($xml, "{$url} isn't valid XML.");
            $this->assertStringContainsString('Tom &amp; Jerry &lt;script&gt;', (string) $response->getContent());
            $this->assertStringNotContainsString('<script>', (string) $response->getContent());
            $this->assertStringContainsString('&lt;strong&gt;Bold&lt;/strong&gt;', (string) $response->getContent());
        }
    }

    public function test_seeing_the_changelog_resets_the_unread_count(): void
    {
        $ada = $this->user();
        Entry::factory()->create(['published_at' => Carbon::now()->subDays(40)]);
        Entry::factory()->create(['published_at' => Carbon::now()->subDays(3)]);
        Entry::factory()->create(['published_at' => Carbon::now()->subDay()]);

        // Never looked: the last 30 days count.
        $this->assertSame(2, Feedback::unreadCount($ada));

        Feedback::markSeen($ada, Carbon::now()->subDays(2));
        $this->assertSame(1, Feedback::unreadCount($ada));

        $this->actingAs($ada)->get('/feedback/changelog')->assertOk();
        $this->assertSame(0, Feedback::unreadCount($ada));
        $this->assertSame(1, Seen::query()->count());

        Entry::factory()->create(['published_at' => Carbon::now()->addSecond()]);
        $this->travel(2)->seconds();
        $this->assertSame(1, Feedback::unreadCount($ada));

        $this->actingAs($ada)->postJson('/feedback/changelog/seen')->assertOk()->assertJson(['unread' => 0]);
        $this->assertSame(0, Feedback::unreadCount($ada));
    }

    public function test_guests_are_remembered_with_a_cookie(): void
    {
        Entry::factory()->create();

        $this->get('/feedback/changelog')->assertOk()->assertCookie(SeenCookie::NAME);

        $this->post('/feedback/changelog/seen')->assertRedirect()->assertCookie(SeenCookie::NAME);
    }

    public function test_the_widget_counts_unread_entries(): void
    {
        $ada = $this->user();
        Entry::factory()->create(['title' => 'Old news', 'published_at' => Carbon::now()->subDays(10)]);
        Entry::factory()->labels('improved')->create(['title' => 'Fresh', 'published_at' => Carbon::now()->subDay()]);
        Feedback::markSeen($ada, Carbon::now()->subDays(5));
        $this->actingAs($ada);

        $html = Blade::render('<x-feedback::whats-new />');

        $this->assertStringContainsString('data-unread="1"', $html);
        $this->assertStringContainsString('What’s new, 1 new update"', $html);
        $this->assertStringContainsString('/feedback/changelog" data-fb-wn-toggle', $html, 'Without JavaScript it is a link.');
        $this->assertStringContainsString('Fresh', $html);
        $this->assertStringContainsString('Old news', $html);
        $this->assertSame(1, substr_count($html, 'fb-wn-item is-new'));
        $this->assertStringContainsString('Improved', $html);
        $this->assertMatchesRegularExpression('/data-fb-wn-panel[^>]*hidden/', $html);
    }

    public function test_the_widget_options_and_guests(): void
    {
        Entry::factory()->count(3)->create();

        $html = Blade::render('<x-feedback::whats-new :limit="2" label="Updates" :open="true" />');
        $this->assertSame(2, substr_count($html, 'class="fb-wn-item'));
        $this->assertStringContainsString('Updates, 3 new updates', $html);
        $this->assertDoesNotMatchRegularExpression('/data-fb-wn-panel[^>]*hidden/', $html);

        // Styles and script come once per page.
        $twice = Blade::render('<x-feedback::whats-new /><x-feedback::whats-new />');
        $this->assertSame(1, substr_count($twice, '<style>'));
        $this->assertSame(1, substr_count($twice, '<script>'));
    }

    public function test_the_widget_is_quiet_with_no_entries_and_absent_without_a_changelog(): void
    {
        $html = Blade::render('<x-feedback::whats-new />');
        $this->assertStringContainsString('No updates yet', $html);
        $this->assertStringContainsString('data-unread="0"', $html);

        config(['feedback.sections.changelog' => false]);
        $this->assertSame('', trim(Blade::render('<x-feedback::whats-new />')));
    }
}
