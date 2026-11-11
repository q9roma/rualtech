<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('catalog_category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->string('source_category')->nullable();
            $table->string('stock_free')->nullable();
            $table->string('stock_transit')->nullable();
        });
        Schema::create('catalog_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('source_category');
            $table->string('rule_code');
            $table->string('mapping_key', 64)->unique();
            $table->string('name');
            $table->string('decision');
            $table->foreignId('catalog_category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->boolean('manual_locked')->default(false);
            $table->timestamps();
        });
        Schema::create('catalog_source_products', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('identity_hash', 64);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['source', 'identity_hash']);
        });
        Schema::create('catalog_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('file_hash', 64);
            $table->string('rules_version');
            $table->string('status');
            $table->json('summary')->nullable();
            $table->timestamps();
        });
        Schema::create('catalog_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('catalog_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->string('decision');
            $table->string('operation');
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('payload');
            $table->timestamps();
            $table->unique(['batch_id', 'source_row']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_import_rows');
        Schema::dropIfExists('catalog_import_batches');
        Schema::dropIfExists('catalog_source_products');
        Schema::dropIfExists('catalog_import_mappings');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_category_id');
            $table->dropColumn(['source_category', 'stock_free', 'stock_transit']);
        });
        Schema::dropIfExists('product_categories');
    }
};
