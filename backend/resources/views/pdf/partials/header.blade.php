{{-- $title, $meta (label => value), $seller --}}
<table class="header">
    <tr>
        <td style="width: 50%;">
            <div class="brand">{{ $seller['name'] }}</div>
            <div class="small muted">{{ $seller['address'] }}</div>
            <div class="small muted">
                {{ __('pdf.phone') }}: {{ $seller['phone'] }} · {{ __('pdf.email') }}: {{ $seller['email'] }}
            </div>
            <div class="small muted">
                {{ __('pdf.bin') }}: {{ \App\Support\Localized::digits($seller['bin']) }}
                · {{ __('pdf.tin') }}: {{ \App\Support\Localized::digits($seller['tin']) }}
            </div>
        </td>
        <td style="width: 50%;" class="right">
            <h1>{{ $title }}</h1>
            <table class="meta" style="margin-top: 2mm;">
                @foreach ($meta as $label => $value)
                    <tr>
                        <td class="label right">{{ $label }}</td>
                        <td class="right bold">{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
