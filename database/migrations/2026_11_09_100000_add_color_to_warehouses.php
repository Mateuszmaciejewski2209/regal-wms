<?php

use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolor magazynu – ułatwia rozróżnianie magazynów w tabelach, dokumentach i na planie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('code');
        });

        // Istniejące magazyny dostają kolejne kolory z palety.
        $palette = array_keys(Warehouse::COLORS);
        foreach (DB::table('warehouses')->orderBy('id')->pluck('id')->values() as $i => $id) {
            DB::table('warehouses')->where('id', $id)->update(['color' => $palette[$i % count($palette)]]);
        }
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
