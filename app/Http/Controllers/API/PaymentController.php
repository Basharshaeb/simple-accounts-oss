<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\CompanyContextService;
use App\Services\DocumentNumberingService;
use App\Services\PaymentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $query = Payment::with(['account', 'cashOrBankAccount', 'currency', 'journalEntry', 'branch', 'cashBox'])
            ->where('company_id', $companyId);

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->query('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->query('account_id')) {
            $accountId = $request->query('account_id');
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)
                    ->orWhereHas('journalEntry.lines', function ($lineQ) use ($accountId) {
                        $lineQ->where('account_id', $accountId);
                    });
            });
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('account', function ($accQ) use ($search) {
                        $accQ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('journalEntry.lines.account', function ($accQ) use ($search) {
                        $accQ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    public function store(Request $request, PaymentService $service): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'branch_id' => ['required', 'exists:branches,id'],
            'cash_box_id' => ['required', 'exists:cash_boxes,id'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'cash_or_bank_account_id' => ['required', 'exists:accounts,id'],
            'amount' => ['nullable', 'numeric', 'gte:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'in:CASH,BANK_TRANSFER,CHEQUE,OTHER'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'auto_post' => ['nullable', 'boolean'],
            'lines' => ['nullable', 'array'],
            'lines.*.account_id' => ['required_with:lines', 'exists:accounts,id'],
            'lines.*.amount' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.description' => ['nullable', 'string'],
        ]);

        try {
            $payment = $service->create(array_merge($validated, [
                'company_id' => $companyId,
                'created_by' => $request->user()->id,
            ]), autoPost: $request->boolean('auto_post', false));

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء سند الصرف بنجاح.',
                'data' => $payment->load(['account', 'cashOrBankAccount', 'currency', 'journalEntry']),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $payment = Payment::with(['account', 'cashOrBankAccount', 'currency', 'journalEntry.lines.account'])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    public function post(int $id, PaymentService $service, Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $payment = Payment::where('company_id', $companyId)->findOrFail($id);

        try {
            $postedPayment = $service->post($payment, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم اعتماد سند الصرف وتوليد القيد المحاسبي بنجاح.',
                'data' => $postedPayment->load(['account', 'cashOrBankAccount', 'currency', 'journalEntry']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function nextNumber(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();
        $date = $request->query('date', now()->toDateString());
        $year = (int) date('Y', strtotime($date));

        $number = DocumentNumberingService::generate($companyId, 'PV', $year);

        return response()->json([
            'success' => true,
            'data' => $number,
        ]);
    }
}
