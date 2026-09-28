<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CashBox;
use App\Services\CompanyContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashBoxController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $cashBoxes = CashBox::with(['branch', 'account.currencies'])
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cashBoxes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'exists:accounts,id'],
            'keeper_name' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $exists = CashBox::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'كود الصندوق متكرر لهذ الشركة.',
            ], 422);
        }

        $isDefault = (bool) ($validated['is_default'] ?? false);
        if ($isDefault) {
            CashBox::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->update(['is_default' => false]);
        }

        $cashBox = CashBox::create(array_merge($validated, [
            'company_id' => $companyId,
            'is_active' => true,
            'is_default' => $isDefault,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الصندوق بنجاح.',
            'data' => $cashBox->load(['branch', 'account']),
        ], 201);
    }
}
