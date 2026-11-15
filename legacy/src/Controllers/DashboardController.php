<?php

declare(strict_types=1);

namespace Foodeza\Bookkeeping\Controllers;

use Foodeza\Bookkeeping\Models\Expense;
use Foodeza\Bookkeeping\Models\Income;
use Foodeza\Bookkeeping\Models\Invoice;
use Foodeza\Bookkeeping\Models\Report;
use Foodeza\Bookkeeping\View;

final class DashboardController
{
    public function index(): void
    {
        $config = require __DIR__ . '/../../config/app.php';
        $month = $_GET['month'] ?? date('Y-m');
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $profit = Report::monthlyProfit($month);
        $serviceRevenue = Income::byService($from, $to, $config['services']);
        $recentIncome = Income::all(5);
        $recentExpenses = Expense::all(5);
        $outstanding = Invoice::outstanding();

        View::render('dashboard', [
            'month' => $month,
            'profit' => $profit,
            'serviceRevenue' => $serviceRevenue,
            'recentIncome' => $recentIncome,
            'recentExpenses' => $recentExpenses,
            'outstanding' => $outstanding,
            'services' => $config['services'],
        ]);
    }
}
