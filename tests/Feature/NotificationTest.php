<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Notifications\RequestStatusChanged;
use Ruvelo\Feedback\Tests\Fixtures\User;
use Ruvelo\Feedback\Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_the_author_and_voters_hear_about_a_status_change_but_not_whoever_made_it(): void
    {
        Notification::fake();
        $ada = $this->user();
        $tom = $this->user('Tom Reyes');
        $maya = $this->user('Maya Okafor');
        $request = Feedback::post($ada, 'Dark mode');
        Feedback::vote($request, $tom);
        Feedback::vote($request, $maya);

        Feedback::changeStatus($request, 'planned', 'Starting in March.', $maya);

        Notification::assertSentTo([$ada, $tom], RequestStatusChanged::class, fn (RequestStatusChanged $n) => $n->to === 'planned' && $n->from === 'under_review');
        Notification::assertNotSentTo($maya, RequestStatusChanged::class);
        Notification::assertCount(2);
    }

    public function test_the_notification_has_what_the_inbox_shows(): void
    {
        $ada = $this->user();
        $tom = $this->user('Tom Reyes');
        $request = Feedback::post($ada, 'Dark mode');
        Feedback::vote($request, $tom);

        Feedback::changeStatus($request, 'planned', 'Starting in **March**.');

        $stored = $tom->notifications()->sole();
        $this->assertSame(RequestStatusChanged::class, $stored->type);
        $this->assertSame('“Dark mode” is now planned', $stored->data['title']);
        $this->assertSame('Starting in March. You voted for this.', $stored->data['body']);
        $this->assertSame($request->url(), $stored->data['url']);
        $this->assertSame('planned', $stored->data['status']);
        $this->assertSame('Starting in March. You asked for this.', $ada->notifications()->sole()->data['body']);
    }

    public function test_shipping_links_to_the_changelog_entry(): void
    {
        $ada = $this->user();
        $request = Feedback::post($ada, 'Usage-based billing');

        $entry = Feedback::publish('Usage-based billing is here', 'Bill for what they use.', ['new'], requests: [$request]);

        $data = $ada->notifications()->sole()->data;
        $this->assertSame('Shipped: Usage-based billing', $data['title']);
        $this->assertSame('It’s live. You asked for this.', $data['body']);
        $this->assertSame($entry->url(), $data['url']);

        $mail = (new RequestStatusChanged($request->refresh(), 'under_review', 'shipped'))->toMail($ada);
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Shipped: Usage-based billing', $mail->subject);
        $this->assertSame('See what’s new', $mail->actionText);
        $this->assertSame($entry->url(), $mail->actionUrl);
    }

    public function test_closing_says_so(): void
    {
        $ada = $this->user();
        $request = Feedback::post($ada, 'Native app');

        Feedback::changeStatus($request, 'closed');

        $data = $ada->notifications()->sole()->data;
        $this->assertSame('“Native app” was closed', $data['title']);
        $this->assertStringContainsString('not something we’ll build', $data['body']);
    }

    public function test_channels_come_from_the_config_and_mail_needs_an_address(): void
    {
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');
        $notification = new RequestStatusChanged($request, 'under_review', 'planned');

        $this->assertSame(['database', 'mail'], $notification->via($ada));

        $nobody = User::forceCreate(['name' => 'No Mail', 'email' => '', 'password' => 'x']);
        $this->assertSame(['database'], $notification->via($nobody));

        config(['feedback.notifications.channels' => ['mail']]);
        $this->assertSame(['mail'], $notification->via($ada));
    }

    public function test_the_database_channel_is_skipped_without_a_notifications_table(): void
    {
        Schema::drop('notifications');
        RequestStatusChanged::flush();
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');

        $this->assertSame(['mail'], (new RequestStatusChanged($request, null, 'planned'))->via($ada));

        // And a status change still works.
        Feedback::changeStatus($request, 'planned');
        $this->assertSame('planned', $request->refresh()->status);
    }

    public function test_notifications_can_be_turned_off_limited_or_skipped(): void
    {
        Notification::fake();
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');

        Feedback::changeStatus($request, 'planned', notify: false);
        config(['feedback.notifications.statuses' => ['shipped']]);
        Feedback::changeStatus($request, 'in_progress');
        config(['feedback.notifications.statuses' => null, 'feedback.notifications.enabled' => false]);
        Feedback::changeStatus($request, 'shipped');

        Notification::assertNothingSent();
    }

    public function test_merged_voters_hear_when_the_request_they_moved_to_ships(): void
    {
        Notification::fake();
        $ada = $this->user();
        $tom = $this->user('Tom Reyes');
        $target = Feedback::post($ada, 'Dark mode');
        $duplicate = Feedback::post($tom, 'Dark theme');

        Feedback::merge($duplicate, $target);
        Feedback::changeStatus($target, 'shipped');

        Notification::assertSentTo($tom, RequestStatusChanged::class);
    }
}
