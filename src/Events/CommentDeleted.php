<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\Comment;

/**
 * A comment was deleted, with its replies.
 */
class CommentDeleted
{
    use Dispatchable;

    public function __construct(public readonly Comment $comment) {}
}
