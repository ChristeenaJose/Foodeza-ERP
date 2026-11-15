<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Expense;
use App\Services\FileStorageService;
use App\Services\ReportService;
use App\Services\TaxReportPdfService;
use App\Services\TaxReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reports,
        private TaxReportService $taxReports,
        private TaxReportPdfService $taxPdf,
    ) {}

    public function profitLoss(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->format('Y-m'));
        $from = $request->get('from', $month.'-01');
        $to = $request->get('to', date('Y-m-t', strtotime($from)));
        $reportFrom = $request->get('report_from', config('foodeza.records_start_date', '2025-01-01'));
        $reportTo = $request->get('report_to', now()->format('Y-m-t'));

        return response()->json([
            'profit' => $this->reports->monthlyProfit($month),
            'service_revenue' => $this->reports->serviceRevenue($from, $to),
            'expense_by_category' => Expense::whereBetween('date', [$from, $to])
                ->join('categories', 'categories.id', '=', 'expenses.category_id')
                ->selectRaw('categories.name as category_name, SUM(expenses.amount) as total, COUNT(expenses.id) as count')
                ->groupBy('categories.id', 'categories.name')
                ->orderByDesc('total')
                ->get(),
            'profit_by_month' => $this->reports->profitByMonth($reportFrom, $reportTo),
        ]);
    }

    public function vat(Request $request): JsonResponse
    {
        $from = $request->get('from', config('foodeza.records_start_date', '2025-01-01'));
        $to = $request->get('to', now()->format('Y-m-d'));

        return response()->json($this->reports->vatReport($from, $to));
    }

    public function tax(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->format('Y'));

        return response()->json($this->taxReports->generate($year));
    }

    public function taxPdf(Request $request)
    {
        $year = (int) $request->get('year', now()->format('Y'));
        $download = $request->boolean('download');
        $filename = "Foodeza-Steuerberater-Paket-{$year}.pdf";
        $pdf = $this->taxPdf->generate($year);

        return $download
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    public function attachments(): JsonResponse
    {
        $receipts = Attachment::where('type', 'receipt')
            ->whereIn('id', Expense::whereNotNull('receipt_attachment_id')->pluck('receipt_attachment_id'))
            ->get()
            ->map(function ($a) {
                $expense = Expense::with('supplier')->where('receipt_attachment_id', $a->id)->first();

                return array_merge($a->toArray(), [
                    'expense' => $expense,
                ]);
            });

        return response()->json(['receipts' => $receipts]);
    }

    public function download(Attachment $attachment, FileStorageService $files)
    {
        return response()->file($files->fullPath($attachment), [
            'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
        ]);
    }
}
