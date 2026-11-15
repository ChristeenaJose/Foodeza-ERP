<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoogleFormOrder;
use App\Models\GoogleFormOrderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GoogleFormOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_returns_services_statuses_and_template(): void
    {
        $this->getJson('/api/google-form-orders/meta')
            ->assertOk()
            ->assertJsonPath('services.0', 'Catering')
            ->assertJsonPath('statuses.new', 'New')
            ->assertJsonPath('default_confirmation_template', 'Hi {name}, your order placed, please collect {date} {time} {location}');
    }

    public function test_event_can_be_created_with_auto_template(): void
    {
        $this->postJson('/api/google-form-order-events', [
            'name' => 'Saturday meal boxes',
            'location' => 'Foodeza kitchen',
            'event_date' => '2026-11-20',
            'start_time' => '11:00',
            'end_time' => '14:00',
        ])->assertCreated()
            ->assertJsonPath('name', 'Saturday meal boxes')
            ->assertJsonPath('location', 'Foodeza kitchen')
            ->assertJsonPath('confirmation_template', 'Hi {name}, your order placed, please collect {date} {time} {location}');
    }

    public function test_event_accepts_browser_time_with_seconds(): void
    {
        $this->postJson('/api/google-form-order-events', [
            'name' => 'Kehl Foodtruck Day',
            'location' => 'Kath. Kirche Herz-Jesu Kork Uferstrasse 3, 77694 Kehl, Germany',
            'event_date' => '2026-11-15 00:00:00',
            'start_time' => '15:00:00',
            'end_time' => '19:00:00',
            'confirmation_template' => 'Hi {name}, your order placed, please collect {date} {time} {location}',
        ])->assertCreated()
            ->assertJsonPath('start_time', '15:00')
            ->assertJsonPath('end_time', '19:00')
            ->assertJsonPath('name', 'Kehl Foodtruck Day');
    }

    public function test_order_requires_event_dates_and_items(): void
    {
        $this->postJson('/api/google-form-orders', [
            'status' => 'new',
        ])->assertStatus(422);
    }

    public function test_order_can_be_created_under_an_event(): void
    {
        $event = $this->makeEvent();

        $this->postJson('/api/google-form-orders', [
            'event_id' => $event->id,
            'submitted_on' => '2026-11-15',
            'service_type' => 'Meal Box Delivery',
            'items' => '2x Kerala lunch box',
            'status' => 'new',
            'customer_name' => 'Anna Bakker',
            'customer_email' => 'anna@example.com',
            'customer_phone' => '+31 6 12345678',
            'guest_count' => 2,
            'form_response_id' => 'response-1',
        ])->assertCreated()
            ->assertJsonPath('reference', 'GF-2026-0001')
            ->assertJsonPath('event_id', $event->id)
            ->assertJsonPath('service_type', 'Meal Box Delivery')
            ->assertJsonPath('status', 'new')
            ->assertJsonPath('items', '2x Kerala lunch box')
            ->assertJsonPath('form_response_id', 'response-1')
            ->assertJsonPath('customer.name', 'Anna Bakker')
            ->assertJsonPath('customer.email', 'anna@example.com');
    }

    public function test_csv_import_upserts_without_duplicates(): void
    {
        $event = $this->makeEvent();
        $csv = "Timestamp,Name,Email,Phone,Order\n"
            ."2026-11-10 09:00:00,Jobins,jobins@example.com,+31611111111,Sadya for 20\n"
            ."2026-11-10 10:00:00,Anna,anna@example.com,+31622222222,2 lunch boxes\n";

        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $this->post('/api/google-form-order-events/'.$event->id.'/import', [
            'file' => $file,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('updated', 0);

        $this->assertDatabaseCount('google_form_orders', 2);

        $csvUpdated = "Timestamp,Name,Email,Phone,Order\n"
            ."2026-11-10 09:00:00,Jobins,jobins@example.com,+31611111111,Sadya for 24\n"
            ."2026-11-10 10:00:00,Anna,anna@example.com,+31622222222,2 lunch boxes\n"
            ."2026-11-11 11:00:00,Sam,sam@example.com,+31633333333,1 curry box\n";

        $file2 = UploadedFile::fake()->createWithContent('orders2.csv', $csvUpdated);

        $this->post('/api/google-form-order-events/'.$event->id.'/import', [
            'file' => $file2,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 2);

        $this->assertDatabaseCount('google_form_orders', 3);
        $this->assertDatabaseHas('google_form_orders', [
            'event_id' => $event->id,
            'items' => 'Sadya for 24',
        ]);
    }

    public function test_order_can_be_cancelled_and_edited(): void
    {
        $event = $this->makeEvent();
        $customer = Customer::create(['name' => 'Jobins', 'phone' => '+31611111111']);

        $order = GoogleFormOrder::create([
            'event_id' => $event->id,
            'reference' => 'GF-2026-0001',
            'customer_id' => $customer->id,
            'submitted_on' => '2026-11-10',
            'event_date' => '2026-11-20',
            'service_type' => 'Catering',
            'items' => 'Sadya for 20',
            'status' => 'new',
            'form_response_id' => 'resp-1',
        ]);

        $this->postJson("/api/google-form-orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->putJson("/api/google-form-orders/{$order->id}", [
            'event_id' => $event->id,
            'submitted_on' => '2026-11-10',
            'service_type' => 'Catering',
            'items' => 'Sadya for 24',
            'status' => 'confirmed',
            'customer_name' => 'Jobins',
            'customer_phone' => '+31611111111',
            'form_response_id' => 'resp-1',
        ])->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('items', 'Sadya for 24');
    }

    public function test_whatsapp_sent_flag_can_be_toggled(): void
    {
        $event = $this->makeEvent();
        $customer = Customer::create(['name' => 'Test Customer', 'phone' => '015237221698']);
        $order = GoogleFormOrder::create([
            'event_id' => $event->id,
            'reference' => 'GF-2026-0101',
            'customer_id' => $customer->id,
            'submitted_on' => '2026-11-10',
            'event_date' => '2026-11-20',
            'service_type' => 'Meal Box Delivery',
            'items' => '1 box',
            'status' => 'new',
            'form_response_id' => 'resp-wa-sent',
            'whatsapp_phone' => '015237221698',
        ]);

        $this->patchJson("/api/google-form-orders/{$order->id}/whatsapp-sent", [
            'sent' => true,
        ])->assertOk();
        $this->assertNotNull($order->fresh()->confirmation_sent_at);

        $this->patchJson("/api/google-form-orders/{$order->id}/whatsapp-sent", [
            'sent' => false,
        ])->assertOk();
        $this->assertNull($order->fresh()->confirmation_sent_at);

        $this->putJson("/api/google-form-orders/{$order->id}", [
            'event_id' => $event->id,
            'submitted_on' => '2026-11-10',
            'service_type' => 'Meal Box Delivery',
            'items' => '1 box',
            'status' => 'confirmed',
            'whatsapp_phone' => '015237221698',
            'confirmation_sent' => true,
            'form_response_id' => 'resp-wa-sent',
        ])->assertOk();
        $this->assertNotNull($order->fresh()->confirmation_sent_at);
    }

    public function test_confirmed_checkbox_updates_status_without_clearing_phone(): void
    {
        $event = $this->makeEvent();
        $customer = Customer::create(['name' => 'Test Customer', 'phone' => '015237221698']);
        $order = GoogleFormOrder::create([
            'event_id' => $event->id,
            'reference' => 'GF-2026-0100',
            'customer_id' => $customer->id,
            'submitted_on' => '2026-11-10',
            'event_date' => '2026-11-20',
            'service_type' => 'Meal Box Delivery',
            'items' => '1 box',
            'status' => 'new',
            'form_response_id' => 'resp-status',
            'whatsapp_phone' => '015237221698',
        ]);

        $this->patchJson("/api/google-form-orders/{$order->id}/status", [
            'status' => 'confirmed',
        ])->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('whatsapp_phone', '015237221698');

        $this->getJson("/api/google-form-orders/{$order->id}/whatsapp")
            ->assertOk()
            ->assertJsonPath('phone', '4915237221698');

        $this->patchJson("/api/google-form-orders/{$order->id}/status", [
            'status' => 'new',
        ])->assertOk()
            ->assertJsonPath('status', 'new')
            ->assertJsonPath('whatsapp_phone', '015237221698');
    }

    public function test_updated_whatsapp_phone_is_used_when_sending(): void
    {
        $event = $this->makeEvent();
        $customer = Customer::create(['name' => 'Test Customer', 'phone' => '015200000000']);
        $order = GoogleFormOrder::create([
            'event_id' => $event->id,
            'reference' => 'GF-2026-0099',
            'customer_id' => $customer->id,
            'submitted_on' => '2026-11-10',
            'event_date' => '2026-11-20',
            'service_type' => 'Meal Box Delivery',
            'items' => '1 box',
            'status' => 'new',
            'form_response_id' => 'resp-phone',
            'whatsapp_phone' => '015200000000',
        ]);

        $this->putJson("/api/google-form-orders/{$order->id}", [
            'event_id' => $event->id,
            'submitted_on' => '2026-11-10',
            'service_type' => 'Meal Box Delivery',
            'items' => '1 box',
            'status' => 'new',
            'customer_name' => 'Test Customer',
            'whatsapp_phone' => '015237221698',
            'form_response_id' => 'resp-phone',
        ])->assertOk()
            ->assertJsonPath('whatsapp_phone', '015237221698');

        $this->getJson("/api/google-form-orders/{$order->id}/whatsapp")
            ->assertOk()
            ->assertJsonPath('phone', '4915237221698')
            ->assertJsonPath('phone_display', '015237221698');
    }

    public function test_delivery_list_pdf_and_csv_include_amounts_and_item_totals(): void
    {
        $event = $this->makeEvent([
            'name' => 'Foodtruck - Kehl',
            'location' => 'Kehl',
            'event_date' => '2026-11-15',
            'start_time' => '15:00',
            'end_time' => '19:00',
        ]);

        $csv = file_get_contents(base_path('tests/Fixtures/foodtruck-kehl.csv'));
        $this->assertNotFalse($csv);

        $file = UploadedFile::fake()->createWithContent('foodtruck-kehl.csv', $csv);
        $this->post('/api/google-form-order-events/'.$event->id.'/import', [
            'file' => $file,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('created', 28);

        $list = app(\App\Services\GoogleFormOrderDeliveryListService::class)->build($event->fresh());
        $this->assertSame(28, $list['order_count']);
        $this->assertNotEmpty($list['columns']);
        $this->assertEqualsWithDelta(18.0, $list['rows'][0]['amount'], 0.01);
        $this->assertEqualsWithDelta(12.5, $list['rows'][1]['amount'], 0.01);
        // Bottom totals from sample PDF: Combo 9, Cutlet 13, Pazhampori 16, Chicken65 3, Palappam 17, Kappa 25, Lassi 5, Tea 5
        $labels = array_map(fn ($c) => $c['label'], $list['columns']);
        $totalsByLabel = array_combine($labels, $list['column_totals']);
        $this->assertSame(9, $totalsByLabel['Chicken Cutlet, Pazhampori & Tea Combo (€5.50)'] ?? null);
        $this->assertSame(13, $totalsByLabel['Chicken Cutlet (€2.00)'] ?? null);
        $this->assertSame(16, $totalsByLabel['Pazhampori (€2.00)'] ?? null);
        $this->assertSame(3, $totalsByLabel['Chicken 65 (€6.50)'] ?? null);
        $this->assertSame(17, $totalsByLabel['Palappam Combo with Chicken 65 (€12.50)'] ?? null);
        $this->assertSame(25, $totalsByLabel['Kappa Biriyani(€12.50)'] ?? null);
        $this->assertSame(5, $totalsByLabel['Mango Lassi (€3.50)'] ?? null);
        $this->assertSame(5, $totalsByLabel['Indian Tea (€2.00)'] ?? null);

        $this->get("/api/google-form-order-events/{$event->id}/delivery-list?format=pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $csvResponse = $this->get("/api/google-form-order-events/{$event->id}/delivery-list?format=csv");
        $csvResponse->assertOk();
        $this->assertStringContainsString('Full Name', $csvResponse->streamedContent());
        $this->assertStringContainsString('Jubin Sebastian E', $csvResponse->streamedContent());
    }

    public function test_confirmation_and_update_messages_are_prepared(): void
    {
        $event = $this->makeEvent();
        $customer = Customer::create(['name' => 'Anna', 'phone' => '06 12345678']);
        $order = GoogleFormOrder::create([
            'event_id' => $event->id,
            'reference' => 'GF-2026-0001',
            'customer_id' => $customer->id,
            'submitted_on' => '2026-11-10',
            'event_date' => '2026-11-20',
            'service_type' => 'Meal Box Delivery',
            'items' => '2 boxes',
            'status' => 'new',
            'form_response_id' => 'resp-1',
        ]);

        $this->postJson("/api/google-form-order-events/{$event->id}/send-confirmations")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('links.0.name', 'Anna')
            ->assertJsonPath('links.0.phone', '49612345678')
            ->assertJsonPath('links.0.url', 'https://wa.me/49612345678?text='.rawurlencode('Hi Anna, your order placed, please collect 20-11-2026 11:00–14:00 Foodeza kitchen'))
            ->assertJsonPath('links.0.sms_url', 'sms:+49612345678&body='.rawurlencode('Hi Anna, your order placed, please collect 20-11-2026 11:00–14:00 Foodeza kitchen'));

        $this->assertNull($order->fresh()->confirmation_sent_at);

        $this->postJson("/api/google-form-order-events/{$event->id}/send-confirmations", [
            'mark_sent' => true,
        ])->assertOk();

        $this->assertNotNull($order->fresh()->confirmation_sent_at);
        $whatsapp = $this->getJson("/api/google-form-orders/{$order->id}/whatsapp")->json();
        $this->assertStringContainsString('Hi Anna, your order placed', $whatsapp['message']);
        // Default country code is DE (49); 06… → 316… only when config says 31.
        $this->assertSame('49612345678', $whatsapp['phone']);

        $update = $this->postJson("/api/google-form-order-events/{$event->id}/updates", [
            'title' => 'Delay',
            'message' => 'Hi {name}, pickup delayed. New time {time} at {location}.',
        ])->assertCreated()->json();

        $this->postJson("/api/google-form-order-events/{$event->id}/updates/{$update['id']}/send")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('links.0.message', 'Hi Anna, pickup delayed. New time 11:00–14:00 at Foodeza kitchen.');
    }

    private function makeEvent(array $overrides = []): GoogleFormOrderEvent
    {
        return GoogleFormOrderEvent::create(array_merge([
            'name' => 'Saturday meal boxes',
            'location' => 'Foodeza kitchen',
            'event_date' => '2026-11-20',
            'start_time' => '11:00',
            'end_time' => '14:00',
            'confirmation_template' => GoogleFormOrderEvent::defaultConfirmationTemplate(),
        ], $overrides));
    }
}
