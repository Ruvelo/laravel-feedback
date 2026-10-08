<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Tests\TestCase;

class SectionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('feedback.sections.board', false);
        $app['config']->set('feedback.sections.roadmap', true);
        $app['config']->set('feedback.sections.changelog', false);
    }

    public function test_switched_off_sections_have_no_routes_or_tabs(): void
    {
        $request = Feedback::post($this->user(), 'Dark mode');
        Feedback::changeStatus($request, 'planned');

        $this->get('/feedback')->assertRedirect('/feedback/roadmap');
        $this->get('/feedback/roadmap')->assertOk()->assertSee('Dark mode')->assertDontSee('>Changelog</a>', false)->assertDontSee('Browse all requests');
        $this->get('/feedback/changelog')->assertNotFound();
        $this->get('/feedback/new')->assertNotFound();
        $this->actingAs($this->user('Tom Reyes'))->post($request->url().'/vote')->assertNotFound();

        // Requests still have a page, linked from the roadmap; voting needs the board.
        $this->get($request->url())->assertOk()->assertSee('Roadmap')->assertDontSee('<form class="fb-vote"', false);
    }
}
