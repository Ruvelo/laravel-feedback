<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Exceptions;

class CategoryNotFound extends FeedbackException
{
    public function __construct(public readonly string $category)
    {
        parent::__construct("There's no category called “{$category}”.");
    }

    public function field(): string
    {
        return 'category';
    }
}
