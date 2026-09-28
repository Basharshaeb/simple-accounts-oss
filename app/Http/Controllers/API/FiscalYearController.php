<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Services\CompanyContextService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FiscalYearController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $years = FiscalYear::with('periods')
            ->where('company_id', $companyId)
            ->orderByDesc('year')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $years,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $validated = $request->validate([
            'year' => [
                'required',
                'integer',
                'between:2000,2100',
                Rule::unique('fiscal_years', 'year')->where('company_id', $companyId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ], [
            'year.required' => 'يرجى تحديد السنة المالية.',
            'year.unique' => 'السنة المالية ('.$request->input('year').') مضافة وموجودة بالفعل لهذه الشركة، لا يمكن تكرار إنشاء نفس السنة المالية.',
            'start_date.required' => 'يرجى إدخال تاريخ بداية السنة المالية.',
            'end_date.required' => 'يرجى إدخال تاريخ نهاية السنة المالية.',
            'end_date.after' => 'تاريخ نهاية السنة المالية يجب أن يكون بعد تاريخ البداية.',
        ]);

        return DB::transaction(function () use ($companyId, $validated) {
            $year = FiscalYear::create([
                'company_id' => $companyId,
                'year' => $validated['year'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => 'OPEN',
            ]);

            // Generate 12 monthly periods automatically
            $startDate = Carbon::parse($validated['start_date']);

            for ($i = 1; $i <= 12; $i++) {
                $periodStart = $startDate->copy()->addMonths($i - 1)->startOfMonth();
                $periodEnd = $startDate->copy()->addMonths($i - 1)->endOfMonth();

                FiscalPeriod::create([
                    'fiscal_year_id' => $year->id,
                    'company_id' => $companyId,
                    'period_number' => $i,
                    'name' => 'فترة '.$i.' ('.$periodStart->format('M Y').')',
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodEnd->toDateString(),
                    'status' => 'OPEN',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء السنة المالية والفترات الشهرية بنجاح.',
                'data' => $year->load('periods'),
            ], 201);
        });
    }

    public function closePeriod(int $periodId): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $period = FiscalPeriod::where('company_id', $companyId)->findOrFail($periodId);

        if ($period->status === 'CLOSED') {
            return response()->json([
                'success' => false,
                'message' => 'الفترة المالية مغلقة بالفعل.',
            ], 422);
        }

        $period->update(['status' => 'CLOSED']);

        return response()->json([
            'success' => true,
            'message' => 'تم إقفال الفترة المالية بنجاح.',
            'data' => $period,
        ]);
    }
}
