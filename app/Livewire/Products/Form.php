<?php

namespace App\Livewire\Products;

use App\Enums\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Rules\GtinCheckDigit;
use App\Support\Num;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?Product $product = null;

    public string $sku = '';

    public string $ean = '';

    public string $name = '';

    public ?int $category_id = null;

    public string $unit = 'szt';

    public string $min_stock = '0';

    public string $default_price = '';

    public int $vat_rate = 23;

    public string $description = '';

    public bool $is_active = true;

    /** Każde żądanie (również zapis) wymaga prawa do kartoteki towarów. */
    public function boot(): void
    {
        Gate::authorize('products.manage');
    }

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->product = $product;
            $this->fill($product->only(['sku', 'name', 'category_id', 'vat_rate', 'is_active']));
            $this->ean = (string) $product->ean;
            $this->unit = $product->unit->value;
            $this->min_stock = Num::input($product->min_stock);
            $this->default_price = Num::input($product->default_price, 2);
            $this->description = (string) $product->description;
        } else {
            $this->sku = Product::nextSku();
            // Nieznany kod z czytnika w dokumencie: formularz od razu z tym kodem EAN.
            $ean = (string) request()->query('ean', '');
            $this->ean = preg_match('/^\d{8}$|^\d{12,14}$/', $ean) ? $ean : '';
        }
    }

    public function save()
    {
        $id = $this->product?->id;

        $data = $this->validate([
            'sku' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._\-\/]+$/', Rule::unique('products', 'sku')->ignore($id)],
            'ean' => ['nullable', 'string', 'regex:/^\d{8}$|^\d{12,14}$/', new GtinCheckDigit, Rule::unique('products', 'ean')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'unit' => ['required', Rule::enum(Unit::class)],
            'min_stock' => ['required'],
            'default_price' => ['nullable'],
            'vat_rate' => ['required', 'integer', Rule::in([0, 5, 8, 23])],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ], [
            'sku.regex' => 'Indeks może zawierać litery, cyfry oraz znaki . _ - /',
            'ean.regex' => 'EAN musi mieć 8 lub 12–14 cyfr.',
        ]);

        $data['min_stock'] = Num::parse($this->min_stock);
        $data['default_price'] = $this->default_price === '' ? null : Num::parse($this->default_price);
        $data['ean'] = $this->ean ?: null;

        if ($data['min_stock'] === null || bccomp($data['min_stock'], '0', 3) < 0) {
            $this->addError('min_stock', 'Podaj nieujemną liczbę.');

            return null;
        }

        if ($this->default_price !== '' && $data['default_price'] === null) {
            $this->addError('default_price', 'Podaj cenę, np. 12,50.');

            return null;
        }

        $product = $this->product ?? new Product;
        $product->fill($data)->save();

        $this->dispatch('toast', title: 'Zapisano towar', message: $product->name, tone: 'success');

        return $this->redirectRoute('products.show', $product, navigate: true);
    }

    public function render()
    {
        return view('livewire.products.form', [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::cases(),
        ])->title($this->product ? 'Edycja: '.$this->product->name : 'Nowy towar');
    }
}
