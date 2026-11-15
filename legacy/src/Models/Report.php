<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Models;

final class Report
{
    public static function monthlyProfit(?string $month = null): array
    {
        $month = $month ?? date('Y-m');
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $income = Income::totals($from, $to);
        $expense = Expense::totals($from, $to);

        return [
            'month' => $month,
            'revenue' => $income['revenue'],
            'expenses' => $expense['expenses'],
            'net_profit' => $income['revenue'] - $expense['expenses'],
            'vat_collected' => $income['vat_collected'],
            'vat_paid' => $expense['vat_paid'],
            'vat_due' => $income['vat_collected'] - $expense['vat_paid'],
        ];
    }

    /** @return list<array{month: string, revenue: float, expenses: float, net_profit: float}> */
    public static function profitByMonth(int $months = 12): array
    {
        $incomeMonths = Income::byMonth($months);
        $expenseMonths = Expense::byMonth($months);

        $incomeMap = [];
        foreach ($incomeMonths as $row) {
            $incomeMap[$row['month']] = (float) $row['total'];
        }

        $expenseMap = [];
        foreach ($expenseMonths as $row) {
            $expenseMap[$row['month']] = (float) $row['total'];
        }

        $allMonths = array_unique(array_merge(array_keys($incomeMap), array_keys($expenseMap)));
        sort($allMonths);

        $result = [];
        foreach ($allMonths as $month) {
            $revenue = $incomeMap[$month] ?? 0.0;
            $expenses = $expenseMap[$month] ?? 0.0;
            $result[] = [
                'month' => $month,
                'revenue' => $revenue,
                'expenses' => $expenses,
                'net_profit' => $revenue - $expenses,
            ];
        }

        return $result;
    }

    public static function vatReport(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? date('Y-m-01');
        $to = $to ?? date('Y-m-d');

        $income = Income::totals($from, $to);
        $expense = Expense::totals($from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => $income['revenue'],
            'vat_collected' => $income['vat_collected'],
            'vat_paid' => $expense['vat_paid'],
            'vat_due' => $income['vat_collected'] - $expense['vat_paid'],
            'expenses' => $expense['expenses'],
        ];
    }
}
