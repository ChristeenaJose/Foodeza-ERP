<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Supplier;

class ContactService
{
    public function findOrCreateCustomer(?string $name, ?int $id = null, array $details = []): ?int
    {
        $customer = $id ? Customer::find($id) : null;

        if (! $customer && $name) {
            $customer = Customer::firstOrCreate(['name' => $name]);
        }

        if ($customer) {
            $updates = array_filter([
                'email' => $details['email'] ?? null,
                'phone' => $details['phone'] ?? null,
                'address' => $details['address'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            if ($updates) {
                $customer->fill($updates)->save();
            }

            return $customer->id;
        }

        return null;
    }

    public function findOrCreateSupplier(?string $name, ?int $id = null): ?int
    {
        if ($id) {
            return $id;
        }
        if (! $name) {
            return null;
        }

        return Supplier::firstOrCreate(['name' => $name])->id;
    }
}
