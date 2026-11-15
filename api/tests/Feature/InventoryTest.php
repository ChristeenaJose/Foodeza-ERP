<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_returns_categories_and_units(): void
    {
        $this->getJson('/api/inventory/meta')
            ->assertOk()
            ->assertJsonPath('categories.0', 'Ingredients')
            ->assertJsonPath('units.0', 'kg');
    }

    public function test_item_requires_name_category_and_unit(): void
    {
        $this->postJson('/api/inventory', [])->assertStatus(422);
    }

    public function test_item_can_be_created_with_opening_stock(): void
    {
        $this->postJson('/api/inventory', [
            'name' => 'Basmati rice',
            'sku' => 'RICE-1',
            'category' => 'Ingredients',
            'unit' => 'kg',
            'quantity' => 10,
            'reorder_level' => 4,
            'unit_cost' => 2.5,
            'supplier_name' => 'Asian Market',
        ])->assertCreated()
            ->assertJsonPath('name', 'Basmati rice')
            ->assertJsonPath('quantity', '10.000')
            ->assertJsonPath('low_stock', false);

        $this->getJson('/api/inventory/movements')
            ->assertOk()
            ->assertJsonPath('0.type', 'in')
            ->assertJsonPath('0.reason', 'Opening stock');
    }

    public function test_stock_can_be_received_and_used(): void
    {
        $item = InventoryItem::create([
            'name' => 'Chicken',
            'category' => 'Ingredients',
            'unit' => 'kg',
            'quantity' => 5,
            'reorder_level' => 2,
        ]);

        $this->postJson("/api/inventory/{$item->id}/adjust", [
            'date' => '2026-09-13',
            'type' => 'in',
            'quantity' => 3,
            'reason' => 'Purchase',
        ])->assertOk()
            ->assertJsonPath('item.quantity', '8.000');

        $this->postJson("/api/inventory/{$item->id}/adjust", [
            'date' => '2026-09-13',
            'type' => 'out',
            'quantity' => 1.5,
            'reason' => 'Catering job',
        ])->assertOk()
            ->assertJsonPath('item.quantity', '6.500');
    }

    public function test_cannot_use_more_stock_than_on_hand(): void
    {
        $item = InventoryItem::create([
            'name' => 'Naan flour',
            'category' => 'Ingredients',
            'unit' => 'kg',
            'quantity' => 1,
            'reorder_level' => 0,
        ]);

        $this->postJson("/api/inventory/{$item->id}/adjust", [
            'date' => '2026-09-13',
            'type' => 'out',
            'quantity' => 2,
        ])->assertStatus(422);
    }

    public function test_low_stock_filter(): void
    {
        InventoryItem::create([
            'name' => 'Oil',
            'category' => 'Ingredients',
            'unit' => 'L',
            'quantity' => 1,
            'reorder_level' => 3,
        ]);
        InventoryItem::create([
            'name' => 'Salt',
            'category' => 'Ingredients',
            'unit' => 'kg',
            'quantity' => 8,
            'reorder_level' => 2,
        ]);

        $this->getJson('/api/inventory?low_stock=1')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Oil');
    }

    public function test_cannot_delete_item_used_on_menu(): void
    {
        $item = InventoryItem::create([
            'name' => 'Yogurt',
            'category' => 'Ingredients',
            'unit' => 'kg',
            'quantity' => 2,
        ]);
        $dish = MenuItem::create([
            'name' => 'Raita',
            'category' => 'Side',
            'service_types' => ['Catering'],
            'price' => 3.5,
            'available' => true,
        ]);
        $dish->ingredients()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 0.1,
        ]);

        $this->deleteJson("/api/inventory/{$item->id}")->assertStatus(422);
    }
}
