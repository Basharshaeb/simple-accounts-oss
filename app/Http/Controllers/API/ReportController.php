<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\CompanyContextService;
use App\Services\FinancialReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function trialBalance(Request $request, FinancialReportService $service): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;

        $report = $service->getTrialBalance($companyId, $fromDate, $toDate, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function accountStatement(Request $request, FinancialReportService $service): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
        ]);

        $accountId = (int) $request->query('account_id');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;

        $report = $service->getAccountStatement($companyId, $accountId, $fromDate, $toDate, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function incomeStatement(Request $request, FinancialReportService $service): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;

        $report = $service->getIncomeStatement($companyId, $fromDate, $toDate, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function balanceSheet(Request $request, FinancialReportService $service): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $asOfDate = $request->query('as_of_date');
        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;

        $report = $service->getBalanceSheet($companyId, $asOfDate, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function dashboard(FinancialReportService $service): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $summary = $service->getDashboardSummary($companyId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
