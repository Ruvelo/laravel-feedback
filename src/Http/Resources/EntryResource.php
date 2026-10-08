<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Support\Palette;

/**
 * @mixin Entry
 */
class EntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Entry $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'body' => $entry->body,
            'html' => $entry->html(),
            'excerpt' => $entry->excerpt(),
            'labels' => array_map(fn (string $label): array => ['key' => $label, 'label' => Palette::changelogLabel($label)], $entry->labelKeys()),
            'cover_url' => $entry->cover_url,
            'published' => $entry->isPublished(),
            'published_at' => $entry->published_at?->toIso8601String(),
            'url' => $entry->url(),
            'requests' => $this->whenLoaded('requests', fn () => $entry->requests->map(fn (FeatureRequest $feature): array => [
                'id' => $feature->id,
                'title' => $feature->title,
                'votes' => $feature->votes_count,
                'url' => $feature->url(),
            ])->values()->all()),
        ];
    }
}
