<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Ruvelo\Feedback\Feedback;

/**
 * An area of the product a request belongs to: Billing, Integrations…
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, FeatureRequest> $requests
 */
class Category extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['position' => 0];

    public function getTable(): string
    {
        return Feedback::table('categories');
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<FeatureRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(FeatureRequest::class);
    }
}
