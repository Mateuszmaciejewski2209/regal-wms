<?php

namespace Tests\Feature;

use App\Enums\Ability;
use App\Enums\DocumentType;
use App\Livewire\Roles\Index as RolesIndex;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_roles_form_a_hierarchy(): void
    {
        $this->assertSame(['admin', 'manager', 'storekeeper', 'viewer'], Role::orderByDesc('level')->pluck('slug')->all());
        $this->assertTrue(Role::where('slug', 'admin')->first()->allows(Ability::RolesManage));
        $this->assertFalse(Role::where('slug', 'storekeeper')->first()->allows(Ability::InventoryApprove));
    }

    public function test_manager_manages_only_lower_accounts(): void
    {
        $manager = User::factory()->manager()->create();
        $admin = User::factory()->admin()->create();
        $storekeeper = User::factory()->create();

        $this->assertTrue($manager->can('manage-user', $storekeeper));
        $this->assertFalse($manager->can('manage-user', $admin));
        $this->assertFalse($manager->canAssignRole(Role::where('slug', 'manager')->first()));
        $this->assertTrue($manager->canAssignRole(Role::where('slug', 'storekeeper')->first()));
    }

    public function test_manager_cannot_promote_user_above_own_level(): void
    {
        $manager = User::factory()->manager()->create();
        $storekeeper = User::factory()->create();

        Livewire::actingAs($manager)->test(UsersIndex::class)
            ->call('edit', $storekeeper->id)
            ->set('form.role_id', Role::where('slug', 'admin')->value('id'))
            ->call('save')
            ->assertHasErrors('form.role_id');

        $this->assertSame('storekeeper', $storekeeper->fresh()->role->slug);
    }

    public function test_role_cannot_grant_abilities_creator_does_not_have(): void
    {
        // Rola pośrednia z prawem do zarządzania rolami, ale bez webhooków.
        $role = Role::create(['slug' => 'lead', 'name' => 'Lider zmiany', 'level' => 40]);
        $role->syncAbilities([Ability::RolesManage->value, Ability::DocumentsView->value]);
        $lead = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($lead)->test(RolesIndex::class)
            ->call('create')
            ->set('form.name', 'Praktykant')
            ->set('form.level', 5)
            ->set('form.abilities', [Ability::WebhooksManage->value])
            ->call('save')
            ->assertHasErrors('form.abilities.0');

        Livewire::actingAs($lead)->test(RolesIndex::class)
            ->call('create')
            ->set('form.name', 'Praktykant')
            ->set('form.level', 45)
            ->set('form.abilities', [Ability::DocumentsView->value])
            ->call('save')
            ->assertHasErrors('form.level');
    }

    public function test_routes_respect_abilities(): void
    {
        $viewer = User::factory()->create(['role_id' => Role::where('slug', 'viewer')->value('id')]);

        $this->actingAs($viewer)->get(route('stock.index'))->assertOk();
        $this->actingAs($viewer)->get(route('documents.create', 'PZ'))->assertForbidden();
        $this->actingAs($viewer)->get(route('scanner.devices'))->assertForbidden();
        $this->actingAs($viewer)->get(route('roles.index'))->assertForbidden();
    }

    public function test_storekeeper_cannot_approve_inventory(): void
    {
        $storekeeper = User::factory()->create();
        $inventory = Document::create([
            'type' => DocumentType::INW, 'status' => 'draft', 'warehouse_id' => Warehouse::factory()->create()->id,
            'issued_at' => now(), 'created_by' => $storekeeper->id,
        ]);

        $this->assertFalse($storekeeper->can('approve-document', $inventory));
        $this->assertTrue(User::factory()->manager()->create()->can('approve-document', $inventory));
    }
}
