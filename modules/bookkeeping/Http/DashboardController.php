<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $period = $request->get('period', 'month');
        $month = $request->get('month', now()->format('Y-m'));
        $year = $request->get('year', now()->format('Y'));

        if ($period === 'year') {
            $from = "{$year}-01-01";
            $to = "{$year}-12-31";
            $profit = $this->reports->yearlyProfit($year);
            $serviceRevenue = $this->reports->serviceRevenue($from, $to);
            $expenseByCategory = $this->reports->expenseByCategory($from, $to);
            $profitByMonth = $this->reports->profitByMonth($from, $to);
        } else {
            $from = $month.'-01';
            $to = date('Y-m-t', strtotime($from));
            $profit = $this->reports->monthlyProfit($month);
            $serviceRevenue = $this->reports->serviceRevenue($from, $to);
            $expenseByCategory = $this->reports->expenseByCategory($from, $to);
            $profitByMonth = [];
        }

        return response()->json([
            'period' => $period,
            'month' => $period === 'month' ? $month : null,
            'year' => $period === 'year' ? $year : null,
            'profit' => $profit,
            'service_revenue' => $serviceRevenue,
            'expense_by_category' => $expenseByCategory,
            'profit_by_month' => $profitByMonth,
            'outstanding' => Invoice::with('customer')->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date')->get(),
            'recent_income' => Income::with('customer')->latest('date')->limit(5)->get(),
            'recent_expenses' => Expense::with(['supplier', 'category'])->latest('date')->limit(5)->get(),
            'config' => [
                'company' => config('foodeza.company'),
                'currency_symbol' => config('foodeza.currency_symbol'),
                'services' => config('foodeza.services'),
            ],
        ]);
    }
}
