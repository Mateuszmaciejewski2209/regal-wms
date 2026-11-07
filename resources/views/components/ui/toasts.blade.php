{{-- Region toastów: aria-live, wejście przez @starting-style, wyjście natychmiastowe. --}}
<div class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end" aria-live="polite">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="enter-up pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-surface p-3 pr-2 shadow-pop">
            <span class="mt-1.5 size-2 shrink-0 rounded-full"
                  :class="{ 'bg-accent': toast.tone === 'success', 'bg-danger': toast.tone === 'danger', 'bg-warn': toast.tone === 'warn', 'bg-ink-4': toast.tone === 'neutral' }"></span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium text-ink" x-show="toast.title" x-text="toast.title"></p>
                <p class="text-pretty text-[0.8125rem] text-ink-3" x-text="toast.message"></p>
            </div>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" x-on:click="$store.toasts.dismiss(toast.id)" aria-label="Zamknij powiadomienie">
                <x-lucide-x aria-hidden="true" />
            </button>
        </div>
    </template>
</div>
