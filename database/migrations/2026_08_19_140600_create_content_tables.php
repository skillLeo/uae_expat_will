<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('breadcrumb')->nullable();
            $table->json('structured_data')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('order')->default(0);
            // Multilingual-ready. English only at launch, but the column exists so
            // a second locale is a row, not a migration.
            $table->string('locale', 10)->default('en')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->unsignedInteger('order')->default(0);
            $table->string('type', 40);
            $table->text('heading')->nullable();
            $table->text('subheading')->nullable();
            $table->longText('body')->nullable();
            $table->json('items')->nullable();
            $table->json('settings')->nullable();
            $table->string('locale', 10)->default('en');
            $table->timestamps();

            $table->index(['page_id', 'order']);
        });

        Schema::create('faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('order')->default(0);
            $table->string('label');
            $table->string('locale', 10)->default('en');
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category_key')->index();
            $table->unsignedInteger('order')->default(0);
            $table->text('question');
            $table->longText('answer');
            $table->boolean('is_published')->default(true);
            $table->string('anchor')->nullable()->index();
            $table->string('locale', 10)->default('en');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('faq_categories');
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('pages');
    }
};
