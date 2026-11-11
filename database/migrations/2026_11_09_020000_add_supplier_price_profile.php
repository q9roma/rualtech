<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('catalog_source_products', function (Blueprint $t) {
            $t->string('supplier_id')->nullable();
            $t->json('supplier_data')->nullable();
        });
        Schema::table('catalog_price_uploads', function (Blueprint $t) {
            $t->string('source_profile')->default('altech-price');
            $t->string('rules_version')->nullable();
        });
    }
    public function down(): void {
        Schema::table('catalog_source_products', fn (Blueprint $t) => $t->dropColumn(['supplier_id','supplier_data']));
        Schema::table('catalog_price_uploads', fn (Blueprint $t) => $t->dropColumn(['source_profile','rules_version']));
    }
};
