<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('name');
            $table->string('availability')->nullable(); // ">50", "под заказ" и т.д.
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 10)->default('RUB');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
