<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Controllers\Controller;
use Ruvelo\Feedback\Http\Resources\RequestResource;
use Ruvelo\Feedback\Models\Category;
use Ruvelo\Feedback\Models\Tag;
use Ruvelo\Feedback\Support\Palette;

/**
 * What a custom frontend needs to draw filters and columns.
 */
class MetaController extends Controller
{
    public function statuses(): JsonResponse
    {
        $statuses = [];
        foreach (Palette::statuses() as $key => $label) {
            $statuses[] = [
                'key' => $key,
                'label' => $label,
                'open' => in_array($key, Palette::openStatuses(), true),
                'locked' => in_array($key, Palette::lockedStatuses(), true),
                'roadmap' => in_array($key, Palette::roadmapStatuses(), true),
            ];
        }

        return response()->json(['data' => $statuses]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::query()->orderBy('position')->orderBy('name')->get()
            ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug])->values()]);
    }

    public function tags(): JsonResponse
    {
        return response()->json(['data' => Tag::query()->orderBy('name')->get()
            ->map(fn (Tag $tag): array => ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug])->values()]);
    }

    public function roadmap(Request $request): JsonResponse
    {
        $columns = [];
        foreach (Feedback::roadmap($this->viewer($request)) as $status => $requests) {
            $columns[] = [
                'status' => $status,
                'label' => Palette::statusLabel($status),
                'requests' => RequestResource::collection($requests)->resolve($request),
            ];
        }

        return response()->json(['data' => $columns]);
    }
}
