<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerGroupResource;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Services\Dashboard\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AdminDashboardService $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->summary($request->user())]);
    }

    /** Option lists for KYC approval / company terms (groups + eligible account managers). */
    public function lookups(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['kyc.review', 'companies.view', 'credit.manage']), 403);

        return response()->json(['data' => [
            'customer_groups' => CustomerGroupResource::collection(CustomerGroup::query()->active()->orderBy('priority')->get()),
            'account_managers' => User::query()
                ->staff()
                ->active()
                ->role([Role::SalesManager->value, Role::SuperAdmin->value])
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $u) => $u->only(['id', 'name', 'email'])),
        ]]);
    }
}
