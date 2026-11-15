<?php

namespace Tests\Unit;

use App\Models\OnamEntry;
use App\Models\OnamPartner;
use App\Services\OnamReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnamReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_partner_reduces_remaining_without_changing_profit(): void
    {
        $ajo = OnamPartner::create(['name' => 'Ajo', 'share_percent' => 50, 'active' => true]);
        OnamPartner::create(['name' => 'Other', 'share_percent' => 50, 'active' => true]);

        OnamEntry::create([
            'date' => '2026-08-01',
            'type' => 'income',
            'amount' => 200,
            'vat_mode' => 'none',
            'description' => 'Ticket sales',
        ]);
        OnamEntry::create([
            'date' => '2026-08-02',
            'type' => 'expense',
            'partner_id' => $ajo->id,
            'amount' => 40,
            'vat_mode' => 'none',
            'description' => 'Groceries',
        ]);

        $before = app(OnamReportService::class)->summary();
        $ajoBefore = collect($before['settlements'])->firstWhere('name', 'Ajo');

        $this->assertEquals(160.0, $before['totals']['profit']);
        $this->assertEquals(80.0, $ajoBefore['profit_share']);
        $this->assertEquals(0.0, $ajoBefore['settled']);
        $this->assertEquals($ajoBefore['net_settlement'], $ajoBefore['remaining']);

        OnamEntry::create([
            'date' => '2026-08-03',
            'type' => 'settlement',
            'partner_id' => $ajo->id,
            'amount' => 50,
            'vat_mode' => 'none',
            'description' => 'Cash to Ajo',
        ]);

        $after = app(OnamReportService::class)->summary();
        $ajoAfter = collect($after['settlements'])->firstWhere('name', 'Ajo');

        $this->assertEquals(160.0, $after['totals']['profit']);
        $this->assertEquals($ajoBefore['profit_share'], $ajoAfter['profit_share']);
        $this->assertEquals($ajoBefore['net_settlement'], $ajoAfter['net_settlement']);
        $this->assertEquals(50.0, $ajoAfter['settled']);
        $this->assertEquals(round($ajoBefore['remaining'] - 50, 2), $ajoAfter['remaining']);
        $this->assertCount(1, $after['payout_entries']);
        $this->assertCount(1, $after['expense_entries']);
    }

    public function test_including_vat_uses_net_amount_for_partner_settlement(): void
    {
        OnamPartner::create(['name' => 'Ajo', 'share_percent' => 100, 'active' => true]);

        OnamEntry::create([
            'date' => '2026-08-01',
            'type' => 'income',
            'amount' => 121,
            'vat_mode' => 'including',
            'description' => 'Tickets',
        ]);

        $summary = app(OnamReportService::class)->summary();
        $income = $summary['income_entries'][0];
        $ajo = collect($summary['settlements'])->firstWhere('name', 'Ajo');

        $this->assertEquals(121.0, $summary['totals']['income']);
        $this->assertEquals(100.0, $summary['totals']['income_net']);
        $this->assertEquals(21.0, $summary['totals']['income_vat']);
        $this->assertEquals(100.0, $summary['totals']['profit']);
        $this->assertEquals(100.0, $ajo['profit_share']);
        $this->assertEquals(100.0, $income->net_amount);
        $this->assertEquals(21.0, (float) $income->vat_amount);
        $this->assertEquals('including', $income->vat_mode);
    }

    public function test_no_vat_keeps_full_amount_in_partner_settlement(): void
    {
        OnamPartner::create(['name' => 'Ajo', 'share_percent' => 100, 'active' => true]);

        OnamEntry::create([
            'date' => '2026-08-01',
            'type' => 'income',
            'amount' => 121,
            'vat_mode' => 'none',
            'description' => 'Cash sales',
        ]);

        $summary = app(OnamReportService::class)->summary();
        $ajo = collect($summary['settlements'])->firstWhere('name', 'Ajo');

        $this->assertEquals(121.0, $summary['totals']['income_net']);
        $this->assertEquals(0.0, $summary['totals']['income_vat']);
        $this->assertEquals(121.0, $summary['totals']['profit']);
        $this->assertEquals(121.0, $ajo['profit_share']);
    }
}
