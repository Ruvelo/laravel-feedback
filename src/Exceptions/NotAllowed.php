<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Exceptions;

/**
 * A gate said no.
 */
class NotAllowed extends FeedbackException
{
    public function __construct(public readonly string $ability, string $message = 'You can’t do that here.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 403;
    }
}
