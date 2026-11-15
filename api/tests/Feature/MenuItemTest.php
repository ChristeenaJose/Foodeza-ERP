<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_returns_categories_and_services(): void
    {
        $this->getJson('/api/menu/meta')
            ->assertOk()
            ->assertJsonPath('categories.0', 'Starter')
            ->assertJsonPath('services.0', 'Catering');
    }

    public function test_menu_item_requires_name_and_service(): void
    {
        $this->postJson('/api/menu', [
            'price' => 10,
        ])->assertStatus(422);
    }

    public function test_menu_item_can_be_created_with_recipe(): void
    {
        $rice = InventoryItem::create([
            'name' => 'Basmati rice',
            'category' => 'Ingredients',
            'unit' => 'kg',
            'quantity' => 10,
            'unit_cost' => 2.5,
        ]);

        $this->postJson('/api/menu', [
            'name' => 'Chicken biryani',
            'category' => 'Kerala Special',
            'service_types' => ['Catering', 'Food Truck'],
            'price' => 14.5,
            'allergens' => 'gluten',
            'ingredients' => [
                ['inventory_item_id' => $rice->id, 'quantity' => 0.2],
            ],
        ])->assertCreated()
            ->assertJsonPath('name', 'Chicken biryani')
            ->assertJsonPath('cost', 0.5)
            ->assertJsonPath('ingredients.0.inventory_item.name', 'Basmati rice');
    }

    public function test_menu_can_be_filtered_by_service(): void
    {
        MenuItem::create([
            'name' => 'Meal box A',
            'category' => 'Package',
            'service_types' => ['Meal Box Delivery'],
            'price' => 9.5,
            'available' => true,
        ]);
        MenuItem::create([
            'name' => 'Wedding platter',
            'category' => 'Package',
            'service_types' => ['Catering'],
            'price' => 22,
            'available' => true,
        ]);

        $this->getJson('/api/menu?service_type=Catering')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Wedding platter');
    }

    public function test_menu_item_can_be_updated_and_deleted(): void
    {
        $item = MenuItem::create([
            'name' => 'Samosa',
            'category' => 'Starter',
            'service_types' => ['Food Truck'],
            'price' => 2.5,
            'available' => true,
        ]);

        $this->putJson("/api/menu/{$item->id}", [
            'name' => 'Veg samosa',
            'category' => 'Starter',
            'service_types' => ['Food Truck', 'Catering'],
            'price' => 3,
            'available' => false,
            'ingredients' => [],
        ])->assertOk()
            ->assertJsonPath('name', 'Veg samosa')
            ->assertJsonPath('available', false);

        $this->deleteJson("/api/menu/{$item->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
