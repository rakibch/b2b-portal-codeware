<?php

namespace App\Services\Cart;

use App\Exceptions\BusinessRuleException;
use App\Imports\QuickOrderImport;
use App\Models\Company;
use App\Models\Product;
use App\Services\Pricing\PricingEngineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuickOrderService
{
    public function __construct(private readonly PricingEngineService $pricing) {}

    /**
     * Matches rows (by product_id or SKU), aggregates duplicates and prices them in one batch.
     *
     * @param  list<array{row?: int, sku?: ?string, product_id?: ?int, quantity: int|string|null}>  $rows
     * @return array{matched: list<array>, unmatched: list<array>, summary: array}
     */
    public function resolve(array $rows, ?Company $company): array
    {
        $normalised = [];
        $unmatched = [];

        foreach (array_values($rows) as $index => $row) {
            $rowNumber = (int) ($row['row'] ?? $index + 1);
            $sku = isset($row['sku']) ? strtoupper(trim((string) $row['sku'])) : null;
            $productId = isset($row['product_id']) && is_numeric($row['product_id']) ? (int) $row['product_id'] : null;
            $rawQty = $row['quantity'] ?? null;

            if (($sku === null || $sku === '') && $productId === null) {
                $unmatched[] = ['row' => $rowNumber, 'sku' => $sku, 'quantity' => $rawQty, 'reason' => 'missing_sku', 'message' => __('messages.quick_order.missing_sku')];

                continue;
            }

            if (! is_numeric($rawQty) || (int) $rawQty != $rawQty || (int) $rawQty <= 0) {
                $unmatched[] = ['row' => $rowNumber, 'sku' => $sku, 'quantity' => $rawQty, 'reason' => 'invalid_quantity', 'message' => __('messages.quick_order.invalid_quantity')];

                continue;
            }

            $normalised[] = ['row' => $rowNumber, 'sku' => $sku, 'product_id' => $productId, 'quantity' => (int) $rawQty];
        }

        $products = $this->findProducts($normalised);

        // Aggregate duplicates per product (keeps first row number for display).
        $aggregated = [];
        foreach ($normalised as $row) {
            $product = $row['product_id'] !== null
                ? $products['by_id']->get($row['product_id'])
                : $products['by_sku']->get($row['sku']);

            if ($product === null) {
                $unmatched[] = ['row' => $row['row'], 'sku' => $row['sku'], 'quantity' => $row['quantity'], 'reason' => 'not_found', 'message' => __('messages.quick_order.not_found')];

                continue;
            }

            if (isset($aggregated[$product->id])) {
                $aggregated[$product->id]['quantity'] += $row['quantity'];
                $aggregated[$product->id]['rows'][] = $row['row'];
            } else {
                $aggregated[$product->id] = ['product' => $product, 'quantity' => $row['quantity'], 'rows' => [$row['row']]];
            }
        }

        $quotes = $this->pricing->quoteMany(
            array_map(fn (array $a) => [$a['product'], $a['quantity']], array_values($aggregated)),
            $company,
        );

        $matched = [];
        foreach (array_values($aggregated) as $i => $line) {
            $matched[] = ['rows' => $line['rows'], 'product' => $line['product'], 'quote' => $quotes[$i]];
        }

        usort($unmatched, fn ($a, $b) => $a['row'] <=> $b['row']);

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'summary' => [
                'total_rows' => count($rows),
                'matched' => count($matched),
                'valid' => count(array_filter($matched, fn ($m) => $m['quote']->isValid())),
                'unmatched' => count($unmatched),
            ],
        ];
    }

    /** Parses an uploaded CSV/XLSX and resolves it. */
    public function import(UploadedFile $file, ?Company $company): array
    {
        $maxRows = (int) config('b2b.import.max_rows', 500);
        $import = new QuickOrderImport($maxRows);

        Excel::import($import, $file);

        if ($import->rows->count() > $maxRows) {
            throw BusinessRuleException::make('import_too_many_rows', ['max' => $maxRows]);
        }

        $first = $import->rows->first();
        if ($first !== null && ! $first->has('sku') && ! $first->has('quantity') && ! $first->has('qty')) {
            throw BusinessRuleException::make('import_invalid_headers');
        }

        $rows = $import->rows->values()->map(fn (Collection $row, int $i) => [
            'row' => $i + 2, // +1 heading row, +1 one-based
            'sku' => $row->get('sku'),
            'quantity' => $row->get('quantity', $row->get('qty')),
        ])->all();

        if ($rows === []) {
            throw BusinessRuleException::make('import_empty');
        }

        return $this->resolve($rows, $company);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['sku', 'quantity'], escape: '');
            fputcsv($out, ['RIC-MIN-25', '100'], escape: '');
            fputcsv($out, ['OIL-SOY-5L', '40'], escape: '');
            fclose($out);
        }, 'quick-order-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{by_id: Collection<int, Product>, by_sku: Collection<string, Product>} */
    private function findProducts(array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_column($rows, 'product_id'))));
        $skus = array_values(array_unique(array_filter(array_column($rows, 'sku'))));

        if ($ids === [] && $skus === []) {
            return ['by_id' => collect(), 'by_sku' => collect()];
        }

        $products = Product::query()
            ->active()
            ->where(fn ($q) => $q->whereIn('id', $ids)->orWhereIn('sku', $skus))
            ->with(['unit', 'priceTiers'])
            ->get();

        return [
            'by_id' => $products->keyBy('id'),
            'by_sku' => $products->keyBy(fn (Product $p) => strtoupper($p->sku)),
        ];
    }
}
