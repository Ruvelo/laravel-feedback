<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Tests\TestCase;

class LayoutTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('feedback.layout', 'host');
        $app['config']->set('feedback.section', 'main');
        $app['config']->set('feedback.path', 'ideas');
        $app['config']->set('feedback.app', ['label' => 'Back to the app', 'url' => 'https://app.test/dashboard']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        app('view')->addLocation(__DIR__.'/../Fixtures/views');
    }

    public function test_pages_render_inside_the_host_layout(): void
    {
        Feedback::post($this->user(), 'Dark mode');

        $this->get('/ideas')
            ->assertOk()
            ->assertSee('<title>Host app</title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('class="fb"', false)
            ->assertSee('Dark mode')
            ->assertSee('Back to the app');
    }
}
