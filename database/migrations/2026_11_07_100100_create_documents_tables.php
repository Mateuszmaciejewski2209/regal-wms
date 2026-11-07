<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumenty magazynowe (PZ, WZ, PW, RW, MM, INW), ich pozycje i numeracja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 5);
            $table->string('number', 40)->nullable()->unique(); // nadawany przy zatwierdzeniu
            $table->string('status', 12)->default('draft');
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('contractor_id')->nullable()->constrained();
            $table->foreignId('parent_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->date('issued_at');
            $table->string('external_number')->nullable(); // nr faktury / zamówienia
            $table->text('notes')->nullable();
            $table->decimal('total_value', 16, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('issued_at');
        });

        Schema::create('document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 14, 3)->nullable();          // INW: null = pozycja niepoliczona
            $table->decimal('expected_quantity', 14, 3)->nullable(); // INW: stan ewidencyjny
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['document_id', 'product_id']);
        });

        // Ciągła numeracja w obrębie typu i miesiąca, np. PZ/2026/11/0001
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 5);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('last_number')->default(0);

            $table->unique(['type', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('document_items');
        Schema::dropIfExists('documents');
    }
};
