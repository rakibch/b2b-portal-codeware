<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([...InvoiceStatus::values(), 'outstanding', 'overdue'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        abort_unless($request->user()->can('invoices.view_company'), 403);

        $query = Invoice::query()->where('company_id', $request->user()->company_id)->with('order:id,order_number');

        match ($validated['status'] ?? null) {
            null => null,
            'outstanding' => $query->outstanding(),
            'overdue' => $query->overdue(),
            default => $query->where('status', $validated['status']),
        };

        return InvoiceResource::collection($query->latest('issue_date')->latest('id')->paginate((int) ($validated['per_page'] ?? 20)));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        Gate::authorize('view', $invoice);

        return new InvoiceResource($invoice->load('order:id,order_number'));
    }
}
