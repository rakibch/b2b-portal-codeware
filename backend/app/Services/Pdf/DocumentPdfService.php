<?php

namespace App\Services\Pdf;

use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Shipment;
use Mccarlosen\LaravelMpdf\LaravelMpdf;

/**
 * Renders bilingual Tax Invoice and Delivery Challan PDFs with mPDF.
 * Bangla documents use Noto Sans Bengali with OpenType Layout so conjuncts shape correctly;
 * missing glyphs (e.g. Latin in names/emails) are substituted from DejaVu Sans.
 */
class DocumentPdfService
{
    public function invoice(Invoice $invoice, string $locale): string
    {
        $invoice->loadMissing(['order.items', 'company']);

        return $this->render('pdf.invoice', [
            'invoice' => $invoice,
            'seller' => $this->seller($locale),
            'bankDetails' => $this->translated('invoice.bank_details', $locale),
            'footerNote' => $this->translated('invoice.footer_note', $locale),
        ], $locale, __('pdf.tax_invoice', [], $locale).' '.$invoice->invoice_number);
    }

    public function challan(Shipment $shipment, string $locale): string
    {
        $shipment->loadMissing(['order.company', 'items.orderItem']);

        return $this->render('pdf.challan', [
            'shipment' => $shipment,
            'seller' => $this->seller($locale),
        ], $locale, __('pdf.delivery_challan', [], $locale).' '.$shipment->challan_number);
    }

    private function render(string $view, array $data, string $locale, string $title): string
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $font = $locale === 'bn' ? 'notobengali' : 'dejavusans';
            $html = view($view, $data + ['font' => $font])->render();

            $pdf = new LaravelMpdf([
                'default_font' => $font,
                'title' => $title,
                'author' => (string) config('app.name'),
                // Glyphs missing in the primary font (e.g. ৳ in DejaVu, Latin in Noto Bengali) are
                // substituted from these fonts; must be set at construction time.
                'useSubstitutions' => true,
                'backupSubsFont' => ['notobengali', 'dejavusans'],
            ]);

            $mpdf = $pdf->getMpdf();
            $mpdf->SetProtection(['print', 'copy']); // no modification of issued documents
            $mpdf->WriteHTML($html);

            return $mpdf->OutputBinaryData();
        } finally {
            app()->setLocale($previous);
        }
    }

    private function seller(string $locale): array
    {
        return [
            'name' => $this->translated('seller.name', $locale) ?? config('app.name'),
            'address' => $this->translated('seller.address', $locale) ?? '',
            'phone' => (string) Setting::get('seller.phone', ''),
            'email' => (string) Setting::get('seller.email', ''),
            'bin' => (string) Setting::get('seller.bin', ''),
            'tin' => (string) Setting::get('seller.tin', ''),
        ];
    }

    private function translated(string $key, string $locale): ?string
    {
        $value = Setting::get($key);

        if (is_array($value)) {
            return $value[$locale] ?? $value['en'] ?? null;
        }

        return $value !== null ? (string) $value : null;
    }
}
