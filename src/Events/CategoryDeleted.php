<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\Category;

/**
 * A category was deleted; its requests have none now.
 */
class CategoryDeleted
{
    use Dispatchable;

    public function __construct(public readonly Category $category) {}
}
