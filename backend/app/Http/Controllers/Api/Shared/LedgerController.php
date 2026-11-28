<?php

namespace App\Http\Controllers\Api\Shared;

use App\Exports\LedgerStatementExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StatementRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\LedgerEntryResource;
use App\Models\Company;
use App\Services\Finance\CreditLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Statement of account — buyers for their own company (/buyer/ledger), staff per company. */
class LedgerController extends Controller
{
    public function __construct(private readonly CreditLedgerService $ledger) {}

    public function buyerStatement(StatementRequest $request): JsonResponse
    {
        return $this->statement($request, $request->user()->company()->firstOrFail());
    }

    public function buyerExport(StatementRequest $request): BinaryFileResponse
    {
        return $this->export($request, $request->user()->company()->firstOrFail());
    }

    public function companyStatement(StatementRequest $request, Company $company): JsonResponse
    {
        return $this->statement($request, $company);
    }

    public function companyExport(StatementRequest $request, Company $company): BinaryFileResponse
    {
        return $this->export($request, $company);
    }

    private function statement(StatementRequest $request, Company $company): JsonResponse
    {
        Gate::authorize('viewLedger', $company);

        $statement = $this->ledger->statement($company, $request->from(), $request->to());

        return response()->json([
            'company' => new CompanyResource($company),
            'from' => $statement['from'],
            'to' => $statement['to'],
            'opening_balance' => $statement['opening_balance'],
            'closing_balance' => $statement['closing_balance'],
            'total_debit' => $statement['total_debit'],
            'total_credit' => $statement['total_credit'],
            'entries' => LedgerEntryResource::collection($statement['entries']),
        ]);
    }

    private function export(StatementRequest $request, Company $company): BinaryFileResponse
    {
        Gate::authorize('viewLedger', $company);

        $statement = $this->ledger->statement($company, $request->from(), $request->to());
        $format = $request->validated('format') === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;
        $extension = $format === ExcelFormat::CSV ? 'csv' : 'xlsx';

        return Excel::download(
            new LedgerStatementExport($company, $statement, app()->getLocale()),
            "statement-{$statement['from']}-{$statement['to']}.{$extension}",
            $format,
        );
    }
}
