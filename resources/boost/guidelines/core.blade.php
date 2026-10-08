@verbatim
## Laravel Feedback (ruvelo/laravel-feedback)

A feedback board, public roadmap and changelog mounted at `/feedback` (config `feedback.path`): people post requests, vote (one vote each) and comment; managers change statuses with a public note, merge duplicates and publish changelog entries; voters and authors get a Laravel notification when a request changes status or ships. Plain Blade pages with scoped styles; no build step.

### Setup

`composer require ruvelo/laravel-feedback` then `php artisan migrate`. Guests can read everything; posting, voting and commenting need a signed-in user. Nobody can manage until you define the `feedback-manage` gate:

<code-snippet name="Letting managers in" lang="php">
use Illuminate\Support\Facades\Gate;

// AppServiceProvider::boot()
Gate::define('feedback-manage', fn ($user) => $user->is_admin);
</code-snippet>

Status notifications use the `database` and `mail` channels (`feedback.notifications.channels`). Run `php artisan make:notifications-table` for the database channel; without that table it is skipped, never an error. Notifiable users need the `Notifiable` trait.

### Permissions

| Gate | Arguments | Default |
|---|---|---|
| `feedback-manage` | `$user` | Nobody, until you define it |
| `feedback-post` | `$user` | Any signed-in user |
| `feedback-vote` | `$user, $request` | Any signed-in user |
| `feedback-comment` | `$user, $request` | Any signed-in user |

### "What's new" widget

Put it in the app's header. It counts changelog entries the user hasn't seen and opens a dropdown of the latest ones; without JavaScript it is a link to the changelog:

<code-snippet name="The widget" lang="blade">
<header>
    <a href="{{ route('feedback.home') }}">Feedback</a>
    <x-feedback::whats-new />
</header>
</code-snippet>

Options: `:limit="3"`, `label="Updates"`, `data-theme="light"`. Link to the pages with `route('feedback.home')`, `route('feedback.roadmap')`, `route('feedback.changelog')` and `route('feedback.requests.show', $request)`.

### PHP API

`Ruvelo\Feedback\Feedback` is the one write path (the routes use it too) and fires the events. It does not check gates or rate limits: call `Feedback::allows('feedback-vote', $user, $request)` or `Feedback::authorize(...)` (throws `NotAllowed`) when acting for a user.

<code-snippet name="PHP API" lang="php">
use Ruvelo\Feedback\Feedback;

$request = Feedback::post($user, 'Dark mode', 'Markdown description', category: 'Billing', tags: ['Enterprise']);
Feedback::vote($request, $otherUser);          // false if they already voted
Feedback::unvote($request, $otherUser);
Feedback::comment($request, $user, 'Me **too**');
Feedback::changeStatus($request, 'planned', 'Starting in March.', by: $admin);  // notifies author and voters
Feedback::merge($duplicate, into: $request, by: $admin);                       // votes carry over
$entry = Feedback::publish('Dark mode is here', $markdown, ['new'], requests: [$request]); // ships linked requests
Feedback::similar('dark theme');               // for "did you mean" lists
Feedback::unreadCount($user);                  // changelog entries since the user last looked
</code-snippet>

Statuses are the keys of `feedback.statuses` (`under_review`, `planned`, `in_progress`, `shipped`, `closed`, plus your own); changelog labels are the keys of `feedback.changelog.labels`. Never write to the models directly: counts would drift and no events would fire. Use `FeatureRequest::factory()` and `Entry::factory()` in tests.

### Events and exceptions

Events in `Ruvelo\Feedback\Events`: `RequestPosted`, `RequestVoted`, `StatusChanged`, `RequestsMerged`, `CommentPosted`, `EntryPublished` and more. Exceptions extend `Ruvelo\Feedback\Exceptions\FeedbackException` and render as the right HTTP response: `VotingClosed` (409), `InvalidStatus`, `InvalidInput`, `CannotMerge` (422), `NotAllowed` (403).

### JSON API

Off by default: set `FEEDBACK_API=true`. Routes live under `/api/feedback` (`requests`, `requests/{id}/vote`, `roadmap`, `changelog`, `changelog/whats-new`). Writes use `feedback.api.write_middleware` (Sanctum by default; `['web']` for session auth in Inertia apps).

### Don't

- Don't render request or comment text with `{!! !!}` yourself: use `$request->html()` or `Feedback::render()`, which escape HTML.
- Don't add `auth` middleware to the public pages to protect voting: guests are already sent to your `login` route.
- Don't hand-roll a duplicate table or vote counter: `votes_count` is kept by `Feedback`.
@endverbatim
