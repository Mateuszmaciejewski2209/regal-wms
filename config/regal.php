<?php

return [
    /*
    | Publiczny adres aplikacji widziany z telefonu (kod QR parowania).
    | Kamera w przeglądarce mobilnej wymaga HTTPS – użyj tunelu, np.:
    |   cloudflared tunnel --url http://localhost:8000
    | i wpisz otrzymany adres https://….trycloudflare.com.
    | Puste = adres tunelu Docker (jeśli działa), adres z którego otwarto panel,
    | a dla localhost – wykryty adres IP w sieci LAN.
    */
    'scanner_url' => env('SCANNER_PUBLIC_URL'),

    /*
    | Endpoint metryk cloudflared (profil „tunnel” w compose.yaml) – zwraca nazwę hosta szybkiego tunelu.
    */
    'tunnel_metrics_url' => env('TUNNEL_METRICS_URL'),

    /*
    | Dane demonstracyjne: wgrywane przez `php artisan app:install`, gdy baza jest pusta,
    | i podpowiadane na ekranie logowania.
    */
    'seed_demo' => (bool) env('SEED_DEMO', false),
];
