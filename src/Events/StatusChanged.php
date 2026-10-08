<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * A request moved to another status. $note is the public note, $by who did it (when known).
 */
class StatusChanged
{
    use Dispatchable;

    public function __construct(public readonly FeatureRequest $request, public readonly ?string $from, public readonly string $to, public readonly ?string $note = null, public readonly ?Model $by = null) {}
}
