<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Cart\CartService;
use App\Services\Dashboard\BuyerDashboardService;
use App\Services\Finance\CreditLedgerService;
use App\Services\Payment\GatewayManager;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BuyerDashboardService $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->summary($request->user())]);
    }

    /**
     * Checkout preview: server-computed totals incl. delivery charge, credit headroom,
     * allowed payment methods and whether the order will need buyer-admin approval.
     * Final enforcement still happens atomically in OrderService::checkout().
     */
    public function checkoutPreview(Request $request, CartService $cart, CreditLedgerService $ledger, GatewayManager $gateways): JsonResponse
    {
        $user = $request->user();
        $summary = $cart->summary($user);
        $totals = $summary['totals'];

        $threshold = (string) Setting::get('orders.free_shipping_threshold', '0');
        $shipping = Money::isPositive($threshold) && Money::compare($totals['subtotal'], $threshold) >= 0
            ? '0.00'
            : Money::format(Money::of((string) Setting::get('orders.flat_shipping_fee', '0')));
        $grand = Money::add($totals['subtotal'], $totals['vat_total'], $shipping);
        $minimum = (string) Setting::get('orders.min_order_amount', '0');
        $codMax = (string) Setting::get('orders.cod_max_amount', '0');

        $company = $user->company()->firstOrFail();
        $available = DB::transaction(fn () => $ledger->availableCredit($ledger->lockCompany($company)));
        $hasOverdue = $company->overdueInvoices()->exists();

        $methods = [
            [
                'value' => PaymentMethod::Credit->value,
                'label' => PaymentMethod::Credit->label(),
                'available' => $company->credit_enabled && ! $company->credit_on_hold && ! $hasOverdue && Money::compare($grand, $available) <= 0,
                'reason' => match (true) {
                    ! $company->credit_enabled => __('messages.errors.credit_not_enabled'),
                    $company->credit_on_hold => __('messages.errors.credit_on_hold'),
                    $hasOverdue => __('messages.errors.credit_overdue_invoices'),
                    Money::compare($grand, $available) > 0 => __('messages.errors.credit_limit_exceeded', ['amount' => $grand, 'available' => $available]),
                    default => null,
                },
            ],
            [
                'value' => PaymentMethod::Cod->value,
                'label' => PaymentMethod::Cod->label(),
                'available' => Money::compare($grand, $codMax) <= 0,
                'reason' => Money::compare($grand, $codMax) > 0 ? __('messages.errors.cod_limit_exceeded', ['max' => $codMax]) : null,
            ],
            ['value' => PaymentMethod::BankTransfer->value, 'label' => PaymentMethod::BankTransfer->label(), 'available' => true, 'reason' => null],
            [
                'value' => PaymentMethod::Gateway->value,
                'label' => PaymentMethod::Gateway->label(),
                'available' => $gateways->defaultEnabled(),
                'reason' => $gateways->defaultEnabled() ? null : __('messages.errors.gateway_unavailable'),
            ],
        ];

        return response()->json(['data' => [
            'totals' => array_merge($totals, ['shipping_total' => $shipping, 'grand_total' => $grand]),
            'item_count' => $summary['item_count'],
            'has_errors' => $summary['has_errors'],
            'minimum_order_amount' => $minimum,
            'below_minimum' => Money::compare($totals['subtotal'], $minimum) < 0,
            'free_shipping_threshold' => $threshold,
            'credit' => [
                'enabled' => $company->credit_enabled,
                'available' => $available,
                'payment_terms_days' => $company->payment_terms_days,
            ],
            'requires_approval' => $user->requiresApprovalFor($grand),
            'approval_limit' => $user->order_approval_limit,
            'payment_methods' => $methods,
        ]]);
    }
}
