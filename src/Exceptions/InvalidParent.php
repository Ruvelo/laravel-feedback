<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Exceptions;

/**
 * Replying to a comment that belongs to another request.
 */
class InvalidParent extends FeedbackException
{
    public function __construct()
    {
        parent::__construct('That comment is on another request.');
    }

    public function field(): string
    {
        return 'body';
    }
}
