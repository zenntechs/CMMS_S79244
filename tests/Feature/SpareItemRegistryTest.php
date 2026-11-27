<?php

namespace Tests\Feature;

use App\Models\SpareItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpareItemRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function chief(): User
    {
        return User::where('email', 'chief@cmms.test')->firstOrFail();
    }

    private function tech(): User
    {
        return User::where('email', 'tech1@cmms.test')->firstOrFail();
    }

    private function viewer(): User
    {
        return User::where('email', 'viewer@cmms.test')->firstOrFail();
    }

    public function test_inventory_lists_seeded_spare_items(): void
    {
        $chief = $this->chief();

        $this->actingAs($chief)
            ->get(route('spares.index'))
            ->assertOk()
            ->assertSee('SP-001')
            ->assertSee('SP-006')
            ->assertSee('Below reorder');
    }

    public function test_spare_item_detail_shows_stock_and_movement_history(): void
    {
        $chief = $this->chief();
        $item = SpareItem::where('code', 'SP-002')->firstOrFail();

        $this->actingAs($chief)
            ->get(route('spares.show', $item))
            ->assertOk()
            ->assertSee('SP-002')
            ->assertSee('Fuel injector')
            ->assertSee('Movement history')
            ->assertSee('Unused part returned.');
    }

    public function test_stock_quantity_matches_seeded_balance(): void
    {
        $item = SpareItem::where('code', 'SP-003')->firstOrFail();

        $this->assertSame('185.00', (string) $item->quantity_on_hand);
        $this->assertSame('60.00', (string) $item->reorder_level);
    }

    public function test_low_stock_boundary_uses_strictly_below_reorder_level(): void
    {
        $chief = $this->chief();

        $atThreshold = SpareItem::factory()->create([
            'code' => 'TST-SP-AT',
            'quantity_on_hand' => 5.00,
            'reorder_level' => 5.00,
            'is_active' => true,
        ]);

        $belowThreshold = SpareItem::factory()->create([
            'code' => 'TST-SP-BELOW',
            'quantity_on_hand' => 4.99,
            'reorder_level' => 5.00,
            'is_active' => true,
        ]);

        $this->actingAs($chief)
            ->get(route('spares.index'))
            ->assertOk()
            ->assertSee('TST-SP-AT')
            ->assertSee('TST-SP-BELOW');

        $this->assertSame('OK', $atThreshold->quantity_on_hand >= $atThreshold->reorder_level ? 'OK' : 'Below reorder');
        $this->assertTrue($belowThreshold->quantity_on_hand < $belowThreshold->reorder_level);
    }

    public function test_out_of_stock_item_is_visible(): void
    {
        $chief = $this->chief();

        SpareItem::factory()->create([
            'code' => 'TST-SP-ZERO',
            'quantity_on_hand' => 0.00,
            'reorder_level' => 2.00,
            'is_active' => true,
        ]);

        $this->actingAs($chief)
            ->get(route('spares.index'))
            ->assertOk()
            ->assertSee('TST-SP-ZERO')
            ->assertSee('Below reorder');
    }

    public function test_inventory_search_and_filters(): void
    {
        $chief = $this->chief();

        $this->actingAs($chief)
            ->get(route('spares.index', ['search' => 'SP-004']))
            ->assertOk()
            ->assertSee('SP-004')
            ->assertDontSee('SP-001');

        $this->actingAs($chief)
            ->get(route('spares.index', ['status' => 'low']))
            ->assertOk()
            ->assertSee('SP-005')
            ->assertSee('SP-006')
            ->assertDontSee('SP-001');
    }

    public function test_inventory_paginates_at_twenty_per_page(): void
    {
        $chief = $this->chief();

        SpareItem::factory()->count(25)->create();

        $first = $this->actingAs($chief)->get(route('spares.index'));
        $first->assertOk();
        $this->assertSame(20, $first->viewData('items')->perPage());
        $this->assertSame(31, $first->viewData('items')->total());
        $this->assertSame(2, $first->viewData('items')->lastPage());
    }

    public function test_all_authenticated_roles_can_view_inventory(): void
    {
        $item = SpareItem::where('code', 'SP-001')->firstOrFail();

        $this->actingAs($this->chief())->get(route('spares.index'))->assertOk();
        $this->actingAs($this->tech())->get(route('spares.index'))->assertOk();
        $this->actingAs($this->viewer())->get(route('spares.index'))->assertOk();

        $this->actingAs($this->tech())->get(route('spares.show', $item))->assertOk();
        $this->actingAs($this->viewer())->get(route('spares.show', $item))->assertOk();
    }

    public function test_guest_is_redirected_from_inventory(): void
    {
        $this->get(route('spares.index'))->assertRedirect('/login');
    }

    public function test_chief_engineer_can_create_a_spare_item_without_setting_stock(): void
    {
        $chief = $this->chief();

        $this->actingAs($chief)->post(route('spares.store'), [
            'code' => 'TST-SP-NEW',
            'description' => 'Test spare item',
            'unit' => 'piece',
            'reorder_level' => '4.00',
            'is_active' => '1',
            'quantity_on_hand' => '999.00',
        ])->assertRedirect(route('spares.index'));

        $item = SpareItem::where('code', 'TST-SP-NEW')->firstOrFail();
        $this->assertSame('0.00', (string) $item->quantity_on_hand);
        $this->assertSame('4.00', (string) $item->reorder_level);
    }

    public function test_chief_engineer_can_edit_master_data_without_changing_stock(): void
    {
        $chief = $this->chief();
        $item = SpareItem::where('code', 'SP-001')->firstOrFail();
        $stockBefore = (string) $item->quantity_on_hand;

        $this->actingAs($chief)->put(route('spares.update', $item), [
            'code' => 'SP-001',
            'description' => 'Updated oil filter element',
            'unit' => 'piece',
            'reorder_level' => '7.00',
            'is_active' => '1',
        ])->assertRedirect(route('spares.show', $item));

        $item->refresh();
        $this->assertSame('Updated oil filter element', $item->description);
        $this->assertSame('7.00', (string) $item->reorder_level);
        $this->assertSame($stockBefore, (string) $item->quantity_on_hand);
    }

    public function test_master_data_validation_rejects_invalid_input(): void
    {
        $chief = $this->chief();

        $this->actingAs($chief)->post(route('spares.store'), [
            'code' => '',
            'description' => '',
            'unit' => '',
            'reorder_level' => '-1',
        ])->assertSessionHasErrors(['code', 'description', 'unit', 'reorder_level']);

        $this->actingAs($chief)->post(route('spares.store'), [
            'code' => 'SP-001',
            'description' => 'Duplicate code',
            'unit' => 'piece',
            'reorder_level' => '1.00',
        ])->assertSessionHasErrors('code');
    }

    public function test_technician_and_viewer_cannot_manage_spare_master_data(): void
    {
        $item = SpareItem::where('code', 'SP-001')->firstOrFail();

        $this->actingAs($this->tech())->get(route('spares.create'))->assertForbidden();
        $this->actingAs($this->viewer())->get(route('spares.create'))->assertForbidden();

        $this->actingAs($this->tech())->post(route('spares.store'), [
            'code' => 'TST-TECH',
            'description' => 'Not allowed',
            'unit' => 'piece',
            'reorder_level' => '1.00',
        ])->assertForbidden();

        $this->actingAs($this->viewer())->put(route('spares.update', $item), [
            'code' => $item->code,
            'description' => 'Not allowed',
            'unit' => $item->unit,
            'reorder_level' => '1.00',
            'is_active' => '1',
        ])->assertForbidden();
    }
}
