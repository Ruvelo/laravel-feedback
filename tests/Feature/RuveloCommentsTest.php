<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Ruvelo\Comments\Comments;
use Ruvelo\Comments\CommentsServiceProvider;
use Ruvelo\Comments\Models\Comment as RuveloComment;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\FeedbackServiceProvider;
use Ruvelo\Feedback\Integrations\CommentableRequest;
use Ruvelo\Feedback\Tests\TestCase;

/**
 * With ruvelo/laravel-comments installed, requests use its threads.
 */
class RuveloCommentsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CommentsServiceProvider::class, FeedbackServiceProvider::class];
    }

    public function test_auto_picks_laravel_comments_when_it_is_installed(): void
    {
        $this->assertSame('ruvelo', Feedback::commentsDriver());
        $this->assertSame(CommentableRequest::class, config('comments.commentables.feedback-request'));

        config(['feedback.comments.driver' => 'builtin']);
        $this->assertSame('builtin', Feedback::commentsDriver());
        config(['feedback.comments.driver' => null]);
        $this->assertNull(Feedback::commentsDriver());
    }

    public function test_the_request_page_shows_the_comments_thread_and_counts_stay_right(): void
    {
        $ada = $this->user();
        $request = Feedback::post($ada, 'Dark mode');

        Comments::post(CommentableRequest::wrap($request), $this->user('Tom Reyes'), 'Me **too**');

        $this->assertSame(1, $request->refresh()->comments_count);
        $this->get($request->url())->assertOk()->assertSee('comments-title', false)->assertSee('Me <strong>too</strong>', false);
        $this->actingAs($ada)->post($request->url().'/comments', ['body' => 'Built-in'])->assertNotFound();

        Comments::delete(RuveloComment::query()->sole());
        $this->assertSame(0, $request->refresh()->comments_count);
    }

    public function test_merging_and_deleting_take_the_thread_along(): void
    {
        $ada = $this->user();
        $target = Feedback::post($ada, 'Dark mode');
        $duplicate = Feedback::post($ada, 'Dark theme');
        $comment = Comments::post(CommentableRequest::wrap($duplicate), $ada, 'Please');

        Feedback::merge($duplicate, $target);

        $this->assertSame($target->id, (int) $comment->refresh()->commentable_id);
        $this->assertSame(1, $target->refresh()->comments_count);
        $this->assertSame($target->url(), CommentableRequest::wrap($target)->commentUrl());

        Feedback::delete($target);
        $this->assertSame(0, RuveloComment::query()->count());
    }
}
