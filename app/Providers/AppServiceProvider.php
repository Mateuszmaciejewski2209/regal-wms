<?php

namespace App\Providers;

use App\Enums\Ability;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('pl');

        $this->defineGates();
        $this->defineRateLimits();
        $this->defineDevProcesses();
    }

    /**
     * Limity API skanera. Nazwane limitery mają osobne liczniki: parowanie liczone per adres IP,
     * skany per sparowane urządzenie (telefony za jednym tunelem HTTPS lub NAT nie dzielą limitu).
     */
    private function defineRateLimits(): void
    {
        RateLimiter::for('scanner-pair', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('scanner', fn (Request $request) => Limit::perMinute(240)->by(
            $request->attributes->get('scanner_device')?->id ?? $request->ip()
        ));
    }

    /**
     * `composer dev` / `php artisan dev`: serwer HTTP dostępny w sieci LAN (telefon),
     * WebSocket (Reverb), kolejka (webhooki) i Vite.
     */
    private function defineDevProcesses(): void
    {
        if (! $this->app->runningInConsole() || ! class_exists(DevCommands::class)) {
            return;
        }

        DevCommands::withoutDefaultCommands();
        DevCommands::artisan('serve --host=0.0.0.0 --port=8000', 'server');
        DevCommands::artisan('reverb:start', 'reverb');
        DevCommands::artisan('queue:listen --tries=1 --timeout=0', 'queue');
        DevCommands::node('dev', 'vite');
    }

    /**
     * RBAC: każde uprawnienie z enuma Ability jest bramką (np. @can('products.manage')),
     * rozstrzyganą na podstawie roli użytkownika. Bramki złożone łączą uprawnienia z regułami biznesowymi.
     */
    private function defineGates(): void
    {
        Gate::before(function (User $user, string $ability) {
            if (! $user->is_active) {
                return false;
            }

            return Ability::tryFrom($ability) ? $user->hasAbility($ability) : null;
        });

        Gate::define('approve-document', fn (User $user, Document $document) => $user->hasAbility(
            $document->type === DocumentType::INW ? Ability::InventoryApprove : Ability::DocumentsApprove
        ));

        // Szkic: autor albo osoba z prawem storna. Zatwierdzony: tylko prawo storna.
        Gate::define('cancel-document', fn (User $user, Document $document) => $document->isDraft()
            ? $user->hasAbility(Ability::DocumentsCreate) && ($document->created_by === $user->id || $user->hasAbility(Ability::DocumentsCancel))
            : $user->hasAbility(Ability::DocumentsCancel));

        // Hierarchia: konto lub rolę można zmieniać tylko „w dół”.
        Gate::define('manage-user', fn (User $user, User $target) => $user->hasAbility(Ability::UsersManage) && ($user->id === $target->id || $user->outranks($target)));
        Gate::define('manage-role', fn (User $user, Role $role) => $user->hasAbility(Ability::RolesManage) && ! $role->is_system && $user->canAssignRole($role));
    }
}
