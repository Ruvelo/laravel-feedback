<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers\Manage;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Controllers\Controller;
use Ruvelo\Feedback\Http\Resources\RequestResource;
use Ruvelo\Feedback\Models\Category;
use Ruvelo\Feedback\Models\Entry;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Models\Tag;
use Ruvelo\Feedback\Support\Palette;

class RequestController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'pending' ? 'pending' : 'all';
        $status = $request->query('status');
        $status = is_string($status) && isset(Palette::statuses()[$status]) ? $status : null;
        $q = is_string($request->query('q')) ? trim($request->query('q')) : '';

        $requests = FeatureRequest::query()
            ->whereNull('merged_into_id')
            ->with(['author', 'category'])
            ->when($tab === 'pending', fn (Builder $query) => $query->whereNull('approved_at'))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($q !== '', fn (Builder $query) => $query->matching($q))
            ->orderByRaw($tab === 'pending' ? 'id asc' : 'votes_count desc, id desc')
            ->paginate(30)
            ->withQueryString();

        return view('feedback::manage.requests', [
            'requests' => $requests,
            'tab' => $tab,
            'status' => $status,
            'q' => $q,
            'pending' => FeatureRequest::query()->whereNull('merged_into_id')->whereNull('approved_at')->count(),
        ]);
    }

    public function edit(FeatureRequest $featureRequest): View
    {
        return view('feedback::manage.request-edit', [
            'feature' => $featureRequest->load('tags'),
            'categories' => Category::query()->orderBy('position')->orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, FeatureRequest $featureRequest): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:'.(int) config('feedback.max_length.title', 160)],
            'body' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('feedback.max_length.body', 10000)],
            'category' => ['sometimes', 'nullable', 'integer', Rule::exists(Feedback::table('categories'), 'id')],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:60'],
            'new_tags' => ['sometimes', 'nullable', 'string', 'max:300'],
        ]);

        $changes = [];
        if (array_key_exists('title', $data)) {
            $changes['title'] = (string) $data['title'];
        }
        if (array_key_exists('body', $data)) {
            $changes['body'] = $data['body'] === null ? null : (string) $data['body'];
        }
        if (array_key_exists('category', $data)) {
            $changes['category'] = $data['category'] === null ? null : (string) $data['category'];
        }
        if (array_key_exists('tags', $data) || array_key_exists('new_tags', $data)) {
            $tags = array_values(array_map('strval', (array) ($data['tags'] ?? [])));
            foreach (explode(',', (string) ($data['new_tags'] ?? '')) as $name) {
                if (trim($name) !== '') {
                    $tags[] = trim($name);
                }
            }
            $changes['tags'] = $tags;
        }

        Feedback::update($featureRequest, $changes);

        return $this->done($request, $featureRequest, 'Request saved.');
    }

    public function approve(Request $request, FeatureRequest $featureRequest): RedirectResponse|JsonResponse
    {
        Feedback::approve($featureRequest);

        return $this->done($request, $featureRequest, 'Approved: it’s on the board now.', back: true);
    }

    public function status(Request $request, FeatureRequest $featureRequest): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Palette::statuses()))],
            'note' => ['nullable', 'string', 'max:5000'],
            'entry' => ['nullable', 'integer', Rule::exists(Feedback::table('entries'), 'id')],
            'notify' => ['sometimes', 'boolean'],
        ]);

        $by = $this->viewer($request);
        $note = isset($data['note']) ? (string) $data['note'] : null;
        $notify = $request->boolean('notify', true);

        if ($data['status'] === 'shipped') {
            $entry = isset($data['entry']) ? Entry::query()->find((int) $data['entry']) : null;
            Feedback::ship($featureRequest, $entry, $note, $by, $notify);
        } else {
            Feedback::changeStatus($featureRequest, (string) $data['status'], $note, $by, $notify);
        }

        return $this->done($request, $featureRequest, 'Status changed to '.Palette::statusLabel((string) $data['status']).'.'.($notify ? ' Voters will hear about it.' : ''));
    }

    public function pin(Request $request, FeatureRequest $featureRequest): RedirectResponse|JsonResponse
    {
        $pinned = $request->has('pinned') ? $request->boolean('pinned') : ! $featureRequest->isPinned();
        Feedback::pin($featureRequest, $pinned);

        return $this->done($request, $featureRequest, $pinned ? 'Pinned to the top of the board.' : 'Unpinned.');
    }

    public function merge(Request $request, FeatureRequest $featureRequest): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['into' => ['required', 'integer', Rule::exists(Feedback::table('requests'), 'id')]]);

        $into = FeatureRequest::query()->findOrFail((int) $data['into']);
        Feedback::merge($featureRequest, $into, $this->viewer($request));

        return $this->done($request, $into->refresh(), 'Merged “'.$featureRequest->title.'” into this request. Its votes came along.');
    }

    public function destroy(Request $request, FeatureRequest $featureRequest): RedirectResponse|JsonResponse
    {
        Feedback::delete($featureRequest);

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('feedback.manage.requests')->with('feedback.status', 'Deleted “'.$featureRequest->title.'”.');
    }

    private function done(Request $request, FeatureRequest $feature, string $message, bool $back = false): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return (new RequestResource($feature->load(['category', 'tags', 'entry'])))->additional(['message' => $message])->response();
        }

        return ($back ? back(fallback: $feature->url()) : redirect()->to($feature->url()))->with('feedback.status', $message);
    }
}
