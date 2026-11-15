<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_returns_services_and_statuses(): void
    {
        $this->getJson('/api/bookings/meta')
            ->assertOk()
            ->assertJsonPath('services.0', 'Catering')
            ->assertJsonPath('statuses.inquiry', 'Inquiry');
    }

    public function test_booking_requires_event_date_and_service(): void
    {
        $this->postJson('/api/bookings', [
            'status' => 'inquiry',
        ])->assertStatus(422);
    }

    public function test_booking_can_be_created_for_a_customer(): void
    {
        $this->postJson('/api/bookings', [
            'event_date' => '2026-09-20',
            'start_time' => '18:00',
            'end_time' => '22:00',
            'service_type' => 'Catering',
            'status' => 'confirmed',
            'customer_name' => 'Anna Bakker',
            'customer_phone' => '+31 6 12345678',
            'location' => 'Amsterdam',
            'guest_count' => 40,
            'quoted_amount' => 850,
        ])->assertCreated()
            ->assertJsonPath('service_type', 'Catering')
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('reference', 'BK-2026-0001')
            ->assertJsonPath('title', 'Catering — Anna Bakker')
            ->assertJsonPath('customer.name', 'Anna Bakker')
            ->assertJsonPath('customer.phone', '+31 6 12345678');
    }

    public function test_bookings_can_be_filtered_by_date_and_updated(): void
    {
        $customer = Customer::create(['name' => 'Jobins']);

        $booking = Booking::create([
            'reference' => 'BK-2026-0001',
            'customer_id' => $customer->id,
            'title' => 'Food Truck — Jobins',
            'service_type' => 'Food Truck',
            'event_date' => '2026-09-13',
            'status' => 'inquiry',
        ]);

        $this->getJson('/api/bookings?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(1);

        $this->getJson('/api/bookings?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonCount(0);

        $this->putJson("/api/bookings/{$booking->id}", [
            'event_date' => '2026-09-13',
            'service_type' => 'Food Truck',
            'status' => 'confirmed',
            'customer_name' => 'Jobins',
            'title' => 'Office lunch truck',
        ])->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('title', 'Office lunch truck');
    }

    public function test_booking_can_be_deleted(): void
    {
        $booking = Booking::create([
            'reference' => 'BK-2026-0001',
            'title' => 'Meal Box Delivery',
            'service_type' => 'Meal Box Delivery',
            'event_date' => '2026-09-15',
            'status' => 'inquiry',
        ]);

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }
}
