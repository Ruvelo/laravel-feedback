<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Exceptions;

/**
 * The status isn't one of the keys in feedback.statuses.
 */
class InvalidStatus extends FeedbackException
{
    public function __construct(public readonly string $status)
    {
        parent::__construct("“{$status}” isn't a status. Use one of: ".implode(', ', array_keys((array) config('feedback.statuses', []))).'.');
    }

    public function field(): string
    {
        return 'status';
    }
}
