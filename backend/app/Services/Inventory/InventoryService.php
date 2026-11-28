<?php

namespace App\Services\Inventory;

use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/** All stock mutations go through here, always under a row lock. */
class InventoryService
{
    /** Manual stock adjustment (goods received, count correction, damage). Delta may be negative. */
    public function adjust(Product $product, int $delta, string $reason, User $actor): Product
    {
        return DB::transaction(function () use ($product, $delta, $reason, $actor): Product {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = $locked->stock_quantity;
            $after = $before + $delta;

            if ($after < 0) {
                throw BusinessRuleException::make('stock_negative', ['available' => $before]);
            }

            $locked->forceFill(['stock_quantity' => $after])->save();

            AuditLog::record('inventory.adjusted', $locked, ['stock_quantity' => $before], [
                'stock_quantity' => $after,
                'delta' => $delta,
                'reason' => $reason,
                'by' => $actor->id,
            ]);

            return $locked;
        });
    }

    /**
     * Row-locks products in ascending id order (consistent lock ordering avoids deadlocks between
     * concurrent checkouts). Must run inside the checkout transaction.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Product> keyed by id
     */
    public function lockProducts(array $productIds): Collection
    {
        $this->assertInTransaction();

        $ids = array_values(array_unique(array_map('intval', $productIds)));
        sort($ids);

        return Product::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * Decrements stock for locked products. The conditional UPDATE is a second line of defence:
     * it can never take stock below zero even if a caller forgot to validate.
     *
     * @param  array<int, int>  $quantities  product_id => quantity
     */
    public function reserve(array $quantities): void
    {
        $this->assertInTransaction();
        ksort($quantities);

        foreach ($quantities as $productId => $qty) {
            $affected = Product::query()
                ->whereKey($productId)
                ->where('stock_quantity', '>=', $qty)
                ->decrement('stock_quantity', $qty);

            if ($affected !== 1) {
                throw BusinessRuleException::make('stock_changed', [], 409, ['product_id' => $productId]);
            }
        }
    }

    /** @param array<int, int> $quantities product_id => quantity to put back */
    public function restore(array $quantities): void
    {
        $this->assertInTransaction();
        ksort($quantities);

        foreach ($quantities as $productId => $qty) {
            if ($qty > 0) {
                Product::withTrashed()->whereKey($productId)->increment('stock_quantity', $qty);
            }
        }
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Inventory mutations must run inside a database transaction.');
        }
    }
}
