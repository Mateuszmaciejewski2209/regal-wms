<div class="mx-auto max-w-3xl">
    <x-ui.page-header :title="$product ? 'Edycja towaru' : 'Nowy towar'" :description="$product?->name"
                      :back="$product ? route('products.show', $product) : route('products.index')" :back-label="$product ? 'Karta towaru' : 'Towary'" />

    <form wire:submit="save" class="card divide-y divide-line">
        <div class="grid gap-4 p-5 sm:grid-cols-6">
            <x-ui.field label="Nazwa" for="name" error="name" required class="sm:col-span-6">
                <input id="name" type="text" wire:model="name" class="control" autocomplete="off" autofocus @error('name') aria-invalid="true" @enderror>
            </x-ui.field>

            <x-ui.field label="Indeks (SKU)" for="sku" error="sku" required hint="Zakodowany w etykiecie QR." class="sm:col-span-3">
                <input id="sku" type="text" wire:model="sku" class="control font-mono" autocomplete="off" spellcheck="false" @error('sku') aria-invalid="true" @enderror>
            </x-ui.field>

            <x-ui.field label="Kod EAN" for="ean" error="ean" hint="Kod kreskowy producenta – też da się zeskanować." class="sm:col-span-3">
                <input id="ean" type="text" inputmode="numeric" wire:model="ean" class="control font-mono" autocomplete="off" @error('ean') aria-invalid="true" @enderror>
            </x-ui.field>

            <x-ui.field label="Kategoria" for="category_id" error="category_id" class="sm:col-span-3">
                <x-ui.select id="category_id" wire:model="category_id" :options="['' => 'Bez kategorii'] + $categories->pluck('name', 'id')->all()" nullable />
            </x-ui.field>

            <x-ui.field label="Jednostka miary" for="unit" error="unit" required class="sm:col-span-3">
                <x-ui.select id="unit" wire:model="unit" :options="collect($units)->mapWithKeys(fn ($u) => [$u->value => $u->value.' – '.$u->label()])->all()" />
            </x-ui.field>

            <x-ui.field label="Stan minimalny" for="min_stock" error="min_stock" required hint="Suma we wszystkich magazynach; 0 = bez alertu." class="sm:col-span-2">
                <input id="min_stock" type="text" inputmode="decimal" wire:model="min_stock" class="control text-right tabular-nums" @error('min_stock') aria-invalid="true" @enderror>
            </x-ui.field>

            <x-ui.field label="Cena zakupu (netto)" for="default_price" error="default_price" hint="Podpowiadana na PZ." class="sm:col-span-2">
                <div class="relative">
                    <input id="default_price" type="text" inputmode="decimal" wire:model="default_price" class="control pr-8 text-right tabular-nums" placeholder="0,00" @error('default_price') aria-invalid="true" @enderror>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[0.8125rem] text-ink-4" aria-hidden="true">zł</span>
                </div>
            </x-ui.field>

            <x-ui.field label="Stawka VAT" for="vat_rate" error="vat_rate" required class="sm:col-span-2">
                <x-ui.select id="vat_rate" wire:model="vat_rate" :options="[23 => '23%', 8 => '8%', 5 => '5%', 0 => '0%']" />
            </x-ui.field>

            <x-ui.field label="Opis" for="description" error="description" class="sm:col-span-6">
                <textarea id="description" wire:model="description" rows="3" class="control"></textarea>
            </x-ui.field>

            <label class="flex items-center gap-2 text-ink-2 sm:col-span-6">
                <input type="checkbox" wire:model="is_active">
                Towar aktywny (dostępny w dokumentach)
            </label>
        </div>

        <div class="flex justify-end gap-2 bg-surface-2/50 px-5 py-3">
            <x-ui.button :href="$product ? route('products.show', $product) : route('products.index')" variant="ghost">Anuluj</x-ui.button>
            <x-ui.button type="submit" variant="primary">Zapisz</x-ui.button>
        </div>
    </form>
</div>
