<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_counts(): void
    {
        $this->seed(\Database\Seeders\MenuManagementSeeder::class);

        $this->getJson('/api/menu-mgmt/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'active_products',
                'active_menus',
                'draft_menus',
                'active_packages',
                'recent_menus',
            ]);
    }

    public function test_product_is_reusable_across_menus_without_duplication(): void
    {
        $this->seed(\Database\Seeders\MenuManagementSeeder::class);

        $product = Product::where('name', 'Chicken Biriyani')->firstOrFail();
        $before = Product::where('name', 'Chicken Biriyani')->count();

        $menu = Menu::create([
            'name' => 'Test Online Menu',
            'menu_type' => 'online',
            'status' => 'draft',
        ]);

        $this->putJson("/api/menu-mgmt/menus/{$menu->id}/builder", [
            'sections' => [
                [
                    'name' => 'Biriyani & Rice',
                    'entries' => [
                        ['product_id' => $product->id, 'price' => 13.5],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertSame(1, Product::where('name', 'Chicken Biriyani')->count());
        $this->assertSame($before, Product::where('name', 'Chicken Biriyani')->count());
        $this->assertDatabaseHas('menu_entries', [
            'menu_id' => $menu->id,
            'product_id' => $product->id,
            'price' => 13.5,
        ]);
    }

    public function test_category_can_be_created_and_archived(): void
    {
        $this->postJson('/api/menu-mgmt/categories', ['name' => 'Street Food'])
            ->assertCreated()
            ->assertJsonPath('name', 'Street Food');

        $cat = MenuCategory::where('slug', 'street-food')->firstOrFail();

        $this->deleteJson("/api/menu-mgmt/categories/{$cat->id}")
            ->assertOk();

        $this->assertNotNull($cat->fresh()->archived_at);
    }

    public function test_product_supports_service_specific_price_history(): void
    {
        $this->seed(\Database\Seeders\MenuManagementSeeder::class);
        $product = Product::where('name', 'Chicken Biriyani')->firstOrFail();
        $service = \App\Models\MenuServiceType::where('slug', 'lunch')->firstOrFail();

        $this->postJson("/api/menu-mgmt/products/{$product->id}/prices", [
            'price_type' => 'service',
            'service_type_id' => $service->id,
            'price' => 12.5,
        ])->assertCreated();

        $this->getJson('/api/menu-mgmt/pricing?product_id='.$product->id)
            ->assertOk()
            ->assertJsonFragment(['price' => '12.50']);
    }
}
