<?php

use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FixedExpenseController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OnamController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index']);

Route::get('/incomes/meta', [IncomeController::class, 'meta']);
Route::apiResource('incomes', IncomeController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

Route::get('/expenses/categories', [ExpenseController::class, 'categories']);
Route::apiResource('expenses', ExpenseController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

Route::get('/fixed-expenses', [FixedExpenseController::class, 'index']);
Route::post('/fixed-expenses', [FixedExpenseController::class, 'store']);
Route::put('/fixed-expenses/{fixedExpense}', [FixedExpenseController::class, 'update']);
Route::delete('/fixed-expenses/{fixedExpense}', [FixedExpenseController::class, 'destroy']);
Route::post('/fixed-expenses/import', [FixedExpenseController::class, 'import']);

Route::get('/invoices/peek-number', [InvoiceController::class, 'peekNumber']);
Route::get('/invoices/outstanding', [InvoiceController::class, 'outstanding']);
Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
Route::post('/invoices/{invoice}/regenerate-pdf', [InvoiceController::class, 'regeneratePdf']);
Route::post('/invoices/{invoice}/paid', [InvoiceController::class, 'markPaid']);
Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show', 'destroy']);

Route::apiResource('payments', PaymentController::class)->only(['index', 'store']);

Route::get('/bank', [BankController::class, 'index']);
Route::post('/bank/import', [BankController::class, 'import']);
Route::post('/bank/match', [BankController::class, 'match']);

Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);
Route::get('/reports/vat', [ReportController::class, 'vat']);
Route::get('/reports/tax', [ReportController::class, 'tax']);
Route::get('/reports/tax/pdf', [ReportController::class, 'taxPdf']);
Route::get('/reports/attachments', [ReportController::class, 'attachments']);
Route::get('/attachments/{attachment}/download', [ReportController::class, 'download']);

Route::prefix('onam')->group(function () {
    Route::get('/summary', [OnamController::class, 'summary']);
    Route::get('/partners', [OnamController::class, 'partners']);
    Route::post('/partners', [OnamController::class, 'storePartner']);
    Route::put('/partners/{partner}', [OnamController::class, 'updatePartner']);
    Route::delete('/partners/{partner}', [OnamController::class, 'destroyPartner']);
    Route::get('/entries', [OnamController::class, 'entries']);
    Route::post('/entries', [OnamController::class, 'storeEntry']);
    Route::put('/entries/{entry}', [OnamController::class, 'updateEntry']);
    Route::delete('/entries/{entry}', [OnamController::class, 'destroyEntry']);
});
