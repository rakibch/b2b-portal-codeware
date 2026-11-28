<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Services\Pdf\DocumentPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Bilingual Tax Invoice / Delivery Challan PDFs. ?lang=en|bn (defaults to the request locale). */
class DocumentPdfController extends Controller
{
    public function __construct(private readonly DocumentPdfService $pdf) {}

    public function invoice(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $locale = $this->locale($request);
        AuditLog::record('document.invoice_downloaded', $invoice, [], ['locale' => $locale]);

        return $this->respond($this->pdf->invoice($invoice, $locale), "{$invoice->invoice_number}-{$locale}.pdf", $request);
    }

    public function challan(Request $request, Shipment $shipment): Response
    {
        Gate::authorize('view', $shipment);

        $locale = $this->locale($request);

        return $this->respond($this->pdf->challan($shipment, $locale), "{$shipment->challan_number}-{$locale}.pdf", $request);
    }

    private function locale(Request $request): string
    {
        $request->validate(['lang' => ['nullable', Rule::in(config('b2b.supported_locales'))]]);

        return $request->query('lang', app()->getLocale());
    }

    private function respond(string $binary, string $filename, Request $request): Response
    {
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
