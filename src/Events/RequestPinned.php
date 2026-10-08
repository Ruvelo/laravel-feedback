<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * A request was pinned to the top of the board, or unpinned.
 */
class RequestPinned
{
    use Dispatchable;

    public function __construct(public readonly FeatureRequest $request, public readonly bool $pinned) {}
}
