<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Controllers\BoardController;
use Ruvelo\Feedback\Http\Controllers\ChangelogController;
use Ruvelo\Feedback\Http\Controllers\CommentController;
use Ruvelo\Feedback\Http\Controllers\Manage;
use Ruvelo\Feedback\Http\Controllers\RoadmapController;
use Ruvelo\Feedback\Http\Controllers\VoteController;
use Ruvelo\Feedback\Http\Middleware\AuthorizeManaging;
use Ruvelo\Feedback\Http\Middleware\EnsureSignedIn;

Route::group([
    'prefix' => config('feedback.path', 'feedback'),
    'domain' => config('feedback.domain'),
    'middleware' => config('feedback.middleware', ['web']),
    'as' => 'feedback.',
], function () {
    if (Feedback::enabled('board')) {
        Route::get('/', [BoardController::class, 'index'])->name('home');
    } else {
        // The first section that's on.
        Route::get('/', fn () => redirect()->route(Feedback::enabled('roadmap') ? 'feedback.roadmap' : (Feedback::enabled('changelog') ? 'feedback.changelog' : 'feedback.manage.requests')))->name('home');
    }

    // Requests are shown on the roadmap and in the changelog too, so their
    // pages exist whatever sections are on; posting needs the board.
    Route::get('/requests/{featureRequest}', [BoardController::class, 'show'])->name('requests.show');

    if (Feedback::enabled('board')) {
        Route::get('/similar', [BoardController::class, 'similar'])->name('similar');

        Route::middleware(EnsureSignedIn::class)->group(function () {
            Route::get('/new', [BoardController::class, 'create'])->name('requests.create');
            Route::post('/requests', [BoardController::class, 'store'])->middleware('throttle:feedback-requests')->name('requests.store');
            Route::post('/requests/{featureRequest}/vote', VoteController::class)->middleware('throttle:feedback-votes')->name('requests.vote');
            Route::post('/requests/{featureRequest}/comments', [CommentController::class, 'store'])->middleware('throttle:feedback-comments')->name('comments.store');
            Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        });
    }

    if (Feedback::enabled('roadmap')) {
        Route::get('/roadmap', RoadmapController::class)->name('roadmap');
    }

    if (Feedback::enabled('changelog')) {
        Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');
        Route::get('/changelog/feed.atom', [ChangelogController::class, 'atom'])->name('changelog.atom');
        Route::get('/changelog/feed.rss', [ChangelogController::class, 'rss'])->name('changelog.rss');
        Route::post('/changelog/seen', [ChangelogController::class, 'seen'])->name('changelog.seen');
        Route::get('/changelog/{entry}', [ChangelogController::class, 'show'])->name('changelog.show');
    }

    Route::prefix('manage')->as('manage.')
        ->middleware([...config('feedback.manage_middleware', []), AuthorizeManaging::class])
        ->group(function () {
            Route::get('/', [Manage\RequestController::class, 'index'])->name('requests');
            Route::get('/requests/{featureRequest}/edit', [Manage\RequestController::class, 'edit'])->name('requests.edit');
            Route::put('/requests/{featureRequest}', [Manage\RequestController::class, 'update'])->name('requests.update');
            Route::delete('/requests/{featureRequest}', [Manage\RequestController::class, 'destroy'])->name('requests.destroy');
            Route::post('/requests/{featureRequest}/approve', [Manage\RequestController::class, 'approve'])->name('requests.approve');
            Route::post('/requests/{featureRequest}/status', [Manage\RequestController::class, 'status'])->name('requests.status');
            Route::post('/requests/{featureRequest}/pin', [Manage\RequestController::class, 'pin'])->name('requests.pin');
            Route::post('/requests/{featureRequest}/merge', [Manage\RequestController::class, 'merge'])->name('requests.merge');

            Route::get('/changelog', [Manage\EntryController::class, 'index'])->name('entries');
            Route::get('/changelog/new', [Manage\EntryController::class, 'create'])->name('entries.create');
            Route::post('/changelog', [Manage\EntryController::class, 'store'])->name('entries.store');
            Route::post('/changelog/preview', [Manage\EntryController::class, 'preview'])->name('entries.preview');
            Route::get('/changelog/{entry}/edit', [Manage\EntryController::class, 'edit'])->name('entries.edit');
            Route::put('/changelog/{entry}', [Manage\EntryController::class, 'update'])->name('entries.update');
            Route::delete('/changelog/{entry}', [Manage\EntryController::class, 'destroy'])->name('entries.destroy');

            Route::get('/categories', [Manage\TaxonomyController::class, 'index'])->name('taxonomy');
            Route::post('/categories', [Manage\TaxonomyController::class, 'storeCategory'])->name('categories.store');
            Route::put('/categories/{category}', [Manage\TaxonomyController::class, 'updateCategory'])->name('categories.update');
            Route::delete('/categories/{category}', [Manage\TaxonomyController::class, 'destroyCategory'])->name('categories.destroy');
            Route::post('/tags', [Manage\TaxonomyController::class, 'storeTag'])->name('tags.store');
            Route::put('/tags/{tag}', [Manage\TaxonomyController::class, 'updateTag'])->name('tags.update');
            Route::delete('/tags/{tag}', [Manage\TaxonomyController::class, 'destroyTag'])->name('tags.destroy');

            Route::get('/stats', Manage\StatsController::class)->name('stats');
        });
});
