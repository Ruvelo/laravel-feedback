<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Support\Palette;
use Ruvelo\Feedback\Support\People;

/**
 * One step in a request's history, with the public note that came with it.
 *
 * @property int $id
 * @property int $request_id
 * @property string|null $from
 * @property string $to
 * @property string|null $note
 * @property string|null $changer_type
 * @property int|string|null $changer_id
 * @property Carbon|null $created_at
 * @property-read FeatureRequest $request
 * @property-read Model|null $changer
 */
class StatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Feedback::table('status_changes');
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
    public function changer(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(): string
    {
        return Palette::statusLabel($this->to);
    }

    public function noteHtml(): string
    {
        return $this->note === null ? '' : Feedback::render($this->note);
    }

    public function changerName(): string
    {
        return People::name($this->changer, 'The team');
    }
}
