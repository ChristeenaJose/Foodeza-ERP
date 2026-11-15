<?php

declare(strict_types=1);

return [
    'name' => 'Foodeza Bookkeeping',
    'company' => 'Foodeza',
    'currency' => 'EUR',
    'currency_symbol' => '€',
    'timezone' => 'Europe/Amsterdam',
    'database' => __DIR__ . '/../database/bookkeeping.sqlite',
    'storage' => __DIR__ . '/../storage',
    'vat_rate' => 0.21,
    'invoice_prefix' => 'FZ',
    'services' => [
        'Catering',
        'Food Truck',
        'Corporate Lunch',
        'Meal Box Delivery',
    ],
    'expense_categories' => [
        'Ingredients',
        'Packaging',
        'Fuel',
        'Gas',
        'Equipment',
        'Marketing',
        'Insurance',
        'Rent',
    ],
    'payment_methods' => [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'card' => 'Card',
        'sumup' => 'SumUp',
        'stripe' => 'Stripe',
    ],
];
