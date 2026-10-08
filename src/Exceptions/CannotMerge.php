<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Exceptions;

/**
 * Merging into itself, or merging a request that's already merged.
 */
class CannotMerge extends FeedbackException
{
    public function field(): string
    {
        return 'into';
    }
}
