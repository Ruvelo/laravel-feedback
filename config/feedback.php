<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Name
    |--------------------------------------------------------------------------
    |
    | Shown in the header, page titles and the changelog feeds. Usually your
    | product's name.
    |
    */

    'name' => env('FEEDBACK_NAME', env('APP_NAME', 'Feedback')),

    /*
    |--------------------------------------------------------------------------
    | Sections
    |--------------------------------------------------------------------------
    |
    | The three public pages share one layout, with a tab for each section
    | that's on. Turn any of them off: a changelog-only site, or a board
    | without a public roadmap. The admin area stays available either way.
    |
    */

    'sections' => [
        'board' => true,
        'roadmap' => true,
        'changelog' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | Everything lives under `path` (e.g. /feedback) and guests can read it:
    | `middleware` wraps every route, so add `auth` to make it private.
    | Posting, voting and commenting need a signed-in user. The admin area
    | is under `{path}/manage` and needs the `feedback-manage` gate, which
    | lets nobody in until you define it; `manage_middleware` is added on
    | top. Set `routes` to false to register your own.
    |
    */

    'routes' => true,

    'path' => env('FEEDBACK_PATH', 'feedback'),

    'domain' => null,

    'middleware' => ['web'],

    'manage_middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | null uses the package's own page. Set a view name (e.g. 'layouts.app')
    | to render inside your app's layout instead: the pages fill the
    | `section` you name and push their <head> tags to a `feedback-head`
    | stack if your layout has one. `app` adds a link back to your product
    | in the header.
    |
    */

    'layout' => null,

    'section' => 'content',

    'app' => [
        'label' => null,
        'url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Statuses
    |--------------------------------------------------------------------------
    |
    | Every request has one. Change the labels and colours freely, or add
    | your own; keep the keys of the five built-in ones, which the package
    | relies on (`under_review` is where new requests start, `shipped`
    | links to the changelog). A colour is one of accent, lilac, success,
    | warning, danger or neutral (each with a light and dark shade), or a
    | hex colour.
    |
    | `open` statuses are the board's default filter. Requests in `locked`
    | statuses take no new votes.
    |
    */

    'statuses' => [
        'under_review' => ['label' => 'Under review', 'color' => 'neutral'],
        'planned' => ['label' => 'Planned', 'color' => 'lilac'],
        'in_progress' => ['label' => 'In progress', 'color' => 'warning'],
        'shipped' => ['label' => 'Shipped', 'color' => 'success'],
        'closed' => ['label' => 'Closed', 'color' => 'danger'],
    ],

    'open_statuses' => ['under_review', 'planned', 'in_progress'],

    'locked_statuses' => ['shipped', 'closed'],

    /*
    |--------------------------------------------------------------------------
    | Board
    |--------------------------------------------------------------------------
    |
    | `sort` is the default order: top (most votes), new, or trending (most
    | votes in the last `trending_days`). With `require_approval`, new
    | requests wait in the admin queue until a manager approves them; their
    | author still sees them. Managers' own requests are approved at once.
    |
    */

    'board' => [
        'sort' => 'top',
        'trending_days' => 14,
        'per_page' => 20,
        'require_approval' => false,
        'similar' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Roadmap
    |--------------------------------------------------------------------------
    |
    | The columns, left to right, and how many requests each shows (most
    | votes first, or most recently shipped for `shipped`).
    |
    */

    'roadmap' => [
        'statuses' => ['planned', 'in_progress', 'shipped'],
        'limit' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Changelog
    |--------------------------------------------------------------------------
    |
    | `labels` are the tags an entry can carry; change the wording or add
    | your own (colours as for statuses). `feed` sets how many entries the
    | RSS and Atom feeds hold.
    |
    */

    'changelog' => [
        'labels' => [
            'new' => ['label' => 'New', 'color' => 'accent'],
            'improved' => ['label' => 'Improved', 'color' => 'lilac'],
            'fixed' => ['label' => 'Fixed', 'color' => 'success'],
        ],
        'per_page' => 10,
        'feed' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | "What's new" widget
    |--------------------------------------------------------------------------
    |
    | <x-feedback::whats-new /> shows the latest `limit` entries and counts
    | the ones published since the viewer last looked. Someone who has
    | never looked sees entries from the last `fresh_days` as new.
    |
    */

    'widget' => [
        'limit' => 5,
        'fresh_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Comments
    |--------------------------------------------------------------------------
    |
    | `builtin` is a simple thread with replies and Markdown. `ruvelo` uses
    | ruvelo/laravel-comments (reactions, mentions, moderation) when it's
    | installed; `auto` picks it when it's there, else the built-in one.
    | null turns comments off.
    |
    */

    'comments' => [
        'driver' => env('FEEDBACK_COMMENTS', 'auto'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | When a request changes status, its author and voters hear about it
    | (except whoever made the change). Standard Laravel notifications with
    | `title`, `body` and `url` in toArray(), so ruvelo/laravel-inbox shows
    | them. The `database` channel is skipped if your app has no
    | notifications table yet. `statuses` limits which changes notify; null
    | means all of them.
    |
    */

    'notifications' => [
        'enabled' => (bool) env('FEEDBACK_NOTIFICATIONS', true),
        'channels' => ['database', 'mail'],
        'statuses' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam and abuse
    |--------------------------------------------------------------------------
    |
    | Per user, per minute, through the routes (the PHP API is not limited).
    | `honeypot` names a hidden field people never fill in; posts that fill
    | it are quietly dropped. null turns it off.
    |
    */

    'rate_limits' => [
        'requests' => 5,
        'comments' => 10,
        'votes' => 60,
    ],

    'honeypot' => 'website',

    'max_length' => [
        'title' => 160,
        'body' => 10000,
        'comment' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | People
    |--------------------------------------------------------------------------
    |
    | The attribute shown as a person's name.
    |
    */

    'name_attribute' => 'name',

    /*
    |--------------------------------------------------------------------------
    | JSON API
    |--------------------------------------------------------------------------
    |
    | Off by default. Reads are public (`middleware`). Writes also need
    | `write_middleware`: a signed-in user for voting, posting and
    | commenting, plus the `feedback-manage` gate for the rest. The default
    | expects Laravel Sanctum; use ['web'] for both to rely on the session
    | from an Inertia or Livewire app on the same domain.
    |
    */

    'api' => [
        'enabled' => (bool) env('FEEDBACK_API', false),
        'prefix' => 'api/feedback',
        'middleware' => ['api'],
        'write_middleware' => ['auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown
    |--------------------------------------------------------------------------
    |
    | Extra CommonMark extensions (class names or instances) and options
    | merged over the defaults. Raw HTML is always escaped and unsafe links
    | are always dropped. In requests and comments, images become links so
    | readers never load a stranger's URL; changelog entries show them.
    |
    */

    'markdown' => [
        'extensions' => [],
        'options' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Tables are {prefix}requests, {prefix}votes, {prefix}comments,
    | {prefix}status_changes, {prefix}categories, {prefix}tags,
    | {prefix}request_tag, {prefix}entries and {prefix}seen. Set `run_migrations` to false if you publish the
    | migration and run it yourself.
    |
    */

    'table_prefix' => 'feedback_',

    'run_migrations' => true,

];
