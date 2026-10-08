<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Ruvelo\Feedback\Models\Entry;

/**
 * For your own tests:
 *
 *     Entry::factory()->create();                         // published a moment ago
 *     Entry::factory()->labels('new', 'fixed')->create();
 *     Entry::factory()->draft()->create();
 *     Entry::factory()->publishedAt(now()->addWeek())->create();  // scheduled
 *
 * The factory writes rows directly: linked requests aren't shipped and no
 * events fire. Use Feedback::publish() when you want those.
 *
 * @extends Factory<Entry>
 */
final class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(4), '.');

        return [
            'title' => $title,
            'slug' => Entry::slugFor($title).'-'.Str::lower(Str::random(5)),
            'body' => $this->faker->paragraphs(2, true),
            'labels' => ['new'],
            'published_at' => Carbon::now()->subMinute(),
        ];
    }

    public function labels(string ...$labels): self
    {
        return $this->state(fn () => ['labels' => array_values($labels)]);
    }

    public function draft(): self
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    public function publishedAt(\DateTimeInterface $at): self
    {
        return $this->state(fn () => ['published_at' => $at]);
    }
}
