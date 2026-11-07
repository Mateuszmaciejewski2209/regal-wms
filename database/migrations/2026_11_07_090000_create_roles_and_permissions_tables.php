<?php

use App\Enums\Ability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kontrola dostępu oparta na rolach (RBAC) z hierarchią poziomów.
 * Użytkownik zarządza wyłącznie kontami i rolami o niższym poziomie niż własny.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80)->unique();
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('level');           // 1–100; wyższy = więcej w hierarchii
            $table->boolean('is_system')->default(false);    // rola wbudowana (Administrator) – pełne prawa, bez edycji
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->restrictOnDelete();
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }

    /** Dane referencyjne: wszystkie uprawnienia i cztery role startowe. */
    private function seedDefaults(): void
    {
        $now = now();

        foreach (Ability::cases() as $ability) {
            DB::table('permissions')->insert(['key' => $ability->value]);
        }

        $roles = [
            ['admin', 'Administrator', 'Pełny dostęp, w tym role, konta i integracje.', 100, true, array_map(fn ($a) => $a->value, Ability::cases())],
            ['manager', 'Kierownik magazynu', 'Zatwierdza inwentaryzacje, wykonuje storno, prowadzi kartoteki i konta magazynierów.', 50, false, [
                'documents.view', 'documents.create', 'documents.approve', 'inventory.approve', 'documents.cancel',
                'stock.view', 'stock.export', 'products.manage', 'contractors.manage', 'warehouses.manage', 'scanner.use', 'users.manage',
            ]],
            ['storekeeper', 'Magazynier', 'Przyjmuje i wydaje towar, skanuje, tworzy i zatwierdza dokumenty operacyjne.', 10, false, [
                'documents.view', 'documents.create', 'documents.approve', 'stock.view', 'scanner.use',
            ]],
            ['viewer', 'Księgowość (podgląd)', 'Podgląd dokumentów i stanów, eksport do CSV.', 5, false, [
                'documents.view', 'stock.view', 'stock.export',
            ]],
        ];

        $permissionIds = DB::table('permissions')->pluck('id', 'key');

        foreach ($roles as [$slug, $name, $description, $level, $system, $abilities]) {
            $roleId = DB::table('roles')->insertGetId([
                'slug' => $slug, 'name' => $name, 'description' => $description,
                'level' => $level, 'is_system' => $system, 'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('permission_role')->insert(array_map(
                fn ($key) => ['role_id' => $roleId, 'permission_id' => $permissionIds[$key]],
                $abilities,
            ));
        }
    }
};
