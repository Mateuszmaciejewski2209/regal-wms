<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skanery mobilne (telefony), dziennik skanów oraz webhooki wychodzące.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique(); // SHA-256 tokenu – sam token zna tylko telefon
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scanner_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('result', 20);                    // added, found, not_found, rejected
            $table->string('source', 20)->default('phone');  // phone, keyboard, api
            $table->string('message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
        });

        // Dokument, do którego trafiają skany użytkownika (aktywny cel skanera).
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('scanner_document_id')->nullable()->after('is_active')
                ->constrained('documents')->nullOnDelete();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 500);
            $table->string('secret', 64);
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('event', 50);
            $table->json('payload');
            $table->string('status', 12)->default('pending'); // pending, success, failed
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scanner_document_id');
        });
        Schema::dropIfExists('scan_events');
        Schema::dropIfExists('scanner_devices');
    }
};
