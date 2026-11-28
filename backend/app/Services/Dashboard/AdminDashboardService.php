<?php

namespace App\Services\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\KycStatus;
use App\Enums\OrderApprovalStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentRecordStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin KPIs, scoped by role: Super Admin sees everything, a Sales Manager sees their accounts
 * (+ unassigned), Warehouse sees operational counts only. All figures are aggregate queries.
 */
class AdminDashboardService
{
    public function summary(User $user): array
    {
        $commercial = $user->can('companies.view') || $user->can('companies.manage');
        $companyIds = fn () => Company::query()->visibleTo($user)->select('id');
        $scope = fn (Builder $q) => $user->can('companies.manage') ? $q : $q->whereIn('company_id', $companyIds());

        $monthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();

        $data = [
            'operations' => [
                'pending_orders' => $scope(Order::query())
                    ->where('status', OrderStatus::Pending->value)
                    ->where('approval_status', '!=', OrderApprovalStatus::Pending->value)
                    ->count(),
                'to_dispatch' => $scope(Order::query())
                    ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Processing->value, OrderStatus::PartiallyDispatched->value])
                    ->count(),
            ],
            'low_stock' => Product::query()
                ->active()
                ->lowStock()
                ->orderBy('stock_quantity')
                ->limit(10)
                ->with('unit')
                ->get(['id', 'sku', 'name', 'stock_quantity', 'low_stock_threshold', 'unit_id'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'stock_quantity' => $p->stock_quantity,
                    'low_stock_threshold' => $p->low_stock_threshold,
                    'unit' => $p->unit->short_name,
                ]),
            'low_stock_count' => Product::query()->active()->lowStock()->count(),
        ];

        if (! $commercial) {
            return $data;
        }

        $revenue = fn (Carbon $from, Carbon $to) => (string) $scope(Invoice::query())
            ->where('status', '!=', InvoiceStatus::Void->value)
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->sum('grand_total');

        $overdue = $scope(Invoice::query())->overdue()
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total - amount_paid - amount_credited), 0) as due')
            ->first();

        $receivables = (string) Company::query()
            ->when(! $user->can('companies.manage'), fn ($q) => $q->visibleTo($user))
            ->where('outstanding_balance', '>', 0)
            ->sum('outstanding_balance');

        return $data + [
            'revenue' => [
                'this_month' => Money::format(Money::of($revenue($monthStart, now()))),
                'last_month' => Money::format(Money::of($revenue($lastMonthStart, $lastMonthStart->copy()->endOfMonth()))),
            ],
            'kyc_pending' => Company::query()->visibleTo($user)->where('kyc_status', KycStatus::Pending->value)->count(),
            'receivables' => Money::format(Money::of($receivables)),
            'overdue' => ['count' => (int) $overdue->cnt, 'amount' => Money::format(Money::of((string) $overdue->due))],
            'payments_to_verify' => $scope(Payment::query())->where('status', PaymentRecordStatus::Pending->value)->count(),
            'awaiting_buyer_approval' => $scope(Order::query())->where('approval_status', OrderApprovalStatus::Pending->value)->where('status', OrderStatus::Pending->value)->count(),
            'sales_chart' => $this->salesChart($scope),
            'recent_orders' => $scope(Order::query())
                ->with('company:id,name')
                ->latest('id')
                ->limit(6)
                ->get(['id', 'order_number', 'company_id', 'status', 'grand_total', 'created_at'])
                ->map(fn (Order $o) => [
                    'order_number' => $o->order_number,
                    'company' => $o->company->name,
                    'status' => ['value' => $o->status->value, 'label' => $o->status->label()],
                    'grand_total' => $o->grand_total,
                    'created_at' => $o->created_at?->toIso8601String(),
                ]),
        ];
    }

    /** Daily order value for the last 30 days (cancelled excluded), zero-filled. */
    private function salesChart(callable $scope): array
    {
        $from = now()->subDays(29)->startOfDay();

        $rows = $scope(Order::query())
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->where('created_at', '>=', $from)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as day, SUM(grand_total) as total, COUNT(*) as orders')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->day)->toDateString());

        $series = [];
        for ($i = 0; $i < 30; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $row = $rows->get($day);
            $series[] = [
                'date' => $day,
                'total' => Money::format(Money::of((string) ($row->total ?? '0'))),
                'orders' => (int) ($row->orders ?? 0),
            ];
        }

        return $series;
    }
}
