<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('catalog_approved_media', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->primary();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->json('identity');
            $table->json('images');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('catalog_approved_media'); }
};
