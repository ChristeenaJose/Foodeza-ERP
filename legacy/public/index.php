<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Foodeza\Bookkeeping\Controllers\BankController;
use Foodeza\Bookkeeping\Controllers\DashboardController;
use Foodeza\Bookkeeping\Controllers\ExpenseController;
use Foodeza\Bookkeeping\Controllers\IncomeController;
use Foodeza\Bookkeeping\Controllers\InvoiceController;
use Foodeza\Bookkeeping\Controllers\PaymentController;
use Foodeza\Bookkeeping\Controllers\ReportController;
use Foodeza\Bookkeeping\Database;
use Foodeza\Bookkeeping\Router;

session_start();

$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['timezone']);

Database::connection();

$router = new Router();
$dashboard = new DashboardController();
$income = new IncomeController();
$expenses = new ExpenseController();
$invoices = new InvoiceController();
$payments = new PaymentController();
$bank = new BankController();
$reports = new ReportController();

$router->get('/', fn () => $dashboard->index());

$router->get('/income', fn () => $income->index());
$router->get('/income/new', fn () => $income->create());
$router->post('/income', fn () => $income->store());
$router->post('/income/delete', fn () => $income->destroy());

$router->get('/expenses', fn () => $expenses->index());
$router->get('/expenses/new', fn () => $expenses->create());
$router->post('/expenses', fn () => $expenses->store());
$router->post('/expenses/delete', fn () => $expenses->destroy());

$router->get('/invoices', fn () => $invoices->index());
$router->get('/invoices/new', fn () => $invoices->create());
$router->post('/invoices', fn () => $invoices->store());
$router->get('/invoices/show', fn () => $invoices->show());
$router->get('/invoices/pdf', fn () => $invoices->pdf());
$router->post('/invoices/paid', fn () => $invoices->markPaid());

$router->get('/payments', fn () => $payments->index());
$router->get('/payments/new', fn () => $payments->create());
$router->post('/payments', fn () => $payments->store());

$router->get('/bank', fn () => $bank->index());
$router->post('/bank/import', fn () => $bank->import());
$router->post('/bank/match', fn () => $bank->match());

$router->get('/reports', fn () => $reports->index());
$router->get('/reports/profit-loss', fn () => $reports->profitLoss());
$router->get('/reports/vat', fn () => $reports->vat());
$router->get('/reports/outstanding', fn () => $reports->outstanding());
$router->get('/reports/attachments', fn () => $reports->attachments());
$router->get('/attachments/download', fn () => $reports->download());

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
