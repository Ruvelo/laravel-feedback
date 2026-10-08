<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers\Manage;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Controllers\Controller;
use Ruvelo\Feedback\Models\Category;
use Ruvelo\Feedback\Models\Tag;

/**
 * Categories and tags.
 */
class TaxonomyController extends Controller
{
    public function index(): View
    {
        return view('feedback::manage.taxonomy', [
            'categories' => Category::query()->withCount(['requests' => fn ($query) => $query->whereNull('merged_into_id')])->orderBy('position')->orderBy('name')->get(),
            'tags' => Tag::query()->withCount(['requests' => fn ($query) => $query->whereNull('merged_into_id')])->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $category = Feedback::category((string) $data['name']);

        return $this->back('Category “'.$category->name.'” is ready.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'position' => ['nullable', 'integer', 'min:0']]);
        Feedback::renameCategory($category, (string) $data['name'], isset($data['position']) ? (int) $data['position'] : null);

        return $this->back('Category saved.');
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        Feedback::deleteCategory($category);

        return $this->back('Category deleted. Its requests have no category now.');
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $tag = Feedback::tag((string) $data['name']);

        return $this->back('Tag “'.$tag->name.'” is ready.');
    }

    public function updateTag(Request $request, Tag $tag): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        Feedback::renameTag($tag, (string) $data['name']);

        return $this->back('Tag saved.');
    }

    public function destroyTag(Tag $tag): RedirectResponse
    {
        Feedback::deleteTag($tag);

        return $this->back('Tag deleted.');
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('feedback.manage.taxonomy')->with('feedback.status', $message);
    }
}
