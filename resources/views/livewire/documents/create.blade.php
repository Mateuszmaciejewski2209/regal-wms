<div class="mx-auto max-w-2xl">
    <x-ui.page-header :title="$docType->label()" :description="$docType->description().'. Po utworzeniu szkicu dodasz pozycje – ręcznie, czytnikiem USB albo telefonem.'"
                      :back="route('documents.index', ['type' => $docType->value])" back-label="Dokumenty">
        <x-slot:meta>
            <div class="mt-3"><x-ui.doc-type :type="$docType" /></div>
        </x-slot:meta>
    </x-ui.page-header>

    @if ($lock)
        <div class="mb-3 flex items-start gap-3 rounded-xl border border-warn/30 bg-warn-soft p-3 text-[0.8125rem] text-ink-2" role="status">
            <x-lucide-lock class="mt-0.5 size-4 shrink-0 text-warn" aria-hidden="true" />
            <p>
                <span class="font-medium text-ink">Magazyn {{ $lock->warehouse->code }} jest w trakcie inwentaryzacji.</span>
                Do zakończenia spisu <a href="{{ route('documents.show', $lock) }}" wire:navigate class="font-medium text-ink underline underline-offset-2">{{ $lock->displayNumber() }}</a>
                nie można tworzyć ani księgować dokumentów w tym magazynie.
            </p>
        </div>
    @endif

    <form wire:submit="save" class="card divide-y divide-line">
        <div class="grid gap-4 p-5 sm:grid-cols-2">
            <x-ui.field :label="$docType->requiresTargetWarehouse() ? 'Magazyn źródłowy' : 'Magazyn'" for="warehouse_id" error="warehouse_id" required>
                <x-ui.select id="warehouse_id" wire:model.live="warehouse_id" :options="$warehouseOptions" nullable :invalid="$errors->has('warehouse_id')" />
            </x-ui.field>

            @if ($docType->requiresTargetWarehouse())
                <x-ui.field label="Magazyn docelowy" for="target_warehouse_id" error="target_warehouse_id" required>
                    <x-ui.select id="target_warehouse_id" wire:model.live="target_warehouse_id" :options="$warehouseOptions" nullable :invalid="$errors->has('target_warehouse_id')" />
                </x-ui.field>
            @endif

            @if ($docType->requiresContractor())
                <x-ui.field :label="$docType->contractorLabel()" for="contractor_id" error="contractor_id" required class="sm:col-span-2">
                    <x-ui.select id="contractor_id" wire:model="contractor_id" :options="$contractorOptions" nullable searchable :invalid="$errors->has('contractor_id')"
                                 :placeholder="'Wybierz '.mb_strtolower($docType->contractorLabel()).'…'" />
                    <p class="mt-1.5 text-[0.8125rem] text-ink-3">
                        Nie ma go na liście?
                        @can('contractors.manage')
                            <a href="{{ route('contractors.index') }}" wire:navigate class="font-medium text-ink underline underline-offset-2">Dodaj kontrahenta</a>.
                        @else
                            Poproś kierownika o dodanie {{ $docType === \App\Enums\DocumentType::PZ ? 'dostawcy' : 'odbiorcy' }}.
                        @endcan
                    </p>
                </x-ui.field>
            @endif

            <x-ui.field label="Data dokumentu" for="issued_at" error="issued_at" required>
                <input id="issued_at" type="date" wire:model="issued_at" class="control">
            </x-ui.field>

            @if ($docType !== \App\Enums\DocumentType::INW)
                <x-ui.field label="Numer obcy (faktura, WZ dostawcy)" for="external_number" error="external_number">
                    <input id="external_number" type="text" wire:model="external_number" class="control" autocomplete="off" placeholder="np. FV/1234/2026…">
                </x-ui.field>
            @endif

            <x-ui.field label="Uwagi" for="notes" error="notes" class="sm:col-span-2">
                <textarea id="notes" wire:model="notes" rows="2" class="control"></textarea>
            </x-ui.field>
        </div>

        <div class="space-y-3 p-5">
            @if ($docType === \App\Enums\DocumentType::INW)
                @cannot('inventory.approve')
                    <div class="flex items-start gap-3 rounded-xl border border-warn/30 bg-warn-soft p-3 text-[0.8125rem] text-ink-2" role="note">
                        <x-lucide-info class="mt-0.5 size-4 shrink-0 text-warn" aria-hidden="true" />
                        <p>Policzysz towar i uzupełnisz arkusz, ale <span class="font-medium text-ink">zatwierdza go kierownik magazynu</span>. Do tego czasu w magazynie nie da się przyjmować ani wydawać towaru.</p>
                    </div>
                @endcannot
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="prefill" class="mt-0.5">
                    <span>
                        <span class="block font-medium text-ink">Wczytaj stany ewidencyjne</span>
                        <span class="block text-[0.8125rem] text-ink-3">Arkusz spisu dostanie wszystkie towary ze stanem w magazynie. Do czasu zatwierdzenia spisu magazyn będzie zablokowany dla innych dokumentów.</span>
                    </span>
                </label>
            @endif
            @can('scanner.use')
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="attachScanner" class="mt-0.5">
                    <span>
                        <span class="block font-medium text-ink">Kieruj skany z mojego telefonu do tego dokumentu</span>
                        <span class="block text-[0.8125rem] text-ink-3">Każdy zeskanowany kod doda pozycję na żywo, bez odświeżania strony.</span>
                    </span>
                </label>
            @endcan
        </div>

        <div class="flex justify-end gap-2 bg-surface-2/50 px-5 py-3">
            <x-ui.button :href="route('documents.index')" variant="ghost">Anuluj</x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="arrow-right" :disabled="(bool) $lock">Utwórz szkic</x-ui.button>
        </div>
    </form>
</div>
