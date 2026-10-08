<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Feedback\Models\Category;

/**
 * A category was created or renamed.
 */
class CategorySaved
{
    use Dispatchable;

    public function __construct(public readonly Category $category) {}
}
