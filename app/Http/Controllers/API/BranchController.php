<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\CompanyContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $branches = Branch::where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $branches,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        $exists = Branch::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'كود الفرع متكرر لـ هذه الشركة.',
            ], 422);
        }

        $branch = Branch::create(array_merge($validated, [
            'company_id' => $companyId,
            'is_active' => true,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الفرع بنجاح.',
            'data' => $branch,
        ], 201);
    }
}
