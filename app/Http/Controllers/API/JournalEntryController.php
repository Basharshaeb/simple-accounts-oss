<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\CompanyContextService;
use App\Services\DocumentNumberingService;
use App\Services\JournalEntryService;
use App\Services\PostingService;
use App\Services\ReversalService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $query = JournalEntry::with(['currency', 'createdBy', 'postedBy', 'branch'])
            ->where('company_id', $companyId);

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->query('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->query('from_date')) {
            $query->where('entry_date', '>=', $request->query('from_date'));
        }

        if ($request->query('to_date')) {
            $query->where('entry_date', '<=', $request->query('to_date'));
        }

        if ($request->query('account_id')) {
            $accountId = $request->query('account_id');
            $query->whereHas('lines', function ($lineQ) use ($accountId) {
                $lineQ->where('account_id', $accountId);
            });
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('lines.account', function ($accQ) use ($search) {
                        $accQ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $entries = $query->orderByDesc('entry_date')->orderByDesc('id')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    public function store(Request $request, JournalEntryService $service): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['required', 'exists:branches,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'auto_post' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.currency_id' => ['nullable', 'exists:currencies,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.exchange_rate' => ['nullable', 'numeric', 'gt:0'],
        ]);

        try {
            $entry = $service->create(array_merge($validated, [
                'company_id' => $companyId,
                'created_by' => $request->user()->id,
            ]), autoPost: $request->boolean('auto_post', false));

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء القيد المحاسبي بنجاح.',
                'data' => $entry->load(['lines.account', 'lines.currency', 'currency', 'branch']),
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

        $entry = JournalEntry::with(['lines.account', 'lines.currency', 'currency', 'createdBy', 'postedBy', 'fiscalPeriod', 'branch'])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $entry,
        ]);
    }

    public function post(int $id, PostingService $service, Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $entry = JournalEntry::where('company_id', $companyId)->findOrFail($id);

        try {
            $postedEntry = $service->postJournalEntry($entry, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم اعتماد القيد المحاسبي بنجاح.',
                'data' => $postedEntry->load(['lines.account', 'currency']),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reverse(int $id, ReversalService $service, Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $entry = JournalEntry::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $reversalEntry = $service->reverseJournalEntry($entry, $request->user()->id, $validated['reason'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء القيد وتوليد قيد عكسي بنجاح.',
                'data' => $reversalEntry->load(['lines.account', 'currency']),
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

        $number = DocumentNumberingService::generate($companyId, 'JE', $year);

        return response()->json([
            'success' => true,
            'data' => $number,
        ]);
    }
}
