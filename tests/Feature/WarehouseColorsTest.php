<?php

namespace Tests\Feature;

use App\Livewire\Warehouses\Index;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseColorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_warehouse_gets_first_unused_color_and_can_change_it(): void
    {
        Warehouse::factory()->create(['code' => 'MG1', 'color' => 'sky']);
        $manager = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id')]);

        Livewire::actingAs($manager)->test(Index::class)
            ->call('create')
            ->assertSet('form.color', 'amber')
            ->set('form.code', 'mg3')
            ->set('form.name', 'Hala nr 3')
            ->set('form.color', 'rose')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('rose', Warehouse::where('code', 'MG3')->value('color'));
    }

    public function test_unknown_color_is_rejected_and_missing_color_falls_back_to_palette(): void
    {
        $manager = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id')]);

        Livewire::actingAs($manager)->test(Index::class)
            ->call('create')
            ->set('form.code', 'X1')
            ->set('form.name', 'Test')
            ->set('form.color', 'neon')
            ->call('save')
            ->assertHasErrors('form.color');

        $warehouse = Warehouse::factory()->create(['color' => null]);
        $this->assertArrayHasKey($warehouse->colorKey(), Warehouse::COLORS);
    }
}
