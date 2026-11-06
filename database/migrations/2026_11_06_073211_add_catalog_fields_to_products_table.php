<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('name');
            $table->string('slug')->nullable()->after('sku');
            $table->boolean('is_active')->default(true)->after('stock');
            $table->softDeletes();
        });

        DB::table('products')->orderBy('id')->each(function ($product) {
            DB::table('products')->where('id', $product->id)->update([
                'sku' => 'SKU-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT),
                'slug' => Str::slug($product->name).'-'.$product->id,
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique('sku');
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropUnique(['slug']);
            $table->dropSoftDeletes();
            $table->dropColumn(['sku', 'slug', 'is_active']);
        });
    }
};
