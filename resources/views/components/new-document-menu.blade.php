@props(['label' => 'Nowy dokument', 'variant' => 'primary'])

<x-ui.dropdown width="w-72" {{ $attributes }}>
    <x-slot:trigger>
        <x-ui.button :variant="$variant" icon="plus" icon-right="chevron-down">{{ $label }}</x-ui.button>
    </x-slot:trigger>

    @foreach (\App\Enums\DocumentType::cases() as $type)
        <a href="{{ route('documents.create', $type->value) }}" wire:navigate role="menuitem"
           class="flex items-start gap-3 rounded-lg px-2.5 py-2 transition-colors hover:bg-surface-2">
            <x-ui.doc-type :type="$type" class="mt-0.5 w-12 justify-center" />
            <span class="min-w-0 leading-tight">
                <span class="block text-[0.8125rem] font-medium text-ink">{{ $type->label() }}</span>
                <span class="block text-xs text-ink-4">{{ $type->description() }}</span>
            </span>
        </a>
    @endforeach
</x-ui.dropdown>
