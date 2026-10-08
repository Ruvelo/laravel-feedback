<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Ruvelo\Feedback\Feedback;

/**
 * When someone last looked at the changelog, for the "What's new" count.
 *
 * @property int $id
 * @property string $viewer_type
 * @property int|string $viewer_id
 * @property Carbon $seen_at
 */
class Seen extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Feedback::table('seen');
    }

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }
}
