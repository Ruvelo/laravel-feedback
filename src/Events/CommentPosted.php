<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\Comment;

/**
 * A comment or reply was posted on a request (built-in comments).
 */
class CommentPosted
{
    use Dispatchable;

    public function __construct(public readonly Comment $comment) {}
}
