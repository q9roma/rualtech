<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', 255)->nullable()->after('name');
        });

        $rows = DB::table('products')->orderBy('id')->get();
        foreach ($rows as $row) {
            $namePart = Str::slug(Str::limit((string) $row->name, 120, ''));
            if ($namePart === '') {
                $namePart = 'product';
            }
            $skuPart = filled($row->sku) ? Str::slug((string) $row->sku) : '';
            $base = $skuPart !== '' ? $namePart . '-' . $skuPart : $namePart;
            $slug = $base;
            $n = 0;
            while (DB::table('products')->where('slug', $slug)->where('id', '!=', $row->id)->exists()) {
                $slug = $base . '-' . (++$n);
            }
            DB::table('products')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
