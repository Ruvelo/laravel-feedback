<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Ruvelo\Feedback\FeedbackServiceProvider;
use Ruvelo\Feedback\Markdown\Renderer;
use Ruvelo\Feedback\Notifications\RequestStatusChanged;
use Ruvelo\Feedback\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [FeedbackServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('f', 32)));
        $app['config']->set('app.url', 'https://app.test');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('feedback.name', 'Halyard');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(Renderer::class)->reset();
        RequestStatusChanged::flush();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function user(string $name = 'Ada Lovelace', array $attributes = []): User
    {
        $first = strtolower(explode(' ', $name)[0]);

        return User::forceCreate([
            'name' => $name,
            'email' => $first.'@example.com',
            'password' => 'secret',
            ...$attributes,
        ]);
    }

    protected function managers(string ...$names): void
    {
        Gate::define('feedback-manage', fn (User $user) => in_array($user->name, $names, true));
    }
}
