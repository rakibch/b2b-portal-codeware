<?php

namespace App\Services\Rfq;

use App\Enums\RfqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\DocumentSequence;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use App\Notifications\RfqDeclinedNotification;
use App\Notifications\RfqQuotedNotification;
use App\Notifications\RfqRespondedNotification;
use App\Notifications\RfqSubmittedNotification;
use App\Services\Pricing\PricingEngineService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * RFQ lifecycle: submitted → under_review → quoted → accepted → converted (Phase 3 OrderService)
 *                                        ↘ rejected / cancelled / expired
 */
class RfqService
{
    public function __construct(private readonly PricingEngineService $pricing) {}

    /** @param array{buyer_notes?: ?string, items: list<array{product_id: int, quantity: int, target_unit_price?: ?string, notes?: ?string}>} $data */
    public function create(User $buyer, array $data): Rfq
    {
        $rfq = DB::transaction(function () use ($buyer, $data): Rfq {
            $items = collect($data['items']);
            $products = Product::query()->active()->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');

            $errors = [];
            foreach ($items as $i => $item) {
                $product = $products->get($item['product_id']);
                $lineErrors = $product === null
                    ? [['code' => 'product_unavailable', 'message' => __('messages.pricing.product_unavailable'), 'meta' => []]]
                    : $this->pricing->validateQuantity($product, (int) $item['quantity'], checkStock: false);

                if ($lineErrors !== []) {
                    $errors[] = ['index' => $i, 'product_id' => $item['product_id'], 'errors' => $lineErrors];
                }
            }

            if ($errors !== []) {
                throw BusinessRuleException::make('rfq_lines_invalid', [], 422, ['lines' => $errors]);
            }

            $company = $buyer->company;

            $rfq = new Rfq(['buyer_notes' => $data['buyer_notes'] ?? null]);
            $rfq->forceFill([
                'reference' => DocumentSequence::next('rfq'),
                'company_id' => $company->id,
                'requested_by' => $buyer->id,
                'assigned_to' => $company->account_manager_id,
                'status' => RfqStatus::Submitted,
            ])->save();

            $rfq->items()->createMany($items->map(fn (array $item) => [
                'product_id' => $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'target_unit_price' => $item['target_unit_price'] ?? null,
                'notes' => $item['notes'] ?? null,
            ])->all());

            AuditLog::record('rfq.submitted', $rfq);

            return $rfq->setRelation('company', $company);
        });

        $this->notifyStaff($rfq, new RfqSubmittedNotification($rfq));

        return $rfq;
    }

    public function startReview(Rfq $rfq, User $staff): Rfq
    {
        return DB::transaction(function () use ($rfq, $staff): Rfq {
            $rfq = $this->lock($rfq);
            $this->assertStatus($rfq, RfqStatus::Submitted);

            $rfq->forceFill([
                'status' => RfqStatus::UnderReview,
                'assigned_to' => $rfq->assigned_to ?? $staff->id,
            ])->save();

            return $rfq;
        });
    }

    /**
     * @param  array<int, string>  $prices  rfq_item_id => quoted unit price
     */
    public function quote(Rfq $rfq, User $staff, array $prices, string $validUntil, ?string $quoteNotes, ?string $internalNotes = null): Rfq
    {
        $rfq = DB::transaction(function () use ($rfq, $staff, $prices, $validUntil, $quoteNotes, $internalNotes): Rfq {
            $rfq = $this->lock($rfq);
            $this->assertStatus($rfq, RfqStatus::Submitted, RfqStatus::UnderReview, RfqStatus::Quoted);

            $items = $rfq->items()->get()->keyBy('id');

            if (array_diff($items->keys()->all(), array_keys($prices)) !== [] || array_diff(array_keys($prices), $items->keys()->all()) !== []) {
                throw BusinessRuleException::make('rfq_quote_incomplete');
            }

            $validUntil = Carbon::parse($validUntil)->startOfDay();
            if ($validUntil->isBefore(today())) {
                throw BusinessRuleException::make('rfq_validity_past');
            }

            foreach ($prices as $itemId => $price) {
                if (! Money::isPositive($price)) {
                    throw BusinessRuleException::make('rfq_price_invalid');
                }

                /** @var RfqItem $item */
                $item = $items->get($itemId);
                $item->forceFill(['quoted_unit_price' => Money::format(Money::of($price))])->save();
            }

            $rfq->forceFill([
                'status' => RfqStatus::Quoted,
                'quoted_by' => $staff->id,
                'quoted_at' => now(),
                'assigned_to' => $rfq->assigned_to ?? $staff->id,
                'valid_until' => $validUntil->toDateString(),
                'quote_notes' => $quoteNotes,
                'internal_notes' => $internalNotes ?? $rfq->internal_notes,
            ])->save();

            AuditLog::record('rfq.quoted', $rfq, [], ['prices' => $prices, 'valid_until' => $rfq->valid_until->toDateString()]);

            return $rfq;
        });

        Notification::send($rfq->requester()->active()->get(), new RfqQuotedNotification($rfq));

        return $rfq;
    }

    /** Staff declines to quote. */
    public function decline(Rfq $rfq, User $staff, string $reason): Rfq
    {
        $rfq = DB::transaction(function () use ($rfq, $staff, $reason): Rfq {
            $rfq = $this->lock($rfq);
            $this->assertStatus($rfq, RfqStatus::Submitted, RfqStatus::UnderReview);

            $rfq->forceFill(['status' => RfqStatus::Rejected, 'quote_notes' => $reason, 'quoted_by' => $staff->id])->save();
            AuditLog::record('rfq.declined', $rfq, [], ['reason' => $reason]);

            return $rfq;
        });

        Notification::send($rfq->requester()->active()->get(), new RfqDeclinedNotification($rfq));

        return $rfq;
    }

    public function accept(Rfq $rfq, User $buyer): Rfq
    {
        return $this->respond($rfq, $buyer, RfqStatus::Accepted);
    }

    public function reject(Rfq $rfq, User $buyer): Rfq
    {
        return $this->respond($rfq, $buyer, RfqStatus::Rejected);
    }

    public function cancel(Rfq $rfq, User $buyer): Rfq
    {
        return DB::transaction(function () use ($rfq, $buyer): Rfq {
            $rfq = $this->lock($rfq);
            $this->assertStatus($rfq, RfqStatus::Submitted, RfqStatus::UnderReview);

            $rfq->forceFill(['status' => RfqStatus::Cancelled, 'responded_at' => now()])->save();
            AuditLog::record('rfq.cancelled', $rfq, [], ['by' => $buyer->id]);

            return $rfq;
        });
    }

    /** Scheduled: quoted RFQs past their validity date become expired. Returns affected count. */
    public function expireDue(): int
    {
        return Rfq::query()
            ->where('status', RfqStatus::Quoted->value)
            ->whereDate('valid_until', '<', today()->toDateString())
            ->update(['status' => RfqStatus::Expired->value, 'updated_at' => now()]);
    }

    private function respond(Rfq $rfq, User $buyer, RfqStatus $to): Rfq
    {
        $rfq = DB::transaction(function () use ($rfq, $buyer, $to): Rfq {
            $rfq = $this->lock($rfq);
            $this->assertStatus($rfq, RfqStatus::Quoted);

            if ($rfq->isExpired()) {
                $rfq->forceFill(['status' => RfqStatus::Expired])->save();

                throw BusinessRuleException::make('rfq_expired');
            }

            $rfq->forceFill(['status' => $to, 'responded_at' => now()])->save();
            AuditLog::record('rfq.'.$to->value, $rfq, [], ['by' => $buyer->id]);

            return $rfq;
        });

        $this->notifyStaff($rfq->load('company'), new RfqRespondedNotification($rfq));

        return $rfq;
    }

    private function lock(Rfq $rfq): Rfq
    {
        return Rfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();
    }

    private function assertStatus(Rfq $rfq, RfqStatus ...$allowed): void
    {
        if (! in_array($rfq->status, $allowed, true)) {
            throw BusinessRuleException::make('rfq_invalid_transition', ['status' => $rfq->status->label()]);
        }
    }

    /** Assigned account manager, otherwise every active staff member able to quote. */
    private function notifyStaff(Rfq $rfq, object $notification): void
    {
        $recipients = $rfq->assigned_to
            ? User::query()->whereKey($rfq->assigned_to)->active()->get()
            : User::query()->staff()->active()->permission('rfqs.quote')->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }
}
