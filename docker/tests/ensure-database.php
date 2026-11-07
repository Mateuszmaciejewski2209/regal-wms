<?php

/**
 * Tworzy bazę testową PostgreSQL (DB_DATABASE), jeśli jeszcze nie istnieje.
 *
 * Uruchamiane przez usługę tests-pg przed `php artisan test`. Testy czyszczą
 * bazę (RefreshDatabase), dlatego korzystają z osobnej bazy, a nie z bazy aplikacji.
 */
$database = getenv('DB_DATABASE');

if ($database === false || $database === '' || $database === 'regal') {
    fwrite(STDERR, "DB_DATABASE musi wskazywać osobną bazę testową (np. regal_test).\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=postgres', getenv('DB_HOST') ?: 'db', getenv('DB_PORT') ?: '5432'),
    getenv('DB_USERNAME') ?: 'regal',
    getenv('DB_PASSWORD') ?: 'regal',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$query = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
$query->execute([$database]);

if ($query->fetchColumn() === false) {
    $pdo->exec('CREATE DATABASE "'.str_replace('"', '""', $database).'"');
    echo "Utworzono bazę testową {$database}.\n";
}
