<?php

namespace Tests\Feature;

use App\Livewire\Products\Form;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_ean_with_wrong_check_digit_is_rejected_and_valid_one_is_saved(): void
    {
        $manager = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id')]);

        $form = Livewire::actingAs($manager)->test(Form::class)
            ->set('sku', 'TW-EAN01')
            ->set('name', 'Towar z kodem EAN')
            ->set('ean', '5900044170003')
            ->call('save')
            ->assertHasErrors('ean');

        $this->assertFalse(Product::where('sku', 'TW-EAN01')->exists());

        $form->set('ean', '5901234123457')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('5901234123457', Product::where('sku', 'TW-EAN01')->value('ean'));
    }

    public function test_new_product_form_takes_ean_from_unknown_code_link(): void
    {
        $manager = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id')]);

        Livewire::withQueryParams(['ean' => '4006381333931'])->actingAs($manager)->test(Form::class)
            ->assertSet('ean', '4006381333931');

        Livewire::withQueryParams(['ean' => 'RGL:abc'])->actingAs($manager)->test(Form::class)
            ->assertSet('ean', '');
    }
}
