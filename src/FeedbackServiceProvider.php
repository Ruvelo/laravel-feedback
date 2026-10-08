<?php

declare(strict_types=1);

namespace Ruvelo\Feedback;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Ruvelo\Comments\Events\CommentApproved;
use Ruvelo\Comments\Events\CommentDeleted;
use Ruvelo\Comments\Events\CommentPosted;
use Ruvelo\Feedback\Integrations\CommentableRequest;
use Ruvelo\Feedback\Markdown\Renderer;
use Ruvelo\Feedback\Models\FeatureRequest;

class FeedbackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/feedback.php', 'feedback');

        $this->app->singleton(Renderer::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'feedback');

        // <x-feedback::whats-new />
        Blade::componentNamespace('Ruvelo\\Feedback\\View\\Components', 'feedback');

        View::composer(['feedback::*', 'feedback::*.*'], function ($view): void {
            $view->with('canManage', Feedback::canManage());
        });

        $this->rateLimits();

        if (config('feedback.routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if (config('feedback.api.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        if (config('feedback.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // ruvelo/laravel-comments, when it's installed and chosen: register
        // requests as a commentable type and keep comment counts in step.
        $this->app->booted(function (): void {
            if (Feedback::commentsDriver() === 'ruvelo') {
                $this->integrateComments();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/feedback.php' => config_path('feedback.php'),
            ], 'feedback-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/feedback'),
            ], 'feedback-views');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'feedback-migrations');
        }
    }

    private function rateLimits(): void
    {
        foreach (['requests', 'comments', 'votes'] as $kind) {
            RateLimiter::for('feedback-'.$kind, function (Request $request) use ($kind): Limit {
                $max = config('feedback.rate_limits.'.$kind);

                return $max === null
                    ? Limit::none()
                    : Limit::perMinute(max(1, is_numeric($max) ? (int) $max : 1))
                        ->by('feedback-'.$kind.'|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
            });
        }
    }

    private function integrateComments(): void
    {
        config(['comments.commentables.'.CommentableRequest::ALIAS => CommentableRequest::class]);

        $recount = function (CommentPosted|CommentApproved|CommentDeleted $event): void {
            $comment = $event->comment;

            if ($comment->commentable_type !== (new CommentableRequest)->getMorphClass()) {
                return;
            }

            $request = FeatureRequest::query()->find($comment->commentable_id);
            if ($request !== null) {
                Feedback::recount($request);
            }
        };

        Event::listen(CommentPosted::class, $recount);
        Event::listen(CommentApproved::class, $recount);
        Event::listen(CommentDeleted::class, $recount);
    }
}
