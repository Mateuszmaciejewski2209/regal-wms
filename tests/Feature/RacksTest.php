<?php

namespace Tests\Feature;

use App\Exceptions\WarehouseException;
use App\Livewire\Layout\Plan;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Layout\RackService;
use App\Services\Scanner\ScannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RacksTest extends TestCase
{
    use RefreshDatabase;

    private RackService $racks;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->racks = app(RackService::class);
        $this->warehouse = Warehouse::factory()->create(['code' => 'MG1']);
    }

    public function test_configurator_generates_codes_with_prefix_padding_and_suffix(): void
    {
        $this->assertSame(['REG-01', 'REG-02', 'REG-03'], $this->racks->codes('REG-', 1, 3, 2));
        $this->assertSame(['A-009-L', 'A-010-L'], $this->racks->codes('A-', 9, 2, 3, '-L'));
    }

    public function test_batch_of_40_racks_is_laid_out_without_overlaps(): void
    {
        $created = $this->racks->createBatch($this->warehouse, $this->racks->codes('REG-', 1, 40, 2), 5, 3, 10);

        $this->assertCount(40, $created);
        $this->assertSame('REG-40', Rack::orderByDesc('code')->value('code'));

        foreach ($created as $a) {
            foreach ($created as $b) {
                if ($a->id !== $b->id) {
                    $f = $b->footprint();
                    $this->assertFalse($a->overlaps($b->pos_x, $b->pos_y, $f['w'], $f['h']), "{$a->code} nachodzi na {$b->code}");
                }
            }
        }

        $this->warehouse->refresh();
        $this->assertGreaterThanOrEqual(10 * 4 + 2, $this->warehouse->plan_width);

        // Druga seria trafia pod pierwszą, a powtórzone kody są odrzucane.
        $this->assertThrows(fn () => $this->racks->createBatch($this->warehouse, ['REG-05'], 4, 3, 8), WarehouseException::class);
    }

    public function test_moving_rack_onto_another_is_rejected(): void
    {
        [$a, $b] = $this->racks->createBatch($this->warehouse, ['R1', 'R2'], 3, 3, 2)->all();

        $this->assertThrows(fn () => $this->racks->move($a, $b->pos_x, $b->pos_y), WarehouseException::class);
        $this->racks->move($a, 1, 10);
        $this->assertSame([1, 10], [$a->fresh()->pos_x, $a->fresh()->pos_y]);
    }

    public function test_slot_capacity_cannot_exceed_100_percent(): void
    {
        $rack = $this->racks->createBatch($this->warehouse, ['R1'], 3, 2, 1)->first();
        [$p1, $p2] = Product::factory()->count(2)->create()->all();

        $this->racks->place($p1, $rack, 2, 1, 70, '10');
        $this->assertSame(30, $this->racks->slotFree($rack, 2, 1));
        $this->assertThrows(fn () => $this->racks->place($p2, $rack, 2, 1, 40), WarehouseException::class);
        $this->racks->place($p2, $rack, 2, 1, 30);
        $this->assertThrows(fn () => $this->racks->place($p1, $rack, 4, 1, 10), WarehouseException::class); // brak półki 4

        $this->assertSame(['R1-2-1'], $this->racks->codesFor([$p1->id], $this->warehouse->id)[$p1->id]);
    }

    public function test_shrinking_rack_with_goods_on_removed_shelf_is_blocked(): void
    {
        $rack = $this->racks->createBatch($this->warehouse, ['R1'], 4, 3, 1)->first();
        $this->racks->place(Product::factory()->create(), $rack, 4, 3, 50);

        $this->assertThrows(fn () => $this->racks->update($rack, ['code' => 'R1', 'levels' => 3, 'bays' => 3]), WarehouseException::class);
    }

    public function test_phone_scans_shelf_label_then_puts_product_away(): void
    {
        $user = User::factory()->create(); // magazynier: locations.manage
        $rack = $this->racks->createBatch($this->warehouse, ['REG-01'], 4, 3, 1)->first()->load('warehouse');
        $product = Product::factory()->create(['sku' => 'TW-1']);
        $scanner = app(ScannerService::class);
        $shelf = ProductLocation::qrPayload($rack, 2, 3);

        $location = $scanner->handleScan($user, $shelf, mode: 'putaway');
        $this->assertSame('location', $location['result']);
        $this->assertSame('REG-01-2-3', $location['location']['code']);

        $placed = $scanner->handleScan($user, 'RGL:TW-1', '4', 'putaway', location: $shelf, fill: 50);
        $this->assertSame('placed', $placed['result']);
        $scanner->handleScan($user, 'RGL:TW-1', '2', 'putaway', location: $shelf, fill: 50);

        $this->assertSame('6.000', (string) ProductLocation::where('product_id', $product->id)->value('quantity'));
        $this->assertSame([['code' => 'REG-01-2-3', 'warehouse' => 'MG1', 'color' => 'sky', 'fill' => 50]], $scanner->productSummary($product)['locations']);
    }

    public function test_phone_scan_without_target_document_is_not_reported_as_success(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['sku' => 'TW-2']);

        $this->assertSame('no_target', app(ScannerService::class)->handleScan($user, 'TW-2')['result']);
    }

    public function test_storekeeper_places_goods_but_cannot_configure_racks(): void
    {
        $rack = $this->racks->createBatch($this->warehouse, ['REG-01'], 3, 3, 1)->first();
        $product = Product::factory()->create();
        $storekeeper = User::factory()->create();

        Livewire::actingAs($storekeeper)->test(Plan::class, ['warehouseId' => $this->warehouse->id])
            ->call('selectRack', $rack->id)
            ->call('openSlot', 1, 1)
            ->set('newFill', 40)
            ->call('place', $product->id)
            ->assertHasNoErrors()
            ->call('openConfigurator')
            ->assertForbidden();

        $this->assertSame(40, ProductLocation::value('fill'));

        $viewer = User::factory()->create(['role_id' => Role::where('slug', 'viewer')->value('id')]);
        Livewire::actingAs($viewer)->test(Plan::class, ['warehouseId' => $this->warehouse->id])
            ->call('selectRack', $rack->id)
            ->call('openSlot', 1, 1)
            ->call('place', $product->id)
            ->assertForbidden();
    }

    public function test_next_product_gets_all_remaining_space_by_default(): void
    {
        $rack = $this->racks->createBatch($this->warehouse, ['REG-01'], 3, 3, 1)->first();
        [$first, $second] = Product::factory()->count(2)->create();
        $storekeeper = User::factory()->create();

        $plan = Livewire::actingAs($storekeeper)->test(Plan::class, ['warehouseId' => $this->warehouse->id])
            ->call('selectRack', $rack->id)
            ->call('openSlot', 1, 1)
            ->assertSet('newFill', 100)
            ->set('newFill', 90)
            ->call('place', $first->id)
            ->assertSet('newFill', 10);

        // Zmniejszenie zajętości pierwszego towaru zapisuje się od razu i zwalnia miejsce dla następnego.
        $id = ProductLocation::value('id');
        $plan->set("slotEdits.{$id}.fill", 60)
            ->call('savePlacement', $id)
            ->assertSet('newFill', 40)
            ->call('place', $second->id)
            ->assertHasNoErrors();

        $this->assertSame([60, 40], ProductLocation::orderBy('id')->pluck('fill')->all());
    }
}
