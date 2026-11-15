<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function renderHtml(Invoice $invoice): string
    {
        $invoice->load(['customer', 'items']);

        return view('invoices.pdf', [
            'invoice' => $invoice,
            'company' => config('foodeza'),
        ])->render();
    }

    public function generateAndStore(Invoice $invoice): Attachment
    {
        $invoice->load(['customer', 'items']);

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'company' => config('foodeza'),
        ])->setPaper('a4');

        $filename = $invoice->invoice_number.'.pdf';
        $path = 'invoices/'.$filename;
        Storage::disk('local')->put($path, $pdf->output());

        $attachment = Attachment::create([
            'original_name' => $filename,
            'stored_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => Storage::disk('local')->size($path),
            'type' => 'invoice',
        ]);

        if ($invoice->pdf_attachment_id) {
            $old = Attachment::find($invoice->pdf_attachment_id);
            if ($old && Storage::disk('local')->exists($old->stored_path)) {
                Storage::disk('local')->delete($old->stored_path);
            }
        }

        $invoice->update(['pdf_attachment_id' => $attachment->id]);

        return $attachment;
    }
}
