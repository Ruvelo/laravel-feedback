<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Ruvelo\Feedback\Feedback;

/**
 * One person's vote on one request. The table's unique index makes sure
 * there's never a second.
 *
 * @property int $id
 * @property int $request_id
 * @property string $voter_type
 * @property int|string $voter_id
 * @property Carbon|null $created_at
 * @property-read FeatureRequest $request
 * @property-read Model|null $voter
 */
class Vote extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Feedback::table('votes');
    }

    /**
     * @return BelongsTo<FeatureRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(FeatureRequest::class, 'request_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function voter(): MorphTo
    {
        return $this->morphTo();
    }
}
