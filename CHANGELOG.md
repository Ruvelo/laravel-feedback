# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-10-08

First release.

### Added

- A feedback board at `/feedback`: requests with a Markdown description, one vote per person (enforced by a unique index), categories, tags, filters by status, category and tag, search, and top, new and trending sorting. Pinned requests come first.
- "Similar requests" while typing a title, from the server or, without JavaScript, on the form page.
- Statuses (Under review, Planned, In progress, Shipped, Closed) with configurable labels and colours, custom statuses, and locked statuses that take no new votes.
- Status changes with public notes and a history on each request.
- Merging duplicates: votes and comments carry over, voters of both keep one vote, the duplicate's author becomes a voter, and its page redirects.
- Built-in threaded comments with Markdown and "Team" badges, or ruvelo/laravel-comments when installed (`comments.driver`).
- A public roadmap of planned, in-progress and shipped requests.
- A changelog with labels (New, Improved, Fixed), cover images, drafts, scheduling, linked requests, and RSS and Atom feeds.
- `<x-feedback::whats-new />`: an unread badge per user (last-seen time, or a cookie for guests) and a dropdown of recent entries; a plain link without JavaScript.
- `RequestStatusChanged` notifications for authors and voters through the `database` and `mail` channels, with `title`, `body` and `url` for ruvelo/laravel-inbox. The database channel is skipped while the app has no notifications table.
- An admin area behind the `feedback-manage` gate: optional approval queue, status changes, pinning, merging, editing, categories and tags, a changelog editor with live preview, and stats (votes per week, top requests).
- Gates with defaults: `feedback-manage`, `feedback-post`, `feedback-vote` and `feedback-comment`. Guests who try to write are sent to the `login` route, or get a 403 when there is none.
- Rate limits for requests, comments and votes, a honeypot field and length limits.
- Each section (board, roadmap, changelog) can be switched off; pages can render inside the host app's layout.
- PHP API: `Feedback::post()`, `vote()`, `unvote()`, `toggleVote()`, `comment()`, `changeStatus()`, `ship()`, `merge()`, `pin()`, `update()`, `approve()`, `delete()`, `publish()`, `saveEntry()`, `similar()`, `roadmap()`, `changelog()`, `unreadCount()`, `markSeen()`, `stats()` and more.
- Events for every change and typed exceptions extending `FeedbackException`.
- An optional JSON API (`FEEDBACK_API=true`): public reads, writes for signed-in users and managers.
- `FeatureRequest::factory()` and `Entry::factory()` for tests in host apps.
- Laravel Boost guidelines in `resources/boost/guidelines/core.blade.php`.
- Ruvelo house style UI: light and dark, scoped styles, themable through CSS variables.

[Unreleased]: https://github.com/Ruvelo/laravel-feedback/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Ruvelo/laravel-feedback/releases/tag/v1.0.0
