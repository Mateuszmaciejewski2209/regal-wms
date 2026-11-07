{{--
    Globalne okno potwierdzenia (zamiast natywnego confirm()).
    Użycie: x-on:click="$confirm({ title, message, confirm: 'Usuń' }).then(ok => ok && $wire.metoda())"
--}}
<div
    x-data
    x-show="$store.confirm.open"
    x-cloak
    x-on:keydown.escape.window="$store.confirm.open && $store.confirm.answer(false)"
    class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-6"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="confirm-title"
    aria-describedby="confirm-message"
>
    <div x-show="$store.confirm.open"
         x-transition:enter="transition-opacity duration-200 ease-out" x-transition:enter-start="opacity-0"
         x-transition:leave="transition-opacity duration-150 ease-out" x-transition:leave-end="opacity-0"
         class="absolute inset-0 bg-black/45" x-on:click="$store.confirm.answer(false)"></div>

    <div x-show="$store.confirm.open"
         x-trap.noscroll="$store.confirm.open"
         x-transition:enter="transition duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]"
         x-transition:enter-start="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-[0.97]"
         x-transition:leave="transition duration-150 ease-out"
         x-transition:leave-end="opacity-0 sm:scale-[0.98]"
         class="relative w-full rounded-t-2xl border border-line bg-surface shadow-pop sm:max-w-md sm:rounded-2xl">
        <div class="flex gap-3 px-5 pt-5 pb-4">
            <span class="grid size-9 shrink-0 place-items-center rounded-full"
                  :class="$store.confirm.danger ? 'bg-danger-soft text-danger' : 'bg-surface-3 text-ink-2'">
                <x-lucide-triangle-alert class="size-4" aria-hidden="true" />
            </span>
            <div class="min-w-0 pt-0.5">
                <h2 id="confirm-title" class="font-semibold text-ink" x-text="$store.confirm.title"></h2>
                <p id="confirm-message" class="mt-1 text-pretty text-[0.8125rem] text-ink-3" x-text="$store.confirm.message"></p>
            </div>
        </div>
        <div class="flex flex-col-reverse gap-2 border-t border-line bg-surface-2/50 px-5 py-3 sm:flex-row sm:justify-end">
            <button type="button" class="btn btn-ghost" x-on:click="$store.confirm.answer(false)" x-text="$store.confirm.cancel">Anuluj</button>
            <button type="button" class="btn" :class="$store.confirm.danger ? 'btn-danger' : 'btn-primary'"
                    x-on:click="$store.confirm.answer(true)" x-text="$store.confirm.confirm" x-init="$watch('$store.confirm.open', v => v && $nextTick(() => $el.focus()))">Potwierdź</button>
        </div>
    </div>
</div>
