<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('foodeza.services') as $service) {
            Category::firstOrCreate(['name' => $service, 'type' => 'income']);
        }

        foreach (config('foodeza.expense_categories') as $name) {
            Category::firstOrCreate(['name' => $name, 'type' => 'expense']);
        }

        $insurance = Category::firstOrCreate(['name' => 'Insurance', 'type' => 'expense']);
        \App\Models\FixedExpense::updateOrCreate(
            ['name' => 'Foodtruck Insurance'],
            [
                'category_id' => $insurance->id,
                'amount' => 67.08,
                'section' => 'insurance',
                'day_of_month' => 1,
                'proof_type' => 'bank_statement',
                'notes' => 'Monthly food truck insurance',
                'active' => true,
            ]
        );

        $this->call(MenuManagementSeeder::class);
    }
}
