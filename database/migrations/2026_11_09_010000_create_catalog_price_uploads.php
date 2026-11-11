<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog_price_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('status')->default('queued');
            $table->string('fingerprint', 64)->nullable();
            $table->string('file_hash', 64);
            $table->string('staged_hash', 64)->nullable();
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('catalog_price_uploads'); }
};
