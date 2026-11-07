<!DOCTYPE html>
<html lang="pl" data-theme="light">
<head>
    @include('partials.head', ['title' => 'Brak uprawnień'])
</head>
<body class="min-h-dvh">
    <main class="grid min-h-dvh place-items-center px-4 py-10">
        <div class="w-full max-w-md text-center">
            <div class="mx-auto mb-4 grid size-12 place-items-center rounded-2xl border border-line bg-surface text-ink-3">
                <x-lucide-lock class="size-6" aria-hidden="true" />
            </div>
            <h1 class="text-xl font-semibold tracking-tight">Brak uprawnień</h1>
            <p class="mt-2 text-pretty text-ink-3">
                Twoja rola{{ auth()->user()?->role ? ' („'.auth()->user()->role->name.'”)' : '' }} nie ma dostępu do tej strony lub akcji.
                Jeśli jest Ci potrzebna, poproś kierownika lub administratora o rozszerzenie uprawnień.
            </p>
            <div class="mt-6 flex justify-center gap-2">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" class="btn btn-secondary">Wróć</a>
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Pulpit</a>
            </div>
        </div>
    </main>
</body>
</html>
