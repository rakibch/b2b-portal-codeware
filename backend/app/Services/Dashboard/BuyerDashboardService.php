<?php

namespace App\Services\Dashboard;

use App\Enums\OrderApprovalStatus;
use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use App\Services\Finance\CreditLedgerService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Single-request buyer dashboard: a fixed, small number of indexed queries. */
class BuyerDashboardService
{
    public function __construct(private readonly CreditLedgerService $ledger) {}

    public function summary(User $user): array
    {
        $company = Company::query()->findOrFail($user->company_id);

        $open = [OrderStatus::Pending->value, OrderStatus::Confirmed->value, OrderStatus::Processing->value, OrderStatus::PartiallyDispatched->value, OrderStatus::Dispatched->value];

        $outstanding = Invoice::query()->where('company_id', $company->id)->outstanding();
        $outstandingTotals = (clone $outstanding)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total - amount_paid - amount_credited), 0) as due')
            ->first();
        $overdue = (clone $outstanding)->whereDate('due_date', '<', today()->toDateString())
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total - amount_paid - amount_credited), 0) as due')
            ->first();

        return [
            'credit' => $this->credit($company),
            'stats' => [
                'open_orders' => Order::query()->where('company_id', $company->id)->whereIn('status', $open)->count(),
                'awaiting_my_approval' => $user->isBuyerAdmin()
                    ? Order::query()->where('company_id', $company->id)->where('approval_status', OrderApprovalStatus::Pending->value)->where('status', OrderStatus::Pending->value)->count()
                    : 0,
                'open_rfqs' => Rfq::query()->where('company_id', $company->id)->open()->count(),
                'outstanding_invoices' => (int) $outstandingTotals->cnt,
                'outstanding_amount' => Money::format(Money::of((string) $outstandingTotals->due)),
                'overdue_invoices' => (int) $overdue->cnt,
                'overdue_amount' => Money::format(Money::of((string) $overdue->due)),
            ],
            'recent_orders' => Order::query()
                ->where('company_id', $company->id)
                ->latest('id')
                ->limit(5)
                ->withCount('items')
                ->get(['id', 'order_number', 'status', 'payment_status', 'payment_method', 'approval_status', 'grand_total', 'created_at'])
                ->map(fn (Order $o) => [
                    'order_number' => $o->order_number,
                    'status' => ['value' => $o->status->value, 'label' => $o->status->label()],
                    'payment_status' => ['value' => $o->payment_status->value, 'label' => $o->payment_status->label()],
                    'approval_status' => ['value' => $o->approval_status->value, 'label' => $o->approval_status->label()],
                    'grand_total' => $o->grand_total,
                    'items_count' => $o->items_count,
                    'created_at' => $o->created_at?->toIso8601String(),
                ]),
            'open_rfqs' => Rfq::query()
                ->where('company_id', $company->id)
                ->open()
                ->latest('id')
                ->limit(5)
                ->withCount('items')
                ->get()
                ->map(fn (Rfq $r) => [
                    'reference' => $r->reference,
                    'status' => ['value' => $r->status->value, 'label' => $r->status->label()],
                    'valid_until' => $r->valid_until?->toDateString(),
                    'items_count' => $r->items_count,
                    'created_at' => $r->created_at?->toIso8601String(),
                ]),
            'outstanding_invoices' => (clone $outstanding)
                ->orderBy('due_date')
                ->limit(5)
                ->get()
                ->map(fn (Invoice $i) => [
                    'invoice_number' => $i->invoice_number,
                    'due_date' => $i->due_date?->toDateString(),
                    'is_overdue' => $i->isOverdue(),
                    'balance_due' => $i->balanceDue(),
                    'status' => ['value' => $i->status->value, 'label' => $i->status->label()],
                ]),
            'quick_reorder' => $this->frequentProducts($company),
        ];
    }

    private function credit(Company $company): array
    {
        $available = DB::transaction(fn () => $this->ledger->availableCredit($this->ledger->lockCompany($company)));

        return [
            'enabled' => $company->credit_enabled,
            'on_hold' => $company->credit_on_hold,
            'limit' => $company->credit_limit,
            'outstanding' => $company->outstanding_balance,
            'available' => $available,
            'used' => Money::max(Money::sub((string) $company->credit_limit, $available), '0'),
            'payment_terms_days' => $company->payment_terms_days,
        ];
    }

    /** Most frequently ordered active products in the last 180 days (one aggregate query). */
    private function frequentProducts(Company $company): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.company_id', $company->id)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.created_at', '>=', now()->subDays(180))
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, COUNT(*) as times, MAX(order_items.quantity) as last_qty')
            ->orderByDesc('times')
            ->limit(8)
            ->get();

        $products = Product::query()->active()->whereIn('id', $rows->pluck('product_id'))->with('unit')->get()->keyBy('id');

        return $rows->filter(fn ($r) => $products->has($r->product_id))->map(fn ($r) => [
            'product_id' => (int) $r->product_id,
            'sku' => $products[$r->product_id]->sku,
            'name' => $products[$r->product_id]->name,
            'slug' => $products[$r->product_id]->slug,
            'unit' => $products[$r->product_id]->unit->short_name,
            'times_ordered' => (int) $r->times,
            'suggested_quantity' => (int) $r->last_qty,
            'stock_status' => $products[$r->product_id]->stockStatus(),
        ])->values()->all();
    }
}
