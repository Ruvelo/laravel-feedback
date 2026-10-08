<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ruvelo\Feedback\Http\Controllers\Api\ChangelogController;
use Ruvelo\Feedback\Http\Controllers\Api\MetaController;
use Ruvelo\Feedback\Http\Controllers\Api\RequestController;
use Ruvelo\Feedback\Http\Middleware\AuthorizeManaging;
use Ruvelo\Feedback\Http\Middleware\EnsureSignedIn;

// The JSON API. Off by default: set FEEDBACK_API=true (or feedback.api.enabled).
Route::group([
    'prefix' => config('feedback.api.prefix', 'api/feedback'),
    'domain' => config('feedback.domain'),
    'middleware' => config('feedback.api.middleware', ['api']),
    'as' => 'feedback.api.',
], function () {
    $write = [...config('feedback.api.write_middleware', ['auth:sanctum']), EnsureSignedIn::class];
    $manage = [...config('feedback.api.write_middleware', ['auth:sanctum']), AuthorizeManaging::class];

    Route::get('statuses', [MetaController::class, 'statuses'])->name('statuses');
    Route::get('categories', [MetaController::class, 'categories'])->name('categories');
    Route::get('tags', [MetaController::class, 'tags'])->name('tags');
    Route::get('requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('requests/similar', [RequestController::class, 'similar'])->name('requests.similar');
    Route::get('requests/{featureRequest}', [RequestController::class, 'show'])->name('requests.show');
    Route::get('requests/{featureRequest}/comments', [RequestController::class, 'comments'])->name('requests.comments');
    Route::get('roadmap', [MetaController::class, 'roadmap'])->name('roadmap');
    Route::get('changelog', [ChangelogController::class, 'index'])->name('changelog.index');
    Route::get('changelog/whats-new', [ChangelogController::class, 'whatsNew'])->name('changelog.whats-new');
    Route::get('changelog/{slug}', [ChangelogController::class, 'show'])->name('changelog.show');

    Route::middleware($write)->group(function () {
        Route::post('requests', [RequestController::class, 'store'])->middleware('throttle:feedback-requests')->name('requests.store');
        Route::post('requests/{featureRequest}/vote', [RequestController::class, 'vote'])->middleware('throttle:feedback-votes')->name('requests.vote');
        Route::delete('requests/{featureRequest}/vote', [RequestController::class, 'unvote'])->middleware('throttle:feedback-votes')->name('requests.unvote');
        Route::post('requests/{featureRequest}/comments', [RequestController::class, 'comment'])->middleware('throttle:feedback-comments')->name('requests.comment');
        Route::post('changelog/seen', [ChangelogController::class, 'seen'])->name('changelog.seen');
    });

    Route::middleware($manage)->group(function () {
        Route::patch('requests/{featureRequest}', [RequestController::class, 'update'])->name('requests.update');
        Route::delete('requests/{featureRequest}', [RequestController::class, 'destroy'])->name('requests.destroy');
        Route::post('requests/{featureRequest}/approve', [RequestController::class, 'approve'])->name('requests.approve');
        Route::post('requests/{featureRequest}/merge', [RequestController::class, 'merge'])->name('requests.merge');
        Route::post('changelog', [ChangelogController::class, 'store'])->name('changelog.store');
        Route::patch('changelog/{entry:id}', [ChangelogController::class, 'update'])->name('changelog.update');
        Route::delete('changelog/{entry:id}', [ChangelogController::class, 'destroy'])->name('changelog.destroy');
    });
});
