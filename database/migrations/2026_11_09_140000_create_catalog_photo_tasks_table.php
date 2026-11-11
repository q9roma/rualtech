<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog_photo_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('identity_hash', 64)->unique();
            $table->string('upload_id');
            $table->json('payload');
            $table->string('status')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('lease_until')->nullable();
            $table->string('lease_token', 36)->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('catalog_photo_tasks'); }
};
