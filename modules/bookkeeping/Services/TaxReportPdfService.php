<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class TaxReportPdfService
{
    public function __construct(private TaxReportService $reports) {}

    public function generate(int $year)
    {
        $report = $this->reports->generate($year);

        return Pdf::loadView('reports.tax', [
            'report' => $report,
            'company' => config('foodeza'),
        ])->setPaper('a4', 'portrait');
    }
}
