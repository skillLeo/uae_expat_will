<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blog.
 *
 * Wills and inheritance is a subject search engines judge harshly: it decides
 * what happens to somebody's family and money, so a page about it is weighed
 * on who wrote it and whether it is current, not only on what it says. Two
 * columns exist for that reason and are not decoration.
 *
 * `author_name` and `author_title` appear on the page. A legal article with no
 * author reads as nobody's opinion.
 *
 * `reviewed_at` is separate from `published_at` because UAE inheritance law
 * moves. An article written in 2026 and checked in 2028 is worth more than one
 * written in 2028 and never looked at again, and the reader deserves to know
 * which they are reading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('category', 60)->nullable()->index();

            // Shown on the index and used as the meta description when no
            // explicit one is set, so it is never left to a truncated body.
            $table->text('excerpt');
            $table->longText('body');

            $table->string('author_name');
            $table->string('author_title');

            $table->string('seo_title')->nullable();
            $table->string('meta_description', 320)->nullable();

            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Minutes, computed on save. Stored rather than derived so the
            // index page does not re-count every body on every request.
            $table->unsignedSmallInteger('reading_minutes')->default(1);

            $table->string('locale', 5)->default('en')->index();
            $table->timestamps();
            $table->softDeletes();

            // The public list is "published, newest first" and nothing else.
            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
