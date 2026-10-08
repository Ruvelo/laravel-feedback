<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\Entry;

/**
 * A changelog entry went live (created or saved with a publish date that has passed). Fires once per entry.
 */
class EntryPublished
{
    use Dispatchable;

    public function __construct(public readonly Entry $entry) {}
}
