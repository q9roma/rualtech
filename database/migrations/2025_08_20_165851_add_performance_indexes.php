<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Индексы для service_categories
        Schema::table('service_categories', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order']);
            $table->index('slug');
        });

        // Индексы для services
        Schema::table('services', function (Blueprint $table) {
            $table->index(['service_category_id', 'is_active', 'sort_order']);
            $table->index(['slug', 'is_active']);
        });

        // Индексы для blog_posts
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->index(['is_published', 'published_at']);
            $table->index('slug');
        });

        // Индексы для pages
        Schema::table('pages', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'sort_order']);
            $table->dropIndex(['slug']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['service_category_id', 'is_active', 'sort_order']);
            $table->dropIndex(['slug', 'is_active']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex(['is_published', 'published_at']);
            $table->dropIndex(['slug']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['slug']);
        });
    }
};
