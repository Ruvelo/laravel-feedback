<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Support\SeenCookie;

/**
 * `<x-feedback::whats-new />`: a button for your app's header with the
 * number of changelog entries the viewer hasn't seen, and a dropdown of
 * the latest ones. Two queries. Without JavaScript it's a link to the
 * changelog, which marks everything seen.
 */
class WhatsNew extends Component
{
    public readonly int $limit;

    public function __construct(
        ?int $limit = null,
        public readonly ?Model $user = null,
        public readonly string $label = 'What’s new',
        public readonly bool $open = false,
    ) {
        $this->limit = max(1, $limit ?? (int) config('feedback.widget.limit', 5));
    }

    public function shouldRender(): bool
    {
        return Feedback::enabled('changelog') && config('feedback.routes', true);
    }

    public function render(): View
    {
        $viewer = $this->user ?? (auth()->user() instanceof Model ? auth()->user() : null);
        $request = request();
        $since = $viewer !== null
            ? Feedback::lastSeen($viewer)
            : ($request instanceof Request ? SeenCookie::get($request) : null);
        $since ??= Carbon::now()->subDays(max(0, (int) config('feedback.widget.fresh_days', 30)));

        return $this->view('feedback::components.whats-new', [
            'id' => 'fb-wn-'.Str::lower(Str::random(6)),
            'unread' => Feedback::unreadCount($viewer, $since),
            'since' => $since,
            'entries' => Feedback::changelog()->limit($this->limit)->get(),
        ]);
    }
}
