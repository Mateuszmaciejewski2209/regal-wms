<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stany magazynowe i kartoteka ruchów.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bieżący stan: ilość i wartość. Cena średnia ważona = value / quantity.
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('value', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });

        // Kartoteka – niezmienny dziennik ruchów (append-only).
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('document_id')->constrained();
            $table->decimal('quantity', 14, 3);          // ze znakiem: + przychód, - rozchód
            $table->decimal('unit_price', 14, 4);
            $table->decimal('balance_quantity', 14, 3);  // stan po ruchu
            $table->decimal('balance_value', 18, 4);
            $table->boolean('is_reversal')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'warehouse_id', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
};
