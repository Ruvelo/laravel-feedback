<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Feature;

use Ruvelo\Feedback\Tests\TestCase;

class JsonApiOffTest extends TestCase
{
    public function test_the_api_is_off_by_default(): void
    {
        $this->getJson('/api/feedback/requests')->assertNotFound();
        $this->assertFalse(app('router')->has('feedback.api.requests.index'));
    }
}
