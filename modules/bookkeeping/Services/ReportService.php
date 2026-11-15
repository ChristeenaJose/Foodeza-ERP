<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function monthlyProfit(?string $month = null): array
    {
        $month = $month ?? now()->format('Y-m');
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return $this->periodProfit($from->toDateString(), $to->toDateString(), $month);
    }

    public function yearlyProfit(?string $year = null): array
    {
        $year = $year ?? now()->format('Y');
        $from = Carbon::parse("{$year}-01-01")->startOfYear();
        $to = $from->copy()->endOfYear();

        return $this->periodProfit($from->toDateString(), $to->toDateString(), $year);
    }

    public function periodProfit(string $from, string $to, ?string $label = null): array
    {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        $revenue = (float) Income::whereBetween('date', [$fromDate, $toDate])->sum('amount');
        $expenses = (float) Expense::whereBetween('date', [$fromDate, $toDate])->sum('amount');
        $vatCollected = (float) Income::whereBetween('date', [$fromDate, $toDate])->sum('vat_amount');
        $vatPaid = (float) Expense::whereBetween('date', [$fromDate, $toDate])->sum('vat_amount');
        $grossRevenue = $revenue + $vatCollected;
        $grossExpenses = $expenses + $vatPaid;

        return [
            'period' => $label,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'gross_revenue' => $grossRevenue,
            'gross_expenses' => $grossExpenses,
            'gross_profit' => $grossRevenue - $grossExpenses,
            'net_profit' => $revenue - $expenses,
            'vat_collected' => $vatCollected,
            'vat_paid' => $vatPaid,
            'vat_due' => $vatCollected - $vatPaid,
        ];
    }

    public function serviceRevenue(string $from, string $to): array
    {
        $totals = Income::whereBetween('date', [$from, $to])
            ->select(
                'service_type',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count'),
                DB::raw('COUNT(DISTINCT date) as days')
            )
            ->groupBy('service_type')
            ->get()
            ->keyBy('service_type');

        return collect(config('foodeza.services'))->map(fn ($service) => [
            'service' => $service,
            'total' => (float) ($totals[$service]->total ?? 0),
            'count' => (int) ($totals[$service]->count ?? 0),
            'days' => (int) ($totals[$service]->days ?? 0),
        ])->values()->all();
    }

    public function profitByMonth(?string $from = null, ?string $to = null): array
    {
        $from = Carbon::parse($from ?? config('foodeza.records_start_date', '2025-01-01'))->startOfMonth();
        $to = Carbon::parse($to ?? now()->format('Y-m-d'))->endOfMonth();

        $incomes = Income::whereBetween('date', [$from, $to])
            ->select(
                DB::raw("strftime('%Y-%m', date) as month"),
                DB::raw('SUM(amount) as total'),
                DB::raw('SUM(vat_amount) as vat_total')
            )
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $expenses = Expense::whereBetween('date', [$from, $to])
            ->select(
                DB::raw("strftime('%Y-%m', date) as month"),
                DB::raw('SUM(amount) as total'),
                DB::raw('SUM(vat_amount) as vat_total')
            )
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $all = $incomes->keys()->merge($expenses->keys())->unique()->sort()->values();

        return $all->map(function ($m) use ($incomes, $expenses) {
            $revenue = (float) ($incomes[$m]->total ?? 0);
            $expenseTotal = (float) ($expenses[$m]->total ?? 0);
            $vatCollected = (float) ($incomes[$m]->vat_total ?? 0);
            $vatPaid = (float) ($expenses[$m]->vat_total ?? 0);
            $grossRevenue = $revenue + $vatCollected;
            $grossExpenses = $expenseTotal + $vatPaid;

            return [
                'month' => $m,
                'revenue' => $revenue,
                'expenses' => $expenseTotal,
                'gross_revenue' => $grossRevenue,
                'gross_expenses' => $grossExpenses,
                'gross_profit' => $grossRevenue - $grossExpenses,
                'net_profit' => $revenue - $expenseTotal,
            ];
        })->all();
    }

    public function expenseByCategory(string $from, string $to): array
    {
        return Expense::whereBetween('date', [$from, $to])
            ->join('categories', 'categories.id', '=', 'expenses.category_id')
            ->selectRaw(
                'categories.name as category_name,
                 SUM(expenses.amount) as total_net,
                 SUM(COALESCE(expenses.vat_amount, 0)) as total_vat,
                 SUM(expenses.amount + COALESCE(expenses.vat_amount, 0)) as total_gross,
                 COUNT(expenses.id) as count'
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_gross')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category_name,
                'total' => round((float) $row->total_gross, 2),
                'total_net' => round((float) $row->total_net, 2),
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    public function vatReport(string $from, string $to): array
    {
        $revenue = (float) Income::whereBetween('date', [$from, $to])->sum('amount');
        $vatCollected = (float) Income::whereBetween('date', [$from, $to])->sum('vat_amount');
        $expenses = (float) Expense::whereBetween('date', [$from, $to])->sum('amount');
        $vatPaid = (float) Expense::whereBetween('date', [$from, $to])->sum('vat_amount');

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'vat_collected' => $vatCollected,
            'vat_paid' => $vatPaid,
            'expenses' => $expenses,
            'vat_due' => $vatCollected - $vatPaid,
        ];
    }
}
