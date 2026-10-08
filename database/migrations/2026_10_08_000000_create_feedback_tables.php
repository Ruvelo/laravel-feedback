<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = (string) config('feedback.table_prefix', 'feedback_');

        Schema::create($prefix.'categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create($prefix.'tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create($prefix.'entries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body');
            $table->json('labels')->nullable();
            $table->string('cover_url', 2048)->nullable();
            $table->nullableMorphs('author');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create($prefix.'requests', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->text('body')->nullable();
            $table->string('status', 40)->default('under_review')->index();
            $table->text('status_note')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->nullableMorphs('author');
            $table->foreignId('category_id')->nullable()->constrained($prefix.'categories')->nullOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained($prefix.'entries')->nullOnDelete();
            $table->foreignId('merged_into_id')->nullable()->constrained($prefix.'requests')->nullOnDelete();
            $table->unsignedInteger('votes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['merged_into_id', 'approved_at', 'votes_count']);
        });

        Schema::create($prefix.'request_tag', function (Blueprint $table) use ($prefix) {
            $table->foreignId('request_id')->constrained($prefix.'requests')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained($prefix.'tags')->cascadeOnDelete();
            $table->primary(['request_id', 'tag_id']);
        });

        Schema::create($prefix.'votes', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('request_id')->constrained($prefix.'requests')->cascadeOnDelete();
            $table->morphs('voter');
            $table->timestamp('created_at')->nullable()->index();

            // One vote per person per request, whatever the code path.
            $table->unique(['request_id', 'voter_type', 'voter_id']);
        });

        Schema::create($prefix.'comments', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('request_id')->constrained($prefix.'requests')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained($prefix.'comments')->cascadeOnDelete();
            $table->nullableMorphs('author');
            $table->text('body');
            $table->boolean('official')->default(false);
            $table->timestamps();
        });

        Schema::create($prefix.'status_changes', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('request_id')->constrained($prefix.'requests')->cascadeOnDelete();
            $table->string('from', 40)->nullable();
            $table->string('to', 40);
            $table->text('note')->nullable();
            $table->nullableMorphs('changer');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create($prefix.'seen', function (Blueprint $table) {
            $table->id();
            $table->morphs('viewer');
            $table->timestamp('seen_at');
            $table->unique(['viewer_type', 'viewer_id']);
        });
    }

    public function down(): void
    {
        $prefix = (string) config('feedback.table_prefix', 'feedback_');

        foreach (['seen', 'status_changes', 'comments', 'votes', 'request_tag', 'requests', 'entries', 'tags', 'categories'] as $table) {
            Schema::dropIfExists($prefix.$table);
        }
    }
};
