@php
    use App\Support\Localized as L;
    $order = $shipment->order;
    $company = $order->company;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.delivery_challan') }} {{ $shipment->challan_number }}</title>
    @include('pdf.partials.styles', ['font' => $font])
</head>
<body>
<htmlpagefooter name="footer">
    <table class="small muted"><tr>
        <td>{{ $shipment->challan_number }} · {{ __('pdf.no_prices_note') }}</td>
        <td class="right">{{ __('pdf.page') }}</td>
    </tr></table>
</htmlpagefooter>
<sethtmlpagefooter name="footer" value="on" />

@include('pdf.partials.header', [
    'title' => __('pdf.delivery_challan'),
    'seller' => $seller,
    'meta' => array_filter([
        __('pdf.challan_no') => $shipment->challan_number,
        __('pdf.order_no') => $order->order_number,
        __('pdf.po_no') => $order->po_number,
        __('pdf.dispatch_date') => L::date($shipment->dispatched_at ?? $shipment->created_at),
        __('pdf.carrier') => $shipment->carrier,
        __('pdf.vehicle_no') => $shipment->vehicle_number,
        __('pdf.driver') => $shipment->driver_name ? $shipment->driver_name.($shipment->driver_phone ? ' · '.L::digits($shipment->driver_phone) : '') : null,
        __('pdf.tracking_no') => $shipment->tracking_number,
    ]),
])

<table style="margin-top: 5mm;">
    <tr>
        <td class="box" style="width: 100%;">
            <div class="box-title">{{ __('pdf.ship_to') }}</div>
            <div class="bold">{{ $order->shipping_address['label'] ?? '' }}</div>
            @include('pdf.partials.address', ['address' => $order->shipping_address, 'company' => $company])
        </td>
    </tr>
</table>

{{-- Delivery challan intentionally contains NO prices. --}}
<table class="items" style="margin-top: 5mm;">
    <thead>
        <tr>
            <th class="center" style="width: 6%;">{{ __('pdf.sl') }}</th>
            <th style="width: 16%;">{{ __('pdf.sku') }}</th>
            <th style="width: 40%;">{{ __('pdf.description') }}</th>
            <th class="right" style="width: 12%;">{{ __('pdf.ordered_qty') }}</th>
            <th class="right" style="width: 14%;">{{ __('pdf.this_shipment') }}</th>
            <th class="right" style="width: 12%;">{{ __('pdf.packs') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($shipment->items as $i => $line)
            @php $item = $line->orderItem; @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="center">{{ L::digits($i + 1) }}</td>
                <td class="small">{{ $item->sku }}</td>
                <td>{{ $item->product_name }}</td>
                <td class="right">{{ L::number($item->quantity, null, 0) }} {{ $item->unit_name }}</td>
                <td class="right bold">{{ L::number($line->quantity, null, 0) }} {{ $item->unit_name }}</td>
                <td class="right">{{ $item->pack_size > 1 ? L::number($line->quantity / $item->pack_size, null, $line->quantity % $item->pack_size ? 1 : 0) : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if ($shipment->notes)
    <p style="margin-top: 4mm;"><span class="bold">{{ __('pdf.remarks') }}:</span> {{ $shipment->notes }}</p>
@endif

<p class="small" style="margin-top: 8mm;">{{ __('pdf.received_in_good_condition') }}</p>

<table class="sign">
    <tr>
        <td class="line" style="width: 38%;">{{ __('pdf.authorised_signature') }}</td>
        <td style="width: 24%;"></td>
        <td class="line right" style="width: 38%;">{{ __('pdf.received_by') }}</td>
    </tr>
</table>
</body>
</html>
