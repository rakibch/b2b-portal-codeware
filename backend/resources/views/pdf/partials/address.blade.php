{{-- $address (snapshot array), $company (Company|null) --}}
@if ($company)
    <div class="bold">{{ $company->name }}</div>
@endif
<div>{{ $address['contact_name'] ?? '' }} · {{ \App\Support\Localized::digits($address['contact_phone'] ?? '') }}</div>
<div>{{ collect([$address['address_line1'] ?? null, $address['address_line2'] ?? null, $address['area'] ?? null])->filter()->implode(', ') }}</div>
<div>{{ collect([$address['city'] ?? null, $address['district'] ?? null, isset($address['postal_code']) ? \App\Support\Localized::digits($address['postal_code']) : null])->filter()->implode(', ') }}</div>
@if ($company && $company->bin_no)
    <div class="small muted">{{ __('pdf.bin') }}: {{ \App\Support\Localized::digits($company->bin_no) }}</div>
@endif
@if ($company && $company->tin_no)
    <div class="small muted">{{ __('pdf.tin') }}: {{ \App\Support\Localized::digits($company->tin_no) }}</div>
@endif
