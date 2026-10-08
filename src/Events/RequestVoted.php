<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * Someone added ($added true) or took back (false) their vote.
 */
class RequestVoted
{
    use Dispatchable;

    public function __construct(public readonly FeatureRequest $request, public readonly Model $voter, public readonly bool $added) {}
}
