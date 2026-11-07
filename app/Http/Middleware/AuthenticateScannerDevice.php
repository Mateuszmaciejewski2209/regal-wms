<?php

namespace App\Http\Middleware;

use App\Enums\Ability;
use App\Models\ScannerDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uwierzytelnia telefon-skaner tokenem Bearer wydanym przy parowaniu.
 */
class AuthenticateScannerDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = ScannerDevice::findByToken($request->bearerToken());

        if (! $device || ! $device->user?->is_active) {
            return response()->json(['message' => 'Urządzenie nie jest sparowane lub zostało odłączone.'], 401);
        }

        if (! $device->user->hasAbility(Ability::ScannerUse)) {
            return response()->json(['message' => 'Rola użytkownika nie ma uprawnienia do skanera mobilnego.'], 403);
        }

        // Ograniczamy zapisy „ostatnio widziany” do jednego na 30 s.
        if (! $device->last_seen_at || $device->last_seen_at->lt(now()->subSeconds(30))) {
            $device->forceFill([
                'last_seen_at' => now(),
                'last_ip' => $request->ip(),
            ])->saveQuietly();
        }

        $request->attributes->set('scanner_device', $device);
        $request->setUserResolver(fn () => $device->user);

        return $next($request);
    }
}
