<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\Category;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Models\Vote;

/**
 * For your own tests:
 *
 *     FeatureRequest::factory()->by($user)->create();
 *     FeatureRequest::factory()->status('planned')->votedBy($a, $b)->create();
 *     FeatureRequest::factory()->pending()->create();
 *
 * Requests are approved and under review unless you say otherwise. They
 * have no author until you give one with by().
 *
 * @extends Factory<FeatureRequest>
 */
final class FeatureRequestFactory extends Factory
{
    protected $model = FeatureRequest::class;

    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(4), '.');

        return [
            'title' => $title,
            'slug' => FeatureRequest::slugFor($title),
            'body' => $this->faker->paragraph(),
            'status' => 'under_review',
            'approved_at' => Carbon::now(),
        ];
    }

    public function by(Model $author): self
    {
        return $this->state(fn () => [
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->getKey(),
        ]);
    }

    public function titled(string $title): self
    {
        return $this->state(fn () => ['title' => $title, 'slug' => FeatureRequest::slugFor($title)]);
    }

    public function status(string $status, ?string $note = null): self
    {
        return $this->state(fn () => ['status' => $status, 'status_note' => $note, 'status_changed_at' => Carbon::now()]);
    }

    public function in(Category $category): self
    {
        return $this->state(fn () => ['category_id' => $category->id]);
    }

    public function pending(): self
    {
        return $this->state(fn () => ['approved_at' => null]);
    }

    public function pinned(): self
    {
        return $this->state(fn () => ['pinned_at' => Carbon::now()]);
    }

    /**
     * Votes from these people (or models), counted.
     */
    public function votedBy(Model ...$voters): self
    {
        return $this->afterCreating(function (FeatureRequest $request) use ($voters): void {
            foreach ($voters as $voter) {
                Vote::query()->firstOrCreate(['request_id' => $request->id, 'voter_type' => $voter->getMorphClass(), 'voter_id' => $voter->getKey()]);
            }
            Feedback::recount($request);
        });
    }
}
