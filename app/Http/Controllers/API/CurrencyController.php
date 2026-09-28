<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\CompanyContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();
        $company = $companyId ? Company::find($companyId) : null;
        $baseCurrencyId = $company ? $company->base_currency_id : null;

        $currencies = Currency::where('is_active', true)->get()->map(function ($curr) use ($companyId, $baseCurrencyId) {
            $isBase = ($curr->id == $baseCurrencyId);

            $latestRate = null;
            if ($companyId) {
                $latestRateObj = ExchangeRate::where('company_id', $companyId)
                    ->where('currency_id', $curr->id)
                    ->orderByDesc('rate_date')
                    ->orderByDesc('id')
                    ->first();
                $latestRate = $latestRateObj ? (float) $latestRateObj->rate : null;
            }

            return [
                'id' => $curr->id,
                'code' => $curr->code,
                'name' => $curr->name,
                'symbol' => $curr->symbol,
                'decimal_places' => $curr->decimal_places,
                'is_base' => $isBase,
                'exchange_rate' => $isBase ? 1.000000 : ($latestRate ?? 1.000000),
            ];
        });

        return response()->json([
            'success' => true,
            'base_currency_id' => $baseCurrencyId,
            'data' => $currencies,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();
        $company = $companyId ? Company::find($companyId) : null;

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'between:0,6'],
            'exchange_rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $currency = Currency::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'decimal_places' => $validated['decimal_places'],
            'is_active' => true,
        ]);

        $isBase = $company && ($company->base_currency_id == $currency->id);
        $rateVal = $isBase ? 1.000000 : (float) $validated['exchange_rate'];

        if ($companyId) {
            ExchangeRate::create([
                'company_id' => $companyId,
                'currency_id' => $currency->id,
                'rate_date' => now()->toDateString(),
                'rate' => $rateVal,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العملة بنجاح.',
            'data' => array_merge($currency->toArray(), [
                'is_base' => $isBase,
                'exchange_rate' => $rateVal,
            ]),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();
        $company = $companyId ? Company::find($companyId) : null;

        $currency = Currency::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:currencies,code,'.$id],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'between:0,6'],
            'exchange_rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $currency->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'symbol' => $validated['symbol'],
            'decimal_places' => $validated['decimal_places'],
        ]);

        $isBase = $company && ($company->base_currency_id == $currency->id);
        $rateVal = $isBase ? 1.000000 : (float) $validated['exchange_rate'];

        if ($companyId) {
            ExchangeRate::create([
                'company_id' => $companyId,
                'currency_id' => $currency->id,
                'rate_date' => now()->toDateString(),
                'rate' => $rateVal,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات العملة وسعر الصرف بنجاح.',
            'data' => array_merge($currency->toArray(), [
                'is_base' => $isBase,
                'exchange_rate' => $rateVal,
            ]),
        ]);
    }
}
