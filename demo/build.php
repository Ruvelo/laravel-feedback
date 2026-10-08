<?php

declare(strict_types=1);

/*
 * Builds the read-only demo published at https://ruvelo.github.io/laravel-feedback/.
 *
 * Seeds Halyard's feedback portal (a made-up B2B billing platform) in an
 * in-memory database, renders the board, every request, the roadmap, the
 * changelog, its feeds and the admin area through the package's real
 * routes and views, and writes the HTML out as static files. Visitors are
 * signed in as Kenji Mori, a customer; the admin pages are rendered as
 * Maya Okafor, who runs product. In the browser, demo/demo.js does what the
 * server would: votes, filters, sorting, search and "Similar requests".
 *
 *   php demo/build.php <site-dir> [<shots-dir>]
 *
 * <shots-dir>, when given, receives the pages the screenshots are taken of:
 * without the demo banner.
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Foundation\Application;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\FeedbackServiceProvider;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Models\Vote;
use Ruvelo\Feedback\Support\Palette;

require __DIR__.'/../vendor/autoload.php';

const HOST = 'https://ruvelo.github.io';
const PATH = 'laravel-feedback';
const REPO = 'https://github.com/Ruvelo/laravel-feedback';

$site = rtrim($argv[1] ?? __DIR__.'/../build/site', '/');
$shots = isset($argv[2]) ? rtrim($argv[2], '/') : null;

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_URL' => HOST,
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'SESSION_DRIVER' => 'array',
    'FEEDBACK_PATH' => PATH,
    'FEEDBACK_NAME' => 'Halyard',
    'FEEDBACK_COMMENTS' => 'builtin',
    'FEEDBACK_NOTIFICATIONS' => 'false',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

class DemoUser extends User
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

$app = Application::create(basePath: null, options: ['extra' => ['providers' => [FeedbackServiceProvider::class]]]);
$app['config']->set('auth.providers.users.model', DemoUser::class);
$app['config']->set('feedback.app', ['label' => 'Back to Halyard', 'url' => HOST.'/'.PATH.'/in-your-app/']);
$app['config']->set('feedback.board.per_page', 100);

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
$app->make(ConsoleKernel::class)->call('migrate', ['--force' => true]);

// --- People -----------------------------------------------------------------

$content = require __DIR__.'/content.php';
$now = Carbon::now()->startOfHour()->addMinutes(17);

$people = [];
$person = function (string $name) use (&$people): DemoUser {
    if (! isset($people[$name])) {
        $email = strtolower((string) preg_replace('/[^a-z]+/i', '.', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $name))).'@customers.test';
        $people[$name] = DemoUser::forceCreate(['name' => $name, 'email' => $email, 'password' => bin2hex(random_bytes(8))]);
    }

    return $people[$name];
};

foreach (['Maya Okafor', 'Tom Reyes', 'Inès Laurent', 'Kenji Mori', ...$content['customers']] as $name) {
    $person($name);
}

// Enough customers to vote.
$first = ['Alex', 'Sam', 'Noah', 'Emma', 'Liam', 'Ava', 'Lena', 'Omar', 'Ines', 'Arjun', 'Maria', 'Theo', 'Elif', 'Kai', 'Rosa', 'Jin', 'Hugo', 'Ana', 'Ben', 'Lina'];
$last = ['Martins', 'Novak', 'Silva', 'Becker', 'Okoye', 'Larsen', 'Moreau', 'Kaur', 'Ivanov', 'Costa', 'Fischer', 'Park'];
foreach ($first as $f) {
    foreach ($last as $l) {
        $person($f.' '.$l);
    }
}
$crowd = array_values($people);

// Maya runs product and Inès runs support: they manage the board.
Gate::define('feedback-manage', fn (DemoUser $user) => in_array($user->name, ['Maya Okafor', 'Inès Laurent'], true));

$maya = $person('Maya Okafor');
$kenji = $person('Kenji Mori');

// --- Seed -------------------------------------------------------------------

$at = fn (float $days) => $now->copy()->subMinutes((int) round($days * 24 * 60));

Carbon::setTestNow($at(200));
foreach ($content['categories'] as $name) {
    Feedback::category($name);
}

// When each request stops taking votes: shipped by an entry, or closed.
$closesAt = [];
foreach ($content['entries'] as [, , $days, , , $ships]) {
    foreach ($ships as $ship) {
        $closesAt[$ship] = $days + 1;
    }
}

/** @var array<string, FeatureRequest> $requests */
$requests = [];
$history = [];

foreach ($content['requests'] as [$key, $title, $category, $days, $votes, $author, $body, $changes, $tags]) {
    Carbon::setTestNow($at($days));
    $request = Feedback::post($person($author), $title, $body, $category, $tags);
    $requests[$key] = $request;
    $history[$key] = $changes;

    // Votes trickle in after it's posted, most of them early.
    $voters = array_values(array_filter($crowd, fn (DemoUser $user) => ! in_array($user->name, [$author, 'Maya Okafor', 'Kenji Mori'], true)));
    mt_srand(crc32($key));
    shuffle($voters);
    $closes = $closesAt[$key] ?? ($changes !== [] && end($changes)[0] === 'closed' ? end($changes)[1] + 1 : 0);
    for ($i = 0; $i < $votes - 1; $i++) {
        $span = max(0.5, $days - $closes);
        Carbon::setTestNow($at($days - $span * (mt_rand(0, 1000) / 1000) ** 2.2));
        Feedback::vote($request, $voters[$i]);
    }
}

// Kenji voted for these; dark mode is the one he cares about most.
foreach (['dark', 'sso', 'webhooks', 'keys'] as $key) {
    Carbon::setTestNow($at(5));
    Feedback::vote($requests[$key], $kenji);
}

foreach ($content['comments'] as [$key, $author, $days, $body, $replies]) {
    Carbon::setTestNow($at($days));
    $comment = Feedback::comment($requests[$key], $person($author), $body);
    foreach ($replies as [$replyAuthor, $replyDays, $reply]) {
        Carbon::setTestNow($at($replyDays));
        Feedback::comment($requests[$key], $person($replyAuthor), $reply, $comment);
    }
}

foreach ($history as $key => $changes) {
    foreach ($changes as [$status, $days, $note, $by]) {
        Carbon::setTestNow($at($days));
        Feedback::changeStatus($requests[$key], $status, $note, $person($by === 'Maya' ? 'Maya Okafor' : 'Tom Reyes'));
    }
}

foreach ($content['duplicates'] as [$title, $into, $days, $votes, $author, $body]) {
    Carbon::setTestNow($at($days));
    $duplicate = Feedback::post($person($author), $title, $body);
    foreach (array_slice($crowd, 40, $votes) as $voter) {
        Feedback::vote($duplicate, $voter);
    }
    Carbon::setTestNow($at($days - 1));
    Feedback::merge($duplicate, $requests[$into], $maya);
}

// Changelog entries, oldest first, shipping the requests they announce.
foreach (array_reverse($content['entries']) as [$key, $title, $days, $labels, $cover, $ships, $body]) {
    Carbon::setTestNow($at($days));
    Feedback::publish(
        $title,
        $body,
        $labels,
        $cover === null ? null : HOST.'/'.PATH.'/assets/'.$cover,
        requests: array_map(fn (string $ship) => $requests[$ship], $ships),
        author: $maya,
    );
}

Carbon::setTestNow($at(0.2));
[$draftTitle, $draftBody, $draftLabels] = $content['draft'];
Feedback::saveEntry(new Entry, ['title' => $draftTitle, 'body' => $draftBody, 'labels' => $draftLabels, 'published_at' => null], [$requests['dark']], $maya);

app('config')->set('feedback.board.require_approval', true);
foreach ($content['pending'] as [$title, $category, $author, $body]) {
    Carbon::setTestNow($at(0.3));
    Feedback::post($person($author), $title, $body, $category);
}
app('config')->set('feedback.board.require_approval', false);

Feedback::pin($requests['xero']);
Feedback::update($requests['usage'], ['tags' => ['Enterprise', 'Most requested']]);

// Kenji last looked at the changelog ten days ago: two updates are new to him.
Feedback::markSeen($kenji, $at(10));
Carbon::setTestNow($now);

// --- Render -----------------------------------------------------------------

$http = $app->make(HttpKernel::class);

$fetch = function (string $path, ?User $as = null) use ($app, $http, $kenji): string {
    $app['auth']->forgetGuards();
    $app['auth']->guard()->setUser($as ?? $kenji);

    $url = HOST.'/'.PATH.($path === '' ? '' : '/'.$path);
    $request = Request::create($url);
    $response = $http->handle($request);
    $http->terminate($request, $response);

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException("GET {$url} answered {$response->getStatusCode()}");
    }

    // Root-relative links, so the same files work on Pages and on localhost.
    return str_replace(HOST.'/', '/', (string) $response->getContent());
};

$banner = '<div style="background:var(--feedback-accent, #3d4eff);color:var(--feedback-on-accent, #fff);font:500 .875rem/1.4 var(--feedback-sans, ui-sans-serif, system-ui, sans-serif);padding:.55rem 16px;text-align:center">'
    .'You’re looking at a read-only demo of <a href="'.REPO.'" style="color:inherit;font-weight:700">ruvelo/laravel-feedback</a>. '
    .'Votes, filters and search work in your browser; nothing is saved. '
    .'<a href="/'.PATH.'/in-your-app/" style="color:inherit">See “What’s new” in an app</a> · '
    .'<a href="/'.PATH.'/manage/" style="color:inherit">The admin area</a> · '
    .'<a href="'.REPO.'#readme" style="color:inherit">Read the docs</a></div>';

$demoScript = '<script src="/'.PATH.'/assets/demo.js"></script>';

$write = function (string $root, string $path, string $html, bool $withBanner = true) use ($banner, $demoScript): void {
    if (str_contains($html, '</head>')) {
        $html = (string) preg_replace('/<\/head>/', $demoScript.'</head>', $html, 1);
    }
    if ($withBanner) {
        $html = (string) preg_replace('/<body[^>]*>/', '$0'.$banner, $html, 1);
    }
    $file = $root.'/'.($path === '' ? '' : rawurldecode($path).'/').'index.html';
    is_dir(dirname($file)) || mkdir(dirname($file), 0777, true);
    file_put_contents($file, $html);
};

// The board holds every request (all statuses count as open while it's
// rendered); demo.js applies the real filters, including the default one.
$open = config('feedback.open_statuses');
config(['feedback.open_statuses' => array_keys(Palette::statuses())]);
$board = $fetch('');
config(['feedback.open_statuses' => $open]);

$pages = [
    '' => $board,
    'new' => $fetch('new'),
    'roadmap' => $fetch('roadmap'),
    'changelog' => $fetch('changelog'),
];

$listed = FeatureRequest::query()->listed()->orderBy('id')->get();
foreach ($listed as $request) {
    $pages['requests/'.$request->getRouteKey()] = $fetch('requests/'.$request->getRouteKey());
}
foreach (Entry::query()->published()->get() as $entry) {
    $pages['changelog/'.$entry->slug] = $fetch('changelog/'.$entry->slug);
}

// The admin area, as Maya.
$admin = [
    'manage' => $fetch('manage', $maya),
    'manage/changelog' => $fetch('manage/changelog', $maya),
    'manage/changelog/new' => $fetch('manage/changelog/new', $maya),
    'manage/categories' => $fetch('manage/categories', $maya),
    'manage/stats' => $fetch('manage/stats', $maya),
];
foreach (Entry::query()->get() as $entry) {
    $admin['manage/changelog/'.$entry->slug.'/edit'] = $fetch('manage/changelog/'.$entry->slug.'/edit', $maya);
}
foreach ($listed as $request) {
    $admin['manage/requests/'.$request->getRouteKey().'/edit'] = $fetch('manage/requests/'.$request->getRouteKey().'/edit', $maya);
}

foreach ($pages + $admin as $path => $html) {
    $write($site, $path, $html);
}

// Merged requests redirect to the one they were merged into.
foreach (FeatureRequest::query()->whereNotNull('merged_into_id')->with('mergedInto')->get() as $duplicate) {
    $target = '/'.PATH.'/requests/'.$duplicate->mergedInto?->getRouteKey().'/';
    $write($site, 'requests/'.$duplicate->getRouteKey(), '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0; url='.$target.'"><title>Merged</title><a href="'.$target.'">This request was merged</a>', false);
}

file_put_contents($site.'/changelog/feed.atom', $fetch('changelog/feed.atom'));
file_put_contents($site.'/changelog/feed.rss', $fetch('changelog/feed.rss'));

// A page from the (pretend) Halyard app, with the "What's new" widget.
$app['auth']->forgetGuards();
$app['auth']->guard()->setUser($kenji);
$inApp = function (bool $open) use ($app): string {
    return str_replace(HOST.'/', '/', $app['view']->file(__DIR__.'/app.blade.php', ['open' => $open])->render());
};
$write($site, 'in-your-app', $inApp(false));

// What demo.js needs: every listed request, in a form it can filter and sort.
$trending = Carbon::now()->subDays((int) config('feedback.board.trending_days', 14));
$index = $listed->load(['category', 'tags'])->map(fn (FeatureRequest $request) => [
    'id' => $request->id,
    'title' => $request->title,
    'url' => '/'.PATH.'/requests/'.$request->getRouteKey().'/',
    'votes' => $request->votes_count,
    'trending' => Vote::query()->where('request_id', $request->id)->where('created_at', '>=', $trending)->count(),
    'created' => $request->created_at->getTimestamp(),
    'pinned' => $request->isPinned(),
    'status' => $request->status,
    'status_label' => $request->statusLabel(),
    'tone' => Palette::statusChip($request->status)['class'],
    'category' => $request->category?->slug,
    'tags' => $request->tags->pluck('slug')->all(),
    'text' => mb_strtolower($request->title.' '.$request->body),
    'voted' => $request->hasVoter($kenji),
])->values();

is_dir($site.'/assets') || mkdir($site.'/assets', 0777, true);
file_put_contents($site.'/assets/demo.js', 'window.FEEDBACK_BASE='.json_encode('/'.PATH.'/').';window.FEEDBACK_OPEN='.json_encode(Palette::openStatuses()).';window.FEEDBACK_INDEX='
    .json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).";\n"
    .file_get_contents(__DIR__.'/demo.js'));
foreach (glob(__DIR__.'/assets/*') ?: [] as $asset) {
    copy($asset, $site.'/assets/'.basename($asset));
}

if ($shots !== null) {
    $typing = '<script>addEventListener("load",()=>{const i=document.getElementById("fb-title");i.focus();i.value="Sync invoices with QuickBooks";i.dispatchEvent(new Event("input"))})</script>';

    foreach ([
        'board' => $pages[''],
        'request' => $pages['requests/'.$requests['usage']->getRouteKey()],
        'dark' => $pages['requests/'.$requests['dark']->getRouteKey()],
        'roadmap' => $pages['roadmap'],
        'changelog' => $pages['changelog'],
        'new' => str_replace('</body>', $typing.'</body>', $pages['new']),
        'manage' => $admin['manage'],
        'manage-request' => $fetch('requests/'.$requests['dark']->getRouteKey(), $maya),
        'editor' => $admin['manage/changelog/'.Entry::query()->where('title', 'Usage-based billing is here')->value('slug').'/edit'],
        'stats' => $admin['manage/stats'],
    ] as $name => $html) {
        $write($shots, $name, $html, false);
    }
    $write($shots, 'widget', $inApp(true), false);
}

fwrite(STDOUT, sprintf("Built %d demo pages (%d requests, %d votes, %d changelog entries) into %s\n",
    count($pages + $admin) + 1, $listed->count(), Vote::query()->count(), Entry::query()->published()->count(), $site));
