<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Exceptions\WarehouseException;
use App\Livewire\Documents\Show;
use App\Livewire\Stock\Index as StockIndex;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private DocumentService $documents;

    private User $manager;

    private Warehouse $main;

    private Warehouse $other;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documents = app(DocumentService::class);
        $this->manager = User::factory()->manager()->create();
        $this->main = Warehouse::factory()->create();
        $this->other = Warehouse::factory()->create();
        $this->product = Product::factory()->create(['min_stock' => 5]);

        $pz = $this->documents->createDraft(DocumentType::PZ, ['warehouse_id' => $this->main->id, 'contractor_id' => Contractor::factory()->create()->id], $this->manager);
        $this->documents->addProduct($pz, $this->product, '10', '2.00');
        $this->documents->approve($pz, $this->manager);
    }

    public function test_open_inventory_freezes_the_warehouse(): void
    {
        $draftBefore = $this->documents->createDraft(DocumentType::RW, ['warehouse_id' => $this->main->id], $this->manager);
        $this->documents->addProduct($draftBefore, $this->product, '1');

        $inventory = $this->documents->createDraft(DocumentType::INW, ['warehouse_id' => $this->main->id, 'prefill' => true], $this->manager);

        // nowy dokument w zamrożonym magazynie – odrzucony
        $this->assertThrows(fn () => $this->documents->createDraft(DocumentType::PW, ['warehouse_id' => $this->main->id], $this->manager), WarehouseException::class);
        // drugi spis tego samego magazynu – odrzucony
        $this->assertThrows(fn () => $this->documents->createDraft(DocumentType::INW, ['warehouse_id' => $this->main->id], $this->manager), WarehouseException::class);
        // istniejący szkic nie da się zatwierdzić
        $this->assertThrows(fn () => $this->documents->approve($draftBefore, $this->manager), WarehouseException::class);
        // inny magazyn działa normalnie
        $this->documents->createDraft(DocumentType::PW, ['warehouse_id' => $this->other->id], $this->manager);

        // spis z niedoborem → RW generowane mimo blokady, potem blokada znika
        $inventory->items()->update(['quantity' => 7]);
        $this->documents->approve($inventory, $this->manager);

        $this->assertSame('7.000', (string) $this->product->stocks()->where('warehouse_id', $this->main->id)->value('quantity'));
        $this->assertNull($this->documents->inventoryLock($this->main->id));
        $this->documents->approve($draftBefore, $this->manager);
    }

    public function test_zero_and_quick_actions_are_saved_in_inventory_sheet(): void
    {
        $second = Product::factory()->create();
        $pw = $this->documents->createDraft(DocumentType::PW, ['warehouse_id' => $this->main->id], $this->manager);
        $this->documents->addProduct($pw, $second, '3', '1.00');
        $this->documents->approve($pw, $this->manager);

        $inventory = $this->documents->createDraft(DocumentType::INW, ['warehouse_id' => $this->main->id, 'prefill' => true], $this->manager);
        [$first, $other] = $inventory->items()->reorder()->orderBy('product_id')->get()->all();

        $component = Livewire::actingAs($this->manager)->test(Show::class, ['document' => $inventory])
            ->set("quantities.{$first->id}", '0');

        $this->assertSame('0.000', (string) $first->fresh()->quantity);

        $component->call('setCounted', $other->id, 'book');
        $this->assertSame('3.000', (string) $other->fresh()->quantity);

        $component->call('approve');
        $inventory->refresh();

        $this->assertTrue($inventory->isApproved());
        $this->assertSame('0.000', (string) $this->product->stocks()->where('warehouse_id', $this->main->id)->value('quantity'));
        $this->assertSame(['RW'], $inventory->children->map(fn (Document $d) => $d->type->value)->all());
    }

    public function test_dashboard_and_stock_page_agree_on_low_stock(): void
    {
        // stan łączny 10 ≥ min 5 → nie jest „poniżej minimum”, mimo 0 w drugim magazynie
        $mm = $this->documents->createDraft(DocumentType::MM, ['warehouse_id' => $this->main->id, 'target_warehouse_id' => $this->other->id], $this->manager);
        $this->documents->addProduct($mm, $this->product, '8');
        $this->documents->approve($mm, $this->manager);
        $low = Product::factory()->create(['min_stock' => 2]); // brak stanu → poniżej minimum

        $this->assertSame([$low->id], Product::query()->belowMinimum()->pluck('id')->all());

        Livewire::actingAs($this->manager)->test(StockIndex::class)
            ->set('low', true)
            ->assertSee($low->name)
            ->assertDontSee($this->product->name);
    }
}
