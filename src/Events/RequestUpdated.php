<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * A manager edited a request's title, description, category or tags. $changes holds the new values.
 */
class RequestUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(public readonly FeatureRequest $request, public readonly array $changes) {}
}
