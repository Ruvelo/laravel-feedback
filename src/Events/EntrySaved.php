<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\Entry;

/**
 * A changelog entry was created or changed: draft, scheduled or published.
 */
class EntrySaved
{
    use Dispatchable;

    public function __construct(public readonly Entry $entry) {}
}
