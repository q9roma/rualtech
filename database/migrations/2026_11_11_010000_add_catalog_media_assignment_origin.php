<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('catalog_approved_media',function(Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('origin')->default('manual');
        });
    }
    public function down(): void {
        Schema::table('catalog_approved_media',fn(Blueprint $table)=>$table->dropColumn('origin'));
    }
};
