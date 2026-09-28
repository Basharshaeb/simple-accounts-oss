<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Services\CompanyContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $rates = ExchangeRate::with('currency')
            ->where('company_id', $companyId)
            ->orderByDesc('rate_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rates,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $validated = $request->validate([
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate_date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $rate = ExchangeRate::updateOrCreate([
            'company_id' => $companyId,
            'currency_id' => $validated['currency_id'],
            'rate_date' => $validated['rate_date'],
        ], [
            'rate' => $validated['rate'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ سعر الصرف بنجاح.',
            'data' => $rate->load('currency'),
        ]);
    }
}
