<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresowanie składowania: regały (z pozycją na planie hali) i rozmieszczenie towarów na półkach.
 *
 * Miejsce składowania = regał + półka (poziom, 1 = najniższa) + pole na półce.
 * Rozmieszczenie jest informacyjne (gdzie leży towar i ile miejsca zajmuje) –
 * stan ilościowy nadal prowadzony jest per magazyn przez dokumenty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->unsignedSmallInteger('plan_width')->default(36)->after('address');  // szerokość planu w kratkach
            $table->unsignedSmallInteger('plan_height')->default(20)->after('plan_width');
        });

        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->unsignedTinyInteger('levels');            // liczba półek (poziomów)
            $table->unsignedTinyInteger('bays');              // liczba pól na półce
            $table->unsignedSmallInteger('pos_x')->default(0); // pozycja na planie (kratki)
            $table->unsignedSmallInteger('pos_y')->default(0);
            $table->boolean('rotated')->default(false);        // ustawiony pionowo na planie
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('product_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->unsignedTinyInteger('bay');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('fill');               // ile miejsca pola zajmuje towar (%)
            $table->decimal('quantity', 14, 3)->nullable();    // orientacyjna ilość na tym miejscu
            $table->timestamps();

            $table->unique(['rack_id', 'level', 'bay', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_locations');
        Schema::dropIfExists('racks');
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['plan_width', 'plan_height']);
        });
    }
};
