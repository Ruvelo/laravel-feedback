<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * A manager approved a waiting request; it's on the board now.
 */
class RequestApproved
{
    use Dispatchable;

    public function __construct(public readonly FeatureRequest $request) {}
}
