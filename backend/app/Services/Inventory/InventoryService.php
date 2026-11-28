<?php

namespace App\Services\Inventory;

use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
}
