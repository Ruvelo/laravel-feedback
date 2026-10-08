<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\Tag;

/**
 * A tag was created or renamed.
 */
class TagSaved
{
    use Dispatchable;

    public function __construct(public readonly Tag $tag) {}
}
