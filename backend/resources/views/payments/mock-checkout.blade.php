<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Sandbox Payment</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; }
        .card { background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.08); max-width: 380px; width: 100%; }
        .badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: 12px; padding: 2px 8px; border-radius: 999px; }
        .amount { font-size: 28px; font-weight: 700; margin: 16px 0; }
        button { width: 100%; padding: 12px; border: 0; border-radius: 8px; font-size: 15px; cursor: pointer; margin-top: 8px; }
        .ok { background: #0f766e; color: #fff; } .fail { background: #e5e7eb; color: #111827; }
    </style>
</head>
<body>
<div class="card">
    <span class="badge">SANDBOX — no real money moves</span>
    <p>Payment {{ $payment->reference }}</p>
    <div class="amount">৳ {{ number_format((float) $payment->amount, 2) }}</div>
    <form method="POST" action="{{ route('payments.mock.return') }}">
        @foreach ($success as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <button class="ok" type="submit">Pay successfully</button>
    </form>
    <form method="POST" action="{{ route('payments.mock.return') }}">
        @foreach ($failed as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <button class="fail" type="submit">Simulate failure</button>
    </form>
</div>
</body>
</html>
