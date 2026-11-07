<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

/*
| Instalacja/aktualizacja bazy przy starcie kontenerów (usługa „init” w compose.yaml):
| migracje, a przy pustej bazie i SEED_DEMO=true – dane demonstracyjne.
*/
Artisan::command('app:install', function () {
    $this->call('migrate', ['--force' => true]);

    if (config('regal.seed_demo') && User::query()->doesntExist()) {
        $this->info('Pusta baza – wgrywam dane demonstracyjne…');

        // Podczas instalacji nikt nie słucha zdarzeń na żywo, a Reverb może jeszcze nie działać.
        config(['broadcasting.default' => 'null']);
        app('Illuminate\Broadcasting\BroadcastManager')->purge();

        $this->call('db:seed', ['--force' => true]);
    }

    $this->info('Regał WMS gotowy.');
})->purpose('Migracje i dane demonstracyjne (start kontenerów Docker)');
