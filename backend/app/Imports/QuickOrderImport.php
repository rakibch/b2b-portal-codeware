<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;

/**
 * Reads "sku, quantity" rows. Values are treated as plain data only (no formulas evaluated,
 * nothing persisted); QuickOrderService does matching and validation.
 */
class QuickOrderImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithLimit
{
    /** @var Collection<int, Collection> */
    public Collection $rows;

    public function __construct(private readonly int $maxRows)
    {
        $this->rows = collect();
    }

    public function collection(Collection $collection): void
    {
        $this->rows = $collection;
    }

    /** One extra row so the service can detect (and reject) oversized files. */
    public function limit(): int
    {
        return $this->maxRows + 1;
    }
}
