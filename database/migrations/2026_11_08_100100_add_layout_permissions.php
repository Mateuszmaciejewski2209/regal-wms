<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nowe uprawnienia modułu regałów: rozmieszczanie towarów (magazynier, kierownik)
 * i konfiguracja regałów/planu hali (kierownik). Administrator ma wszystko z definicji.
 *
 * Idempotentna: na świeżej bazie uprawnienia mogą już istnieć (migracja ról wstawia wszystkie
 * przypadki enuma Ability), na istniejącej – zostaną dodane.
 */
return new class extends Migration
{
    private const GRANTS = [
        'locations.manage' => ['manager', 'storekeeper'],
        'layout.manage' => ['manager'],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $key => $roles) {
            DB::table('permissions')->insertOrIgnore(['key' => $key]);
            $permissionId = DB::table('permissions')->where('key', $key)->value('id');

            foreach (DB::table('roles')->whereIn('slug', $roles)->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', array_keys(self::GRANTS))->delete();
    }
};
