@php
    use App\Support\Localized as L;
    $order = $invoice->order;
    $company = $invoice->company;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.tax_invoice') }} {{ $invoice->invoice_number }}</title>
    @include('pdf.partials.styles', ['font' => $font])
</head>
<body>
<htmlpagefooter name="footer">
    <table class="small muted"><tr>
        <td>{{ $invoice->invoice_number }} · {{ __('pdf.generated_at', ['datetime' => L::date(now(), null, 'd F Y, h:i A')]) }}</td>
        <td class="right">{{ __('pdf.page') }}</td>
    </tr></table>
</htmlpagefooter>
<sethtmlpagefooter name="footer" value="on" />

@include('pdf.partials.header', [
    'title' => __('pdf.tax_invoice'),
    'seller' => $seller,
    'meta' => array_filter([
        __('pdf.invoice_no') => $invoice->invoice_number,
        __('pdf.order_no') => $order->order_number,
        __('pdf.po_no') => $order->po_number,
        __('pdf.issue_date') => L::date($invoice->issue_date),
        __('pdf.due_date') => L::date($invoice->due_date),
        __('pdf.payment_method') => $order->payment_method->label(),
        __('pdf.payment_terms') => $company->payment_terms_days > 0 && $order->payment_method === \App\Enums\PaymentMethod::Credit
            ? __('pdf.net_days', ['days' => L::digits($company->payment_terms_days)])
            : null,
    ]),
])

<table style="margin-top: 5mm;">
    <tr>
        <td class="box" style="width: 50%;">
            <div class="box-title">{{ __('pdf.bill_to') }}</div>
            @include('pdf.partials.address', ['address' => $order->billing_address, 'company' => $company])
        </td>
        <td style="width: 2%;"></td>
        <td class="box" style="width: 48%;">
            <div class="box-title">{{ __('pdf.ship_to') }}</div>
            <div class="bold">{{ $order->shipping_address['label'] ?? '' }}</div>
            @include('pdf.partials.address', ['address' => $order->shipping_address, 'company' => null])
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 5mm;">
    <thead>
        <tr>
            <th class="center" style="width: 5%;">{{ __('pdf.sl') }}</th>
            <th style="width: 13%;">{{ __('pdf.sku') }}</th>
            <th style="width: 30%;">{{ __('pdf.description') }}</th>
            <th class="right" style="width: 10%;">{{ __('pdf.qty') }}</th>
            <th class="right" style="width: 13%;">{{ __('pdf.unit_price') }}</th>
            <th class="right" style="width: 13%;">{{ __('pdf.vat') }}</th>
            <th class="right" style="width: 16%;">{{ __('pdf.amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $i => $item)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="center">{{ L::digits($i + 1) }}</td>
                <td class="small">{{ $item->sku }}</td>
                <td>{{ $item->product_name }}</td>
                <td class="right">{{ L::number($item->quantity, null, 0) }} {{ $item->unit_name }}</td>
                <td class="right">{{ L::number($item->unit_price) }}</td>
                <td class="right">{{ L::number($item->vat_amount) }}<br><span class="small muted">{{ L::number($item->vat_percent) }}%</span></td>
                <td class="right">{{ L::number($item->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top: 4mm;">
    <tr>
        <td style="width: 55%; vertical-align: top;">
            <div class="box-title">{{ __('pdf.in_words') }}</div>
            <div class="bold">{{ L::amountInWords($invoice->grand_total) }}</div>

            @if ($bankDetails)
                <div class="box-title" style="margin-top: 4mm;">{{ __('pdf.bank_details') }}</div>
                <div class="small">{{ $bankDetails }}</div>
            @endif

            @if ($invoice->status === \App\Enums\InvoiceStatus::Void)
                <div style="margin-top: 6mm;"><span class="stamp">{{ __('pdf.void') }}</span></div>
            @elseif ($invoice->status === \App\Enums\InvoiceStatus::Paid)
                <div style="margin-top: 6mm;"><span class="stamp paid">{{ __('pdf.paid') }}</span></div>
            @endif
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table class="totals">
                <tr><td>{{ __('pdf.subtotal') }}</td><td class="right">{!! L::currencyHtml($invoice->subtotal) !!}</td></tr>
                @if (\App\Support\Money::isPositive((string) $invoice->discount_total))
                    <tr><td class="muted">{{ __('pdf.discount') }}</td><td class="right muted">{!! L::currencyHtml($invoice->discount_total) !!}</td></tr>
                @endif
                <tr><td>{{ __('pdf.vat_total') }}</td><td class="right">{!! L::currencyHtml($invoice->vat_total) !!}</td></tr>
                <tr><td>{{ __('pdf.shipping') }}</td><td class="right">{!! L::currencyHtml($invoice->shipping_total) !!}</td></tr>
                <tr class="grand"><td>{{ __('pdf.grand_total') }}</td><td class="right">{!! L::currencyHtml($invoice->grand_total) !!}</td></tr>
                <tr><td>{{ __('pdf.amount_paid') }}</td><td class="right">{!! L::currencyHtml(\App\Support\Money::add((string) $invoice->amount_paid, (string) $invoice->amount_credited)) !!}</td></tr>
                <tr><td class="bold">{{ __('pdf.balance_due') }}</td><td class="right bold">{!! L::currencyHtml($invoice->balanceDue()) !!}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if ($footerNote)
    <p class="small muted" style="margin-top: 6mm;">{{ $footerNote }}</p>
@endif

<table class="sign">
    <tr>
        <td class="line" style="width: 38%;">{{ __('pdf.prepared_by') }}</td>
        <td style="width: 24%;"></td>
        <td class="line right" style="width: 38%;">{{ __('pdf.authorised_signature') }}</td>
    </tr>
</table>
</body>
</html>
