<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('catalog_import_mappings',fn(Blueprint $table)=>$table->string('decision_identity',64)->nullable()); }
    public function down(): void { Schema::table('catalog_import_mappings',fn(Blueprint $table)=>$table->dropColumn('decision_identity')); }
};
