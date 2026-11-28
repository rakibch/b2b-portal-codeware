<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\LedgerEntry;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Statement of account export. All text cells are neutralised against spreadsheet formula injection. */
class LedgerStatementExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        private readonly Company $company,
        private readonly array $statement,
        private readonly string $locale,
    ) {}

    public function headings(): array
    {
        return [
            __('pdf.issue_date', [], $this->locale),
            __('messages.export.type', [], $this->locale),
            __('messages.export.reference', [], $this->locale),
            __('pdf.description', [], $this->locale),
            __('messages.export.debit', [], $this->locale),
            __('messages.export.credit', [], $this->locale),
            __('messages.export.balance', [], $this->locale),
        ];
    }

    public function array(): array
    {
        $rows = [[
            $this->statement['from'],
            '',
            '',
            self::safe(__('messages.export.opening_balance', [], $this->locale)),
            '',
            '',
            (float) $this->statement['opening_balance'],
        ]];

        /** @var LedgerEntry $entry */
        foreach ($this->statement['entries'] as $entry) {
            $rows[] = [
                $entry->entry_date->toDateString(),
                self::safe(__('enums.ledger_entry_type.'.$entry->type->value, [], $this->locale)),
                self::safe((string) $entry->reference),
                self::safe((string) $entry->getTranslation('description', $this->locale)),
                (float) $entry->debit,
                (float) $entry->credit,
                (float) $entry->balance,
            ];
        }

        $rows[] = [
            $this->statement['to'],
            '',
            '',
            self::safe(__('messages.export.closing_balance', [], $this->locale)),
            (float) $this->statement['total_debit'],
            (float) $this->statement['total_credit'],
            (float) $this->statement['closing_balance'],
        ];

        return $rows;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $this->company->name), 0, 31) ?: 'Statement';
    }

    /** Prefix values that a spreadsheet would interpret as a formula (CSV/Excel injection). */
    public static function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
