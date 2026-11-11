<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog_product_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('kind');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->json('changes');
            $table->json('context');
            $table->timestamp('created_at');
        });
        Schema::table('catalog_source_products', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('missing_since')->nullable();
        });
        Schema::table('catalog_price_uploads', function (Blueprint $table) {
            $table->date('price_date')->nullable();
            $table->json('safety')->nullable();
            $table->string('safety_ack', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_price_uploads', fn (Blueprint $table) => $table->dropColumn(['price_date','safety','safety_ack']));
        Schema::table('catalog_source_products', fn (Blueprint $table) => $table->dropColumn(['last_seen_at','last_validated_at','missing_since']));
        Schema::dropIfExists('catalog_product_events');
    }
};
