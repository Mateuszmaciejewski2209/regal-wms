# Pomiary wydajności (podrozdział 4.11)

Skrypty mierzą działającą aplikację w Docker Compose (`http://127.0.0.1:8000`). Nie zmieniają kodu aplikacji:
dane testowe (towary `PERF-…`, szkice) tworzą serwisami aplikacji, a skrypty PHP kopiują do `/tmp/perf` w kontenerze `app`.
Wyniki (CSV/JSON/logi) trafiają do `tests/perf/results/`.

Wymagania: `docker compose up -d`, `npm ci` (Playwright z devDependencies, przeglądarka Chromium jak dla E2E).
Adres można zmienić zmienną `PERF_BASE_URL` (domyślnie `http://127.0.0.1:8000` – nie `localhost`, ze względu na WebSocket).

| Pomiar | Polecenie (pełna wersja) | Krótka wersja (sprawdzenie skryptu) |
|---|---|---|
| M0 testy | `node tests/perf/m0-test-suites.mjs` | – (uruchamia całość raz) |
| M1 czas API skanu | `node tests/perf/m1-scan-latency.mjs` | `node tests/perf/m1-scan-latency.mjs --add 20 --lookup 10 --ui 5` |
| M1 + emulacja 50 ms | `node tests/perf/m1-scan-latency.mjs --latency 50` | `node tests/perf/m1-scan-latency.mjs --latency 50 --add 20 --lookup 10 --ui 5` |
| M2 propagacja | `node tests/perf/m2-live-propagation.mjs` | `node tests/perf/m2-live-propagation.mjs --reps 6 --approvals 3` |
| M2 + emulacja 50 ms | `node tests/perf/m2-live-propagation.mjs --latency 50` | `node tests/perf/m2-live-propagation.mjs --latency 50 --reps 6` |
| M3 równoczesne zatwierdzanie | `node tests/perf/m3-concurrent-approve.mjs` | `node tests/perf/m3-concurrent-approve.mjs --runs-a 1 --runs-b 1` |
| M4 kolejka offline | `node tests/perf/m4-offline-queue.mjs` | `node tests/perf/m4-offline-queue.mjs --reps 1` |
| Reset bazy | `node tests/perf/reset-db.mjs` | – |

Po pomiarach: `node tests/perf/reset-db.mjs` – ta sama procedura co `tests/e2e/global-setup.js`
(`docker compose exec -T app php artisan migrate:fresh --seed --force` + `docker compose restart queue`).

## Metoda w skrócie

- **M1**: telefon = kontekst Playwright „Pixel 7”, sparowany formularzem `/skaner`; dokument wybrany w PWA.
  Sekwencyjne `POST /api/scanner/scans` z tej strony (ten sam `fetch` i nagłówki co `api()` w `resources/js/scanner.js`),
  start co 300 ms (≈200/min < limit 240/min). Czas: `performance.now()` przed `fetch` → po sparsowaniu JSON; TTFB z Resource Timing.
  Wariant UI: ręczne wpisanie kodu w PWA, od zdarzenia `submit` do pojawienia się wyniku w karcie (`aria-live`).
- **M2**: t0 = tuż przed `fetch` w stronie telefonu magazyniera, t1 = pole „Ilość: …” w panelu kierownika ma oczekiwaną wartość
  (MutationObserver + kontrola co 2 ms); osobno chwila odebrania zdarzenia WebSocket `items.changed`.
  Zatwierdzenie: kliknięcie „Zatwierdź” w oknie potwierdzenia (kierownik) → status „Zatwierdzony” na liście `/dokumenty` u magazyniera.
  Różne strony mają różne zegary – przeliczenie na zegar Node metodą NTP (próbka o min. RTT, wynik w JSON).
- **M3**: `php/m3_run.php` w kontenerze uruchamia N osobnych procesów PHP (`proc_open`), każdy otwiera własne połączenie z bazą,
  czeka do wspólnej chwili „go” i woła `DocumentService::approve()`. Weryfikacja: statusy, stan i wartość, ruchy, numeracja.
- **M4**: `context.setOffline(true)`, K/2 kodów → zmiana dokumentu → K/2 kodów → `setOffline(false)`; pomiar do pustej kolejki
  `regal.scanner.queue` i do pojawienia się wszystkich pozycji w obu panelach; weryfikacja w bazie.
  Zmiana dokumentu w PWA wymaga sieci, więc sieć wraca na chwilę, a żądania skanów są w tym czasie zrywane (`page.route` → abort).

## Zależności od środowiska i bazy danych

- Brak zapytań SQL specyficznych dla silnika: tylko Eloquent i query builder (`DB::table('document_sequences')`),
  zdarzenia transakcji Laravel (`TransactionBeginning/Committed/RolledBack`) i `DB::listen` (czas zapytań).
- Połączenie z bazą bierze się z konfiguracji kontenera `app` (zmienne `DB_*` w `compose.yaml`); skrypty nie używają portu bazy na hoście.
- `m3_worker.php` zapisuje klasę i kod błędu (`getCode()`), czyli kod SQLSTATE PostgreSQL (np. zakleszczenie: 40P01).
- `slowest_query_sql` w CSV M3 to SQL wygenerowany przez gramatykę PostgreSQL (identyfikatory w cudzysłowach `"`), tylko informacyjnie.
- Poprawność M3 zależy od blokad wierszy (`lockForUpdate` → `SELECT … FOR UPDATE`) i `insertOrIgnore` w `DocumentNumberGenerator` –
  to kod aplikacji; po zmianie silnika pomiar należy powtórzyć.
- Ścieżki w kontenerze: `/var/www/html` (aplikacja), `/tmp/perf` (skrypty), nazwa usługi `app`.
- PHP do M0: `PHP_BIN` (domyślnie PHP 8.4 z WinGet); PHPUnit używa SQLite w pamięci (`phpunit.xml`).

## Uwaga o limicie żądań

Podczas pomiarów limit `throttle:240,1` na trasach skanera był liczony per adres IP klienta, wspólnie z limitem parowania.
Wszystkie żądania z hosta przychodzą przez bramkę Dockera, więc parowanie tuż po serii skanów dostawało `429 Too Many Attempts.`.
Błąd poprawiono: limiter `scanner` liczy skany osobno dla każdego urządzenia, a parowanie ma własny limit dla adresu IP
(`AppServiceProvider`, test `test_scan_requests_do_not_use_up_the_pairing_limit`). Ponawianie co 20 s w `pairPhone()`
zostało jako zabezpieczenie.
