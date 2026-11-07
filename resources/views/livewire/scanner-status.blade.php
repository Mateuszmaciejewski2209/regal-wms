<div class="flex items-center">
    @if ($target)
        <div class="flex items-center gap-1 rounded-lg border border-accent-line bg-accent-soft py-0.5 pr-0.5 pl-2.5">
            <x-lucide-scan-line class="size-4 shrink-0 text-accent" aria-hidden="true" />
            <a href="{{ route('documents.show', $target) }}" wire:navigate class="max-w-44 truncate text-[0.8125rem] font-medium text-ink" title="Skany z telefonu trafiają do tego dokumentu">
                <span class="hidden sm:inline">Skanuj do:</span> {{ $target->displayNumber() }}
            </a>
            <button type="button" wire:click="clearTarget" class="btn btn-ghost btn-icon btn-sm size-7" aria-label="Odłącz skaner od dokumentu">
                <x-lucide-x aria-hidden="true" />
            </button>
        </div>
    @elseif ($paired > 0)
        <a href="{{ route('scanner.devices') }}" wire:navigate class="btn btn-ghost btn-sm" title="Sparowane telefony">
            <x-lucide-smartphone aria-hidden="true" />
            <span class="num">{{ $online }}/{{ $paired }}</span>
            <span class="hidden sm:inline">online</span>
        </a>
    @else
        <x-ui.button :href="route('scanner.devices')" variant="ghost" size="sm" icon="smartphone">
            <span class="hidden sm:inline">Sparuj telefon</span>
        </x-ui.button>
    @endif
</div>
