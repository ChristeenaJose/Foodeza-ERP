<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoogleFormOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleFormOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_returns_services_and_statuses(): void
    {
        $this->getJson('/api/google-form-orders/meta')
            ->assertOk()
            ->assertJsonPath('services.0', 'Catering')
            ->assertJsonPath('statuses.new', 'New');
    }

    public function test_order_requires_dates_service_and_items(): void
    {
        $this->postJson('/api/google-form-orders', [
            'status' => 'new',
        ])->assertStatus(422);
    }

    public function test_order_can_be_created_from_a_form_response(): void
    {
        $this->postJson('/api/google-form-orders', [
            'submitted_on' => '2026-11-15',
            'event_date' => '2026-11-20',
            'service_type' => 'Meal Box Delivery',
            'items' => '2x Kerala lunch box',
            'status' => 'new',
            'customer_name' => 'Anna Bakker',
            'customer_email' => 'anna@example.com',
            'customer_phone' => '+31 6 12345678',
            'guest_count' => 2,
            'location' => 'Amsterdam',
            'form_response_id' => 'response-1',
        ])->assertCreated()
            ->assertJsonPath('reference', 'GF-2026-0001')
            ->assertJsonPath('service_type', 'Meal Box Delivery')
            ->assertJsonPath('status', 'new')
            ->assertJsonPath('items', '2x Kerala lunch box')
            ->assertJsonPath('form_response_id', 'response-1')
            ->assertJsonPath('customer.name', 'Anna Bakker')
            ->assertJsonPath('customer.email', 'anna@example.com');
    }

    public function test_orders_can_be_filtered_and_updated(): void
    {
        $customer = Customer::create(['name' => 'Jobins']);

        $order = GoogleFormOrder::create([
            'reference' => 'GF-2026-0001',
            'customer_id' => $customer->id,
            'submitted_on' => '2026-11-10',
            'event_date' => '2026-11-15',
            'service_type' => 'Catering',
            'items' => 'Sadya for 20',
            'status' => 'new',
        ]);

        $this->getJson('/api/google-form-orders?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonCount(1);

        $this->getJson('/api/google-form-orders?from=2026-12-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonCount(0);

        $this->putJson("/api/google-form-orders/{$order->id}", [
            'submitted_on' => '2026-11-10',
            'event_date' => '2026-11-15',
            'service_type' => 'Catering',
            'items' => 'Sadya for 24',
            'status' => 'confirmed',
            'customer_name' => 'Jobins',
        ])->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('items', 'Sadya for 24');
    }
}
