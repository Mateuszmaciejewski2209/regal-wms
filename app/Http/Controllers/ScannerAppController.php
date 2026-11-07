<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Mobilna aplikacja skanera (PWA): strona, manifest i service worker.
 */
class ScannerAppController extends Controller
{
    public function show(): View
    {
        return view('scanner.app');
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => 'Regał – Skaner',
            'short_name' => 'Skaner',
            'description' => 'Mobilny skaner kodów QR i kreskowych dla Regał WMS',
            'lang' => 'pl',
            'start_url' => route('scanner.app', absolute: false),
            'scope' => '/skaner',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#0B0F14',
            'theme_color' => '#0B0F14',
            'icons' => [
                ['src' => asset('icons/scanner-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('icons/scanner-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => asset('icons/scanner.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /** Service worker serwowany z /skaner/sw.js, by jego zakres obejmował całą aplikację skanera. */
    public function serviceWorker(): Response
    {
        return response(view('scanner.sw')->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/skaner',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
