<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Allergen;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuEntry;
use App\Models\MenuLocation;
use App\Models\MenuSection;
use App\Models\MenuServiceType;
use App\Models\Package;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuManagementSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];
        foreach ([
            'Kerala Curries',
            'Biriyani & Rice',
            'Kerala Specials',
            'Starters',
            'Snacks',
            'Sides',
            'Desserts',
            'Drinks',
        ] as $i => $name) {
            $categories[$name] = MenuCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i, 'active' => true, 'archived_at' => null]
            );
        }

        $services = [];
        foreach ([
            'Lunch',
            'Food Truck',
            'Market',
            'Catering',
            'Private Event',
            'Party',
            'Online Order',
        ] as $i => $name) {
            $services[$name] = MenuServiceType::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i, 'active' => true]
            );
        }

        foreach ([
            'Karlsruhe',
            'Stuttgart',
            'Durlach',
            'Feuersee',
            'Customer Event',
            'Catering Kitchen',
        ] as $name) {
            MenuLocation::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'active' => true]
            );
        }

        $allergens = [];
        foreach ([
            'Gluten' => 'GLUTEN',
            'Milk' => 'MILK',
            'Egg' => 'EGG',
            'Soy' => 'SOY',
            'Nuts' => 'NUTS',
            'Peanuts' => 'PEANUTS',
            'Fish' => 'FISH',
            'Crustaceans' => 'CRUSTACEANS',
            'Mustard' => 'MUSTARD',
            'Celery' => 'CELERY',
            'Sesame' => 'SESAME',
            'Sulphites' => 'SULPHITES',
        ] as $name => $code) {
            $allergens[$code] = Allergen::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'active' => true]
            );
        }

        $productDefs = [
            ['Butter Chicken', 'Kerala Curries', 10.50, 'non_vegetarian', false, false, true],
            ['Kerala Chicken Curry', 'Kerala Curries', 8.50, 'non_vegetarian', false, false, true],
            ['Paneer Butter Masala', 'Kerala Curries', 9.50, 'vegetarian', true, false, true],
            ['Dal Spinach', 'Kerala Curries', 7.50, 'vegan', true, true, true],
            ['Chicken Biriyani', 'Biriyani & Rice', 12.00, 'non_vegetarian', false, false, true],
            ['Beef Biriyani', 'Biriyani & Rice', 14.00, 'non_vegetarian', false, false, false],
            ['Prawn Biriyani', 'Biriyani & Rice', 15.00, 'non_vegetarian', false, false, false],
            ['Chicken 65', 'Snacks', 5.00, 'non_vegetarian', false, false, true],
            ['Spicy Pork Fry', 'Snacks', 6.00, 'non_vegetarian', false, false, false],
            ['Chicken Cutlet', 'Starters', 2.00, 'non_vegetarian', false, false, true],
            ['Beef Cutlet', 'Starters', 3.00, 'non_vegetarian', false, false, false],
            ['Pazhampori', 'Snacks', 2.50, 'vegetarian', true, false, true],
            ['Kappa', 'Kerala Specials', 5.00, 'vegan', true, true, true],
            ['Fish Curry', 'Kerala Curries', 9.00, 'non_vegetarian', false, false, false],
            ['Appam', 'Sides', 1.50, 'vegetarian', true, false, true],
            ['Beef Ishtu', 'Kerala Curries', 9.00, 'non_vegetarian', false, false, false],
            ['Kerala Chicken Ishtu', 'Kerala Curries', 8.50, 'non_vegetarian', false, false, true],
            ['Parotta', 'Sides', 1.50, 'vegetarian', true, false, true],
            ['Fruit Salad with Ice Cream', 'Desserts', 2.00, 'vegetarian', true, false, true],
            ['Semiya Payasam', 'Desserts', 2.00, 'vegetarian', true, false, true],
            ['Mango Lassi', 'Drinks', 3.50, 'vegetarian', true, false, true],
            ['Salad', 'Sides', 1.00, 'vegan', true, true, true],
            ['Chammanthi', 'Sides', 0.50, 'vegan', true, true, true],
            ['Pickles', 'Sides', 0.50, 'vegan', true, true, true],
            ['Fried Rice with Chilli Chicken', 'Biriyani & Rice', 12.00, 'non_vegetarian', false, false, true],
        ];

        $products = [];
        foreach ($productDefs as [$name, $cat, $price, $foodType, $veg, $vegan, $halal]) {
            $sku = strtoupper(Str::slug($name, '-'));
            $product = Product::withTrashed()->updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'short_name' => $name,
                    'category_id' => $categories[$cat]->id,
                    'food_type' => $foodType,
                    'is_vegetarian' => $veg,
                    'is_vegan' => $vegan,
                    'is_halal' => $halal,
                    'active' => true,
                    'default_price' => $price,
                    'vat_rate' => 0.07,
                    'availability_status' => 'available',
                    'deleted_at' => null,
                ]
            );
            $products[$name] = $product;

            if ($product->prices()->where('price_type', 'standard')->doesntExist()) {
                ProductPrice::create([
                    'product_id' => $product->id,
                    'price_type' => 'standard',
                    'price' => $price,
                    'currency' => 'EUR',
                ]);
            }
        }

        // Service-specific biriyani prices
        $biryani = $products['Chicken Biriyani'];
        foreach ([
            ['Lunch', 12.00],
            ['Market', 14.00],
            ['Catering', 15.00],
            ['Private Event', 15.00],
        ] as [$svc, $price]) {
            ProductPrice::firstOrCreate(
                [
                    'product_id' => $biryani->id,
                    'price_type' => $svc === 'Catering' || $svc === 'Private Event' ? 'catering_per_person' : 'service',
                    'service_type_id' => $services[$svc]->id,
                ],
                ['price' => $price, 'currency' => 'EUR']
            );
        }

        // Variants for Chicken Biriyani
        if ($biryani->variants()->count() === 0) {
            foreach ([['Small', 10], ['Regular', 12], ['Large', 14], ['Catering Portion', 15]] as $i => [$vname, $vprice]) {
                $biryani->variants()->create([
                    'name' => $vname,
                    'sku' => 'CHICKEN-BIRIYANI-'.Str::slug($vname),
                    'price' => $vprice,
                    'sort_order' => $i,
                    'available' => true,
                ]);
            }
        }

        // Add-ons
        $addonDefs = [
            ['Extra Chicken', 3.00],
            ['Extra Rice', 2.00],
            ['Parotta', 1.50],
            ['Salad', 1.00],
            ['Pickle', 0.50],
            ['Chammanthi', 0.50],
            ['Extra Sauce', 0.50],
        ];
        foreach ($addonDefs as [$name, $price]) {
            $linked = $products[$name] ?? null;
            Addon::updateOrCreate(
                ['name' => $name],
                [
                    'price' => $price,
                    'product_id' => $linked?->id,
                    'active' => true,
                ]
            );
        }

        $riceAddon = Addon::where('name', 'Extra Rice')->first();
        $parottaAddon = Addon::where('name', 'Parotta')->first();
        $extraChicken = Addon::where('name', 'Extra Chicken')->first();
        if ($riceAddon && $parottaAddon && $extraChicken) {
            $products['Butter Chicken']->addons()->syncWithoutDetaching([
                $riceAddon->id, $parottaAddon->id, $extraChicken->id,
            ]);
        }

        // Lunch Menu
        $lunch = Menu::updateOrCreate(
            ['name' => 'Lunch Menu'],
            [
                'description' => 'Daily lunch service',
                'menu_type' => 'lunch',
                'status' => 'active',
                'available_days' => [2, 3, 4], // Tue–Thu
                'available_time_start' => '11:30',
                'available_time_end' => '14:00',
                'currency' => 'EUR',
            ]
        );
        $lunch->serviceTypes()->sync([$services['Lunch']->id]);

        $this->buildSections($lunch, [
            'Kerala Curries' => ['Butter Chicken', 'Kerala Chicken Curry', 'Paneer Butter Masala', 'Dal Spinach'],
            'Biriyani & Rice' => ['Chicken Biriyani'],
            'Sides' => ['Chicken 65', 'Spicy Pork Fry'],
        ], $categories, $products);

        // Catering Menu
        $catering = Menu::updateOrCreate(
            ['name' => 'Catering Menu'],
            [
                'description' => 'Per-person catering offerings',
                'menu_type' => 'catering',
                'status' => 'active',
                'currency' => 'EUR',
            ]
        );
        $catering->serviceTypes()->sync([$services['Catering']->id, $services['Private Event']->id]);

        $this->buildSections($catering, [
            'Starters' => ['Chicken Cutlet', 'Beef Cutlet', 'Kappa', 'Appam'],
            'Main Course' => [
                'Chicken Biriyani', 'Beef Biriyani', 'Prawn Biriyani',
                'Fried Rice with Chilli Chicken', 'Kerala Chicken Ishtu', 'Kerala Chicken Curry',
            ],
            'Sides' => ['Chicken 65', 'Spicy Pork Fry', 'Salad', 'Pickles', 'Chammanthi'],
            'Desserts' => ['Fruit Salad with Ice Cream', 'Semiya Payasam'],
        ], $categories, $products);

        // Market / Food Truck menu
        $market = Menu::updateOrCreate(
            ['name' => 'Market Menu'],
            [
                'description' => 'Food truck and market sales',
                'menu_type' => 'food_truck',
                'status' => 'active',
                'available_days' => [5], // Friday
                'available_time_start' => '11:30',
                'available_time_end' => '18:00',
                'currency' => 'EUR',
            ]
        );
        $market->serviceTypes()->sync([$services['Food Truck']->id, $services['Market']->id]);
        $durlach = MenuLocation::where('slug', 'durlach')->first();
        if ($durlach) {
            $market->locations()->sync([$durlach->id]);
        }
        $this->buildSections($market, [
            'Snacks' => ['Chicken 65', 'Chicken Cutlet', 'Pazhampori'],
            'Biriyani & Rice' => ['Chicken Biriyani'],
            'Drinks' => ['Mango Lassi'],
        ], $categories, $products);

        // Packages
        $birthday = Package::updateOrCreate(
            ['name' => 'Birthday Snack Package'],
            [
                'description' => 'Snack platter for parties',
                'people_count' => 1,
                'price_per_person' => 12.00,
                'status' => 'active',
            ]
        );
        $birthday->serviceTypes()->sync([$services['Party']->id, $services['Private Event']->id]);
        $birthday->items()->delete();
        foreach ([
            ['Chicken Cutlet', 1],
            ['Pazhampori', 1],
            ['Chicken 65', 2],
            ['Salad', 1],
        ] as $i => [$pname, $qty]) {
            $birthday->items()->create([
                'product_id' => $products[$pname]->id,
                'quantity' => $qty,
                'sort_order' => $i,
            ]);
        }

        $dinner = Package::updateOrCreate(
            ['name' => 'Kerala Dinner Package'],
            [
                'description' => 'Appam, curry, salad and dessert',
                'people_count' => 1,
                'price_per_person' => 15.00,
                'status' => 'active',
            ]
        );
        $dinner->serviceTypes()->sync([$services['Catering']->id, $services['Private Event']->id]);
        $dinner->items()->delete();
        foreach ([
            ['Appam', 2],
            ['Kerala Chicken Curry', 1],
            ['Salad', 1],
            ['Semiya Payasam', 1],
        ] as $i => [$pname, $qty]) {
            $dinner->items()->create([
                'product_id' => $products[$pname]->id,
                'quantity' => $qty,
                'sort_order' => $i,
            ]);
        }
    }

    private function buildSections(Menu $menu, array $map, array $categories, array $products): void
    {
        $menu->entries()->delete();
        $menu->sections()->delete();

        $si = 0;
        foreach ($map as $sectionName => $productNames) {
            $section = MenuSection::create([
                'menu_id' => $menu->id,
                'category_id' => $categories[$sectionName]->id ?? null,
                'name' => $sectionName,
                'sort_order' => $si++,
            ]);
            foreach ($productNames as $ei => $pname) {
                if (! isset($products[$pname])) {
                    continue;
                }
                MenuEntry::create([
                    'menu_id' => $menu->id,
                    'menu_section_id' => $section->id,
                    'product_id' => $products[$pname]->id,
                    'price' => $products[$pname]->default_price,
                    'sort_order' => $ei,
                    'available' => true,
                ]);
            }
        }
    }
}
