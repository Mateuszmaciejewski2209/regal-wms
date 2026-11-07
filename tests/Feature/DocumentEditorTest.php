<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Livewire\Documents\Show;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Edytor szkicu: zmiany ilości i ceny wpisane w tabeli zapisują się w bazie. */
class DocumentEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_quantity_and_price_typed_in_table_are_saved(): void
    {
        $user = User::factory()->create();
        $pz = app(DocumentService::class)->createDraft(DocumentType::PZ, [
            'warehouse_id' => Warehouse::factory()->create()->id,
            'contractor_id' => Contractor::factory()->create()->id,
        ], $user);
        $item = app(DocumentService::class)->addProduct($pz, Product::factory()->create(['default_price' => 5.20]), '1');

        Livewire::actingAs($user)->test(Show::class, ['document' => $pz])
            ->set("quantities.{$item->id}", '5')
            ->set("prices.{$item->id}", '5,10')
            ->assertHasNoErrors()
            ->assertSee('25,50');

        $item->refresh();
        $this->assertSame('5.000', (string) $item->quantity);
        $this->assertSame('5.1000', (string) $item->unit_price);
    }

    public function test_unreadable_price_shows_error_instead_of_zero(): void
    {
        $user = User::factory()->create();
        $pz = app(DocumentService::class)->createDraft(DocumentType::PZ, [
            'warehouse_id' => Warehouse::factory()->create()->id,
            'contractor_id' => Contractor::factory()->create()->id,
        ], $user);
        $item = app(DocumentService::class)->addProduct($pz, Product::factory()->create(['default_price' => 9]), '1');

        Livewire::actingAs($user)->test(Show::class, ['document' => $pz])
            ->set("prices.{$item->id}", 'dwanaście')
            ->assertHasErrors("prices.{$item->id}");

        $this->assertSame('9.0000', (string) $item->fresh()->unit_price);
    }

    public function test_document_date_shows_time_only_when_it_matches_the_day(): void
    {
        $this->travelTo('2026-11-07 14:32:00');
        $user = User::factory()->create();
        $documents = app(DocumentService::class);
        $warehouse = Warehouse::factory()->create();

        $today = $documents->createDraft(DocumentType::PW, ['warehouse_id' => $warehouse->id], $user);
        $this->assertSame('07.11.2026 14:32', $today->fresh()->dateTimeLabel());

        // Dokument z datą wsteczną: nie doklejamy dzisiejszej godziny do cudzej daty.
        $backdated = $documents->createDraft(DocumentType::PW, ['warehouse_id' => $warehouse->id, 'issued_at' => '2026-11-03'], $user);
        $this->assertSame('03.11.2026', $backdated->fresh()->dateTimeLabel());
    }
}
