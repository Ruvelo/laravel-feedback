<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * A request was deleted, with its votes and comments.
 */
class RequestDeleted
{
    use Dispatchable;

    public function __construct(public readonly FeatureRequest $request) {}
}
