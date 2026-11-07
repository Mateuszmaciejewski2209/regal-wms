# Audyt end-to-end – Regał WMS

Data: 2026-11-07. Zakres: przejście głównych ścieżek jako każda rola (administrator, kierownik magazynu, magazynier, księgowość), przegląd kodu pod kątem uprawnień i komunikatów, automatyczne testy E2E (Playwright) na środowisku Docker.

## Metoda

1. **Przegląd kodu per rola** – każdy widok i akcja Livewire sprawdzone pod kątem: czy rola widzi przycisk, którego nie może użyć (403), czy akcja mutująca dane ma kontrolę uprawnień, czy ścieżka magazyniera nie kończy się ślepym zaułkiem.
2. **Przejścia w przeglądarce** – logowanie każdą rolą, realne klikanie (PZ, WZ, INW, plan magazynu, skaner).
3. **Testy automatyczne** – 15 scenariuszy E2E w `tests/e2e/*.spec.js` + 44 testy funkcjonalne PHPUnit.

## Znalezione problemy i stan

### Blokery (zatrzymywały pracę)

| # | Problem | Rola | Stan |
|---|---|---|---|
| B1 | Zmiana ilości/ceny w tabeli dokumentu nie zapisywała się (Livewire 4: `wire:model.blur` tylko zapamiętuje wartość, bez wysyłki) – wyszło dopiero w teście E2E | wszyscy | **naprawione** (`wire:model.live.blur`) + test regresji |
| B2 | Magazynier mógł założyć inwentaryzację, ale nie mógł jej zatwierdzić – magazyn zostawał zablokowany bez informacji, kto ma to zrobić | magazynier | **naprawione** – ostrzeżenie przy tworzeniu, „przekaż kierownikowi”, podpowiedź na przycisku |
| B3 | Skan telefonem bez wybranego dokumentu dawał zielony błysk i dźwięk sukcesu – towar „znikał” | magazynier | **naprawione** – wynik `no_target`, sygnał błędu, telefon sam otwiera wybór dokumentu |
| B4 | Kolejka offline w telefonie wysyłała skany do dokumentu aktualnego w chwili połączenia, nie w chwili skanu | magazynier | **naprawione** – skan niesie `document_id` |
| B5 | Brak dostawcy na liście = brak możliwości przyjęcia (link prowadził do strony bez prawa dodawania) | magazynier | **naprawione** – „Poproś kierownika o dodanie dostawcy” |
| B6 | Migracja nowych uprawnień kolidowała z migracją ról na świeżej bazie | wdrożenie | **naprawione** – migracja idempotentna |

### Wycieki uprawnień (przycisk → 403)

| # | Problem | Stan |
|---|---|---|
| U1 | „Sparuj telefon” / status skanera w pasku górnym widoczny dla roli bez skanera | naprawione |
| U2 | „Nowy dokument” na pulpicie dla roli tylko do podglądu | naprawione |
| U3 | Linki do skanerów na pulpicie | naprawione |
| U4 | Brak polskiej strony 403 – angielski komunikat bez nawigacji | naprawione (`errors/403.blade.php`) |
| U5 | Akcje webhooków i formularz towaru bez kontroli w akcjach Livewire (chronione tylko przez trasę) | naprawione (`boot()` + `Gate::authorize`) |
| U6 | API skanera: rola ze skanerem bez prawa do dokumentów mogła dopisywać pozycje | naprawione (wymagane `documents.create`) |

### Ergonomia i spójność

| # | Problem | Stan |
|---|---|---|
| E1 | Nieczytelna cena („12,5 zł”) cicho zamieniała się na 0 zł | naprawione – błąd przy polu + ostrzeżenie w oknie zatwierdzenia |
| E2 | Edycja nagłówka omijała blokadę inwentaryzacyjną; INW można było „przenieść” do innego magazynu | naprawione |
| E3 | Edycja nagłówka pokazywała nieaktywne magazyny i odbiorców na PZ | naprawione |
| E4 | „Skaner podłączony” bez sparowanego telefonu | naprawione – „Brak sparowanego telefonu – Sparuj” |
| E5 | Okno parowania nie reagowało na wygaśnięcie kodu ani brak WebSocketu | naprawione – odliczanie, „Nowy kod”, odpytywanie co 3 s |
| E6 | Ekran skanerów pełen żargonu (webhook, Bearer, Reverb) zamiast kroków dla magazyniera | naprawione – kroki „Sparuj → Wybierz dokument → Skanuj → Odkładaj na półki”; szczegóły techniczne tylko dla administratora |
| E7 | Błędne formy liczebników („3 pozycji”, „2 poz. przekracza”) | naprawione (`Num::plural`) |
| E8 | Niespójne nazwy (Nr obcy / Nr faktury, magazyn wydający / źródłowy) | ujednolicone |

## Rozszerzenia wprowadzone po audycie

**Plan magazynu i regały** – odpowiedź na pytanie, które magazynier zadaje najczęściej: *gdzie to leży?*

- **Konfigurator serii** – prefiks, numer startowy, liczba cyfr, sufiks (np. `REG-01 … REG-40`), liczba półek i pól, regałów w rzędzie; podgląd kodów na żywo; automatyczne ułożenie w rzędy z alejkami.
- **Plan hali (widok z góry)** – regały na siatce, kolor wg zajętości, przeciąganie w trybie „Ułóż regały” (również strzałkami, `R` obraca), wyszukiwanie towaru podświetla regały i pola.
- **Front regału** – półki od podłogi (P1), pola, towary jako „pudełka” o szerokości = zajmowane miejsce; kliknięcie pola: położenie/zdjęcie towaru, suwak zajętości, orientacyjna ilość; kontrola, by pole nie przekroczyło 100%.
- **Etykiety QR półek** (`RGL:L:MG1:REG-01:2:3`) – druk na arkusz A4 z widoku regału.
- **Telefon**: skan etykiety półki pokazuje jej zawartość; tryb **„Rozmieść”** – zeskanuj półkę, potem towary (z wyborem zajętości 10–100%).
- **Lokalizacje wszędzie, gdzie są potrzebne** – w pozycjach dokumentu („gdzie odłożyć” przy PZ, „skąd pobrać” przy WZ/MM), na wydruku PDF, na karcie towaru („Gdzie leży”), w wyniku skanu.
- **Uprawnienia**: `locations.manage` (magazynier, kierownik), `layout.manage` (kierownik).

**Praca magazyniera** – pulpit z szybkimi akcjami (Przyjmij / Wydaj / Przesuń / Plan), lista „Do dokończenia” (własne szkice), wyszukiwarka **„Gdzie leży towar?”** z każdego ekranu (`Ctrl+K` lub `/`).

## Rekomendacje na kolejny etap (poza zakresem)

1. **Stany per lokalizacja** – obecnie rozmieszczenie jest informacyjne (ilość orientacyjna). Pełne adresowanie wymagałoby, by każdy PZ/WZ/MM wskazywał półkę, a stan był księgowany na lokalizacji.
2. **Lista zbiórki (picking)** – WZ posortowany wg trasy po hali (kolejność regałów), odhaczanie skanem.
3. **Zamówienia do dostawców** – z listy „poniżej minimum” jednym kliknięciem.
4. **Partie i daty ważności** (FEFO) – dla chemii budowlanej i spożywki.
5. **Aplikacja natywna / WPF** – stanowisko przy biurku z czytnikiem i drukarką etykiet; PWA w telefonie pokrywa skanowanie.
