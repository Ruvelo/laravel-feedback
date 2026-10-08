<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\Tag;

/**
 * A tag was deleted and taken off every request.
 */
class TagDeleted
{
    use Dispatchable;

    public function __construct(public readonly Tag $tag) {}
}
