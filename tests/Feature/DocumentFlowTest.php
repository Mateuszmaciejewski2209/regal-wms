<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\WarehouseException;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    private DocumentService $documents;

    private User $user;

    private Warehouse $main;

    private Warehouse $second;

    private Contractor $contractor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documents = app(DocumentService::class);
        $this->user = User::factory()->manager()->create();
        $this->main = Warehouse::factory()->create(['code' => 'MG1']);
        $this->second = Warehouse::factory()->create(['code' => 'MG2']);
        $this->contractor = Contractor::factory()->create();
        $this->product = Product::factory()->create(['default_price' => 10, 'min_stock' => 0]);
    }

    public function test_receipts_use_weighted_average_price(): void
    {
        $this->receive('10', '10.00');
        $this->receive('10', '20.00');

        $stock = $this->stock($this->main);

        $this->assertSame('20.000', $stock->quantity);
        $this->assertSame('300.0000', $stock->value);
        $this->assertSame('15.0000', $stock->averagePrice());
    }

    public function test_issue_is_valued_at_average_price_and_numbered(): void
    {
        $this->receive('10', '10.00');
        $this->receive('10', '20.00');

        $wz = $this->draft(DocumentType::WZ, ['contractor_id' => $this->contractor->id]);
        $this->documents->addProduct($wz, $this->product, '4');
        $wz = $this->documents->approve($wz, $this->user);

        $this->assertSame(DocumentStatus::Approved, $wz->status);
        $this->assertMatchesRegularExpression('#^WZ/\d{4}/\d{2}/0001$#', $wz->number);
        $this->assertSame('60.00', $wz->total_value);
        $this->assertSame('16.000', $this->stock($this->main)->quantity);
        $this->assertSame('15.0000', $this->stock($this->main)->averagePrice());
    }

    public function test_issue_beyond_stock_is_rejected_atomically(): void
    {
        $this->receive('5', '10.00');

        $rw = $this->draft(DocumentType::RW);
        $this->documents->addProduct($rw, $this->product, '6');

        try {
            $this->documents->approve($rw, $this->user);
            $this->fail('Oczekiwano wyjątku o niewystarczającym stanie.');
        } catch (WarehouseException $e) {
            $this->assertStringContainsString('Niewystarczający stan', $e->getMessage());
        }

        $this->assertTrue($rw->fresh()->isDraft());
        $this->assertNull($rw->fresh()->number);
        $this->assertSame('5.000', $this->stock($this->main)->quantity);
        $this->assertSame(1, $this->product->movements()->count());
    }

    public function test_transfer_moves_stock_at_cost(): void
    {
        $this->receive('10', '12.50');

        $mm = $this->draft(DocumentType::MM, ['target_warehouse_id' => $this->second->id]);
        $this->documents->addProduct($mm, $this->product, '4');
        $this->documents->approve($mm, $this->user);

        $this->assertSame('6.000', $this->stock($this->main)->quantity);
        $this->assertSame('4.000', $this->stock($this->second)->quantity);
        $this->assertSame('12.5000', $this->stock($this->second)->averagePrice());
    }

    public function test_transfer_requires_different_target_warehouse(): void
    {
        $mm = $this->draft(DocumentType::MM, ['target_warehouse_id' => $this->main->id]);
        $this->documents->addProduct($mm, $this->product, '1');

        $this->expectException(WarehouseException::class);
        $this->documents->approve($mm, $this->user);
    }

    public function test_inventory_generates_pw_for_surplus_and_rw_for_shortage(): void
    {
        $other = Product::factory()->create(['default_price' => 5]);
        $uncounted = Product::factory()->create();
        $this->receive('10', '10.00');
        $this->receive('8', '5.00', $other);
        $this->receive('3', '1.00', $uncounted);

        $inw = $this->draft(DocumentType::INW, ['prefill' => true]);
        $this->assertSame(3, $inw->items()->count());

        $inw->items()->where('product_id', $this->product->id)->update(['quantity' => 12]); // nadwyżka +2
        $inw->items()->where('product_id', $other->id)->update(['quantity' => 5]);         // niedobór −3

        $inw = $this->documents->approve($inw, $this->user);

        $this->assertSame('12.000', $this->stock($this->main)->quantity);
        $this->assertSame('5.000', $this->stock($this->main, $other)->quantity);
        $this->assertSame('3.000', $this->stock($this->main, $uncounted)->quantity); // niepoliczone – bez zmian

        $children = $inw->children->keyBy(fn (Document $d) => $d->type->value);
        $this->assertTrue($children['PW']->isApproved());
        $this->assertTrue($children['RW']->isApproved());
        $this->assertSame('5.00', $inw->total_value); // +20 (2 × 10) − 15 (3 × 5)
    }

    public function test_cancelling_receipt_reverses_stock(): void
    {
        $pz = $this->receive('10', '10.00');

        $this->documents->cancel($pz, $this->user);

        $this->assertSame(DocumentStatus::Cancelled, $pz->fresh()->status);
        $this->assertSame('0.000', $this->stock($this->main)->quantity);
        $this->assertSame('0.0000', $this->stock($this->main)->value);
    }

    public function test_cannot_cancel_receipt_when_goods_were_already_issued(): void
    {
        $pz = $this->receive('10', '10.00');

        $wz = $this->draft(DocumentType::WZ, ['contractor_id' => $this->contractor->id]);
        $this->documents->addProduct($wz, $this->product, '8');
        $this->documents->approve($wz, $this->user);

        $this->expectException(WarehouseException::class);
        $this->documents->cancel($pz, $this->user);
    }

    public function test_cancelling_issue_restores_stock_at_original_price(): void
    {
        $this->receive('10', '10.00');
        $wz = $this->draft(DocumentType::WZ, ['contractor_id' => $this->contractor->id]);
        $this->documents->addProduct($wz, $this->product, '4');
        $wz = $this->documents->approve($wz, $this->user);

        $this->documents->cancel($wz, $this->user);

        $this->assertSame('10.000', $this->stock($this->main)->quantity);
        $this->assertSame('100.0000', $this->stock($this->main)->value);
    }

    public function test_scanning_same_product_twice_increments_quantity(): void
    {
        $pz = $this->draft(DocumentType::PZ, ['contractor_id' => $this->contractor->id]);

        $this->documents->addProduct($pz, $this->product, '1');
        $this->documents->addProduct($pz, $this->product, '2');

        $this->assertSame(1, $pz->items()->count());
        $this->assertSame('3.000', $pz->items()->first()->quantity);
    }

    public function test_approved_document_cannot_be_edited(): void
    {
        $pz = $this->receive('1', '1.00');

        $this->expectException(WarehouseException::class);
        $this->documents->addProduct($pz, $this->product, '1');
    }

    // ─── pomocnicze ───

    private function draft(DocumentType $type, array $data = []): Document
    {
        return $this->documents->createDraft($type, $data + ['warehouse_id' => $this->main->id], $this->user);
    }

    private function receive(string $quantity, string $price, ?Product $product = null): Document
    {
        $pz = $this->draft(DocumentType::PZ, ['contractor_id' => $this->contractor->id]);
        $this->documents->addProduct($pz, $product ?? $this->product, $quantity, $price);

        return $this->documents->approve($pz, $this->user);
    }

    private function stock(Warehouse $warehouse, ?Product $product = null): Stock
    {
        return Stock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', ($product ?? $this->product)->id)
            ->firstOrFail();
    }
}
