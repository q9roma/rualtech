<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commerce_product_links', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->unique()->constrained('lunar_product_variants');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_product_links');
    }
};
