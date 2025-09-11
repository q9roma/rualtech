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
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('subject')->default('Заказ услуги');
            $table->text('message');
            $table->string('service')->nullable(); // Название услуги
            $table->string('source')->default('website'); // website, service_page, etc.
            $table->enum('status', ['new', 'in_progress', 'completed', 'cancelled'])->default('new');
            $table->timestamp('processed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->json('meta_data')->nullable(); // Дополнительные данные
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index(['email', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
