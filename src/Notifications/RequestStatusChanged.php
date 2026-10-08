<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Support\Palette;
use Ruvelo\Feedback\Support\Text;

/**
 * A request you posted or voted for changed status: "Shipped: Usage-based
 * billing".
 *
 * toArray() returns `title`, `body` and `url`, the shape
 * ruvelo/laravel-inbox shows without any setup.
 *
 * Queued: shipping a popular request can mean mail to hundreds of voters,
 * which must not happen inside the request that changed the status. It is
 * dispatched after the transaction commits, so nobody hears about a change
 * that was rolled back. Apps on the `sync` queue still send it right away.
 */
class RequestStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    private static ?bool $hasNotificationsTable = null;

    public function __construct(
        public readonly FeatureRequest $request,
        public readonly ?string $from,
        public readonly string $to,
        public readonly ?string $note = null,
    ) {
        $this->afterCommit();
    }

    /**
     * The configured channels. `database` is left out while the app has no
     * notifications table, so a status change never fails over it.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = array_values(array_filter((array) config('feedback.notifications.channels', ['database', 'mail']), 'is_string'));

        if (in_array('database', $channels, true) && ! self::hasNotificationsTable()) {
            $channels = array_values(array_diff($channels, ['database']));
        }

        if (in_array('mail', $channels, true) && $notifiable instanceof Model && method_exists($notifiable, 'routeNotificationFor') && ! $notifiable->routeNotificationFor('mail', $this)) {
            $channels = array_values(array_diff($channels, ['mail']));
        }

        return $channels;
    }

    /**
     * @return array{title: string, body: string, url: string, request_id: int, status: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body($notifiable),
            'url' => $this->url(),
            'request_id' => $this->request->id,
            'status' => $this->to,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->line($this->body($notifiable));

        return $mail->action($this->to === 'shipped' && $this->entryUrl() !== null ? 'See what’s new' : 'View the request', $this->url());
    }

    public function title(): string
    {
        $title = '“'.$this->request->title.'”';

        return match ($this->to) {
            'shipped' => 'Shipped: '.$this->request->title,
            'closed' => $title.' was closed',
            default => $title.' is now '.mb_strtolower(Palette::statusLabel($this->to)),
        };
    }

    protected function body(object $notifiable): string
    {
        $why = $notifiable instanceof Model && $this->request->isAuthoredBy($notifiable)
            ? 'You asked for this.'
            : 'You voted for this.';

        if ($this->note !== null && trim($this->note) !== '') {
            return Text::excerpt($this->note, 300).' '.$why;
        }

        return match ($this->to) {
            'shipped' => 'It’s live. '.$why,
            'closed' => 'It’s not something we’ll build for now. '.$why,
            default => 'It moved to '.Palette::statusLabel($this->to).'. '.$why,
        };
    }

    protected function url(): string
    {
        return $this->entryUrl() ?? $this->request->url();
    }

    private function entryUrl(): ?string
    {
        $entry = $this->request->entry;

        return $this->to === 'shipped' && $entry !== null && $entry->isPublished() && config('feedback.sections.changelog', true)
            ? $entry->url()
            : null;
    }

    private static function hasNotificationsTable(): bool
    {
        return self::$hasNotificationsTable ??= Schema::hasTable('notifications');
    }

    /**
     * Forget whether the notifications table exists. For tests.
     */
    public static function flush(): void
    {
        self::$hasNotificationsTable = null;
    }
}
