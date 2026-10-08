<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Ruvelo\Feedback\Feedback;

/**
 * A free label managers put on requests: "Enterprise", "Quick win"…
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tag extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Feedback::table('tags');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<FeatureRequest, $this>
     */
    public function requests(): BelongsToMany
    {
        return $this->belongsToMany(FeatureRequest::class, Feedback::table('request_tag'), 'tag_id', 'request_id');
    }
}
