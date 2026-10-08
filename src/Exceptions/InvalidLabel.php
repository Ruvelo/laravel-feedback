<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Exceptions;

/**
 * The changelog label isn't one of the keys in feedback.changelog.labels.
 */
class InvalidLabel extends FeedbackException
{
    public function __construct(public readonly string $label)
    {
        parent::__construct("“{$label}” isn't a changelog label. Use one of: ".implode(', ', array_keys((array) config('feedback.changelog.labels', []))).'.');
    }

    public function field(): string
    {
        return 'labels';
    }
}
