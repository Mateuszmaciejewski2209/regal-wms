<div>
    @if ($inventories->isNotEmpty())
        <div class="flex items-start gap-2.5 border-b border-warn/30 bg-warn-soft px-4 py-2 text-[0.8125rem] text-ink-2 sm:px-6" role="status">
            <x-lucide-lock class="mt-0.5 size-4 shrink-0 text-warn" aria-hidden="true" />
            <p>
                <span class="font-medium text-ink">Trwa inwentaryzacja:</span>
                @foreach ($inventories as $inw)
                    <a href="{{ route('documents.show', $inw) }}" wire:navigate class="font-medium text-ink underline underline-offset-2">{{ $inw->warehouse->code }}</a>@if (! $loop->last), @endif
                @endforeach
                – przyjęcia, wydania i przesunięcia w {{ $inventories->count() > 1 ? 'tych magazynach' : 'tym magazynie' }} są wstrzymane do zatwierdzenia spisu przez kierownika.
            </p>
        </div>
    @endif
</div>
