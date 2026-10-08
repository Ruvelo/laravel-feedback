<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * A request was posted. Check $request->isApproved(): it may be waiting for a manager.
 */
class RequestPosted
{
    use Dispatchable;

    public function __construct(public readonly FeatureRequest $request) {}
}
