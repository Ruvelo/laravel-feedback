<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * $duplicate was merged into $into: $movedVotes votes and its comments moved across.
 */
class RequestsMerged
{
    use Dispatchable;

    public function __construct(public readonly FeatureRequest $duplicate, public readonly FeatureRequest $into, public readonly int $movedVotes, public readonly ?Model $by = null) {}
}
