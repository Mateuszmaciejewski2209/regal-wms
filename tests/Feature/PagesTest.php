<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Product;
use App\Models\Rack;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test dymny: każda strona panelu renderuje się na danych demonstracyjnych.
 */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Zaloguj się');
    }

    public function test_admin_sees_every_page(): void
    {
        $this->actingAs(User::where('email', 'admin@regal.test')->first());

        $inventory = Document::where('type', 'INW')->first();
        $draft = Document::where('status', 'draft')->first();
        $product = Product::first();

        $pages = [
            route('dashboard'),
            route('documents.index'),
            route('documents.index', ['type' => 'WZ', 'status' => 'approved', 'q' => 'Budomax']),
            route('documents.show', $inventory),
            route('documents.show', $draft),
            route('documents.show', $inventory->children()->first()),
            route('stock.index'),
            route('stock.index', ['low' => 1]),
            route('products.index'),
            route('products.show', $product),
            route('products.edit', $product),
            route('products.create'),
            route('contractors.index'),
            route('warehouses.index'),
            route('scanner.devices'),
            route('webhooks.index'),
            route('users.index'),
            route('roles.index'),
            route('layout.plan'),
            route('layout.plan', ['magazyn' => 1, 'regal' => Rack::value('id'), 'znajdz' => 'TAS']),
            route('labels.locations', Rack::value('id')),
            route('stock.index', ['zero' => 1, 'warehouse' => 1]),
            route('products.index', ['ile' => 10, 'page' => 2]),
            route('labels', ['ids' => [$product->id]]),
            route('scanner.app'),
            route('scanner.manifest'),
            route('scanner.sw'),
        ];

        foreach (['PZ', 'WZ', 'PW', 'RW', 'MM', 'INW'] as $type) {
            $pages[] = route('documents.create', $type);
        }

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_pdf_and_csv_exports(): void
    {
        $this->actingAs(User::where('email', 'kierownik@regal.test')->first());

        $this->get(route('documents.pdf', Document::whereNotNull('number')->first()))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('stock.export'))->assertOk()->assertDownload();
    }

    public function test_storekeeper_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::where('email', 'magazynier@regal.test')->first());

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('webhooks.index'))->assertForbidden();
        $this->get(route('products.create'))->assertForbidden();
    }
}
