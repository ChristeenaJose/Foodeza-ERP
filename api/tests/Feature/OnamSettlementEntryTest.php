<?php

namespace Tests\Feature;

use App\Models\OnamPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnamSettlementEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlement_entry_requires_a_partner(): void
    {
        $this->postJson('/api/onam/entries', [
            'date' => '2026-08-03',
            'type' => 'settlement',
            'amount' => 50,
        ])->assertStatus(422);
    }

    public function test_settlement_entry_can_be_recorded_for_a_partner(): void
    {
        $ajo = OnamPartner::create(['name' => 'Ajo', 'share_percent' => 50, 'active' => true]);

        $this->postJson('/api/onam/entries', [
            'date' => '2026-08-03',
            'type' => 'settlement',
            'partner_id' => $ajo->id,
            'amount' => 50,
            'description' => 'Cash to Ajo',
        ])->assertCreated()
            ->assertJsonPath('type', 'settlement')
            ->assertJsonPath('partner_id', $ajo->id);
    }

    public function test_income_entry_stores_vat_when_including_vat(): void
    {
        $this->postJson('/api/onam/entries', [
            'date' => '2026-08-03',
            'type' => 'income',
            'amount' => 121,
            'vat_mode' => 'including',
            'description' => 'Tickets',
        ])->assertCreated()
            ->assertJsonPath('type', 'income')
            ->assertJsonPath('amount', '121.00')
            ->assertJsonPath('vat_mode', 'including')
            ->assertJsonPath('vat_amount', '21.00');
    }

    public function test_income_entry_stores_no_vat_when_not_applicable(): void
    {
        $this->postJson('/api/onam/entries', [
            'date' => '2026-08-03',
            'type' => 'income',
            'amount' => 121,
            'vat_mode' => 'none',
            'description' => 'Cash sales',
        ])->assertCreated()
            ->assertJsonPath('vat_mode', 'none')
            ->assertJsonPath('vat_amount', '0.00');
    }
}
