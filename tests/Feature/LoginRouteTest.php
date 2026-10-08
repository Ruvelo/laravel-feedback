<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Tests\TestCase;

/**
 * Apps with a login page send guests there, and back afterwards.
 */
class LoginRouteTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('/login', fn () => 'Sign in')->name('login');
    }

    public function test_guests_are_sent_to_sign_in_and_back(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');

        $this->from($request->url())->post($request->url().'/vote')->assertRedirect('/login')->assertSessionHas('url.intended', $request->url());
        $this->get('/feedback/new')->assertRedirect('/login')->assertSessionHas('url.intended', 'http://localhost/feedback/new');
        $this->get('/feedback/manage')->assertRedirect('/login');
        $this->postJson($request->url().'/vote')->assertUnauthorized();
    }

    public function test_guests_see_sign_in_links(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');

        $this->get('/feedback')->assertSee('aria-label="Sign in to vote', false)->assertSee('>Sign in</a>', false);
        $this->get($request->url())->assertSee('to vote and comment');
    }
}
