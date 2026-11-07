<?php

/*
 * Wspólny bootstrap skryptów pomiarowych uruchamianych w kontenerze app (kopiowane do /tmp/perf).
 * Ładuje aplikację tak jak artisan – z konfiguracją i połączeniem z bazą kontenera. Kod aplikacji nie jest zmieniany.
 */

require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION), PHP_EOL;
}
