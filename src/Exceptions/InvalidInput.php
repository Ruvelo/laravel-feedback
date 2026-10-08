<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Exceptions;

/**
 * A title, description, comment or URL that can't be saved: empty, too
 * short or too long.
 */
class InvalidInput extends FeedbackException
{
    public function __construct(string $message, public readonly string $attribute = 'title')
    {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->attribute;
    }
}
