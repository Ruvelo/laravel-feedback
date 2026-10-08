<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\Entry;

/**
 * A changelog entry was deleted. Requests it shipped keep their status.
 */
class EntryDeleted
{
    use Dispatchable;

    public function __construct(public readonly Entry $entry) {}
}
