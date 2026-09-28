<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\CompanyContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'لم يتم تحديد الشركة الحالية.'], 400);
        }

        $type = $request->query('type');
        $isPostable = $request->query('is_postable');
        $tree = $request->boolean('tree', true);

        if (! $tree) {
            $query = Account::with(['currency', 'currencies', 'parent'])
                ->where('company_id', $companyId);

            if ($type) {
                $query->where('type', $type);
            }
            if ($isPostable !== null) {
                $query->where('is_postable', filter_var($isPostable, FILTER_VALIDATE_BOOLEAN));
            }

            return response()->json([
                'success' => true,
                'data' => $query->orderBy('code')->get(),
            ]);
        }

        // Return tree view starting from root accounts (parent_id is null)
        $withRelations = [
            'currency',
            'currencies',
            'children.currency',
            'children.currencies',
            'children.children.currency',
            'children.children.currencies',
            'children.children.children.currency',
            'children.children.children.currencies',
            'children.children.children.children.currency',
            'children.children.children.children.currencies',
        ];

        $roots = Account::with($withRelations)
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roots,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        // Sanitize parent_id if empty string
        if ($request->has('parent_id') && ($request->input('parent_id') === '' || $request->input('parent_id') === 'null')) {
            $request->merge(['parent_id' => null]);
        }

        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:ASSET,LIABILITY,EQUITY,REVENUE,EXPENSE'],
            'nature' => ['required', 'in:DEBIT,CREDIT'],
            'is_group' => ['required', 'boolean'],
            'is_postable' => ['required', 'boolean'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'currency_ids' => ['nullable', 'array'],
            'currency_ids.*' => ['exists:currencies,id'],
            'description' => ['nullable', 'string'],
        ]);

        // Validate code uniqueness per company
        $exists = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'رقم/كود الحساب متكرر لـ هذه الشركة.',
            ], 422);
        }

        $level = 1;
        if (! empty($validated['parent_id'])) {
            $parent = Account::where('company_id', $companyId)->findOrFail($validated['parent_id']);
            $level = $parent->level + 1;

            if (! $parent->is_group) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن إضافة حساب فرعي إلا تحت حساب تجميعي (Group Account).',
                ], 422);
            }
        }

        if ($validated['is_group'] && $validated['is_postable']) {
            return response()->json([
                'success' => false,
                'message' => 'الحساب التجميعي لا يمكن أن يكون حسابًا قابلًا للتسجيل المباشر.',
            ], 422);
        }

        $account = Account::create(array_merge($validated, [
            'company_id' => $companyId,
            'level' => $level,
            'is_active' => true,
        ]));

        if (! empty($validated['currency_ids'])) {
            $account->currencies()->sync($validated['currency_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحساب بنجاح.',
            'data' => $account->load(['currency', 'currencies']),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $account = Account::with(['parent', 'children', 'currency', 'currencies'])
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $account,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $account = Account::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'in:ASSET,LIABILITY,EQUITY,REVENUE,EXPENSE'],
            'nature' => ['sometimes', 'required', 'in:DEBIT,CREDIT'],
            'is_group' => ['sometimes', 'required', 'boolean'],
            'is_postable' => ['sometimes', 'required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'currency_ids' => ['nullable', 'array'],
            'currency_ids.*' => ['exists:currencies,id'],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($validated['code']) && $validated['code'] !== $account->code) {
            $exists = Account::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('code', $validated['code'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم/كود الحساب الجديد متكرر لهذه الشركة.',
                ], 422);
            }
        }

        if (isset($validated['is_group']) && $validated['is_group'] && $account->journalLines()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تحويل حساب مسجل عليه حركات إلى حساب تجميعي.',
            ], 422);
        }

        $account->update($validated);

        if (array_key_exists('currency_ids', $validated)) {
            $account->currencies()->sync($validated['currency_ids'] ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الحساب بنجاح.',
            'data' => $account->load(['currency', 'currencies']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $account = Account::where('company_id', $companyId)->findOrFail($id);

        if ($account->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف حساب يحتوي على حسابات فرعية.',
            ], 422);
        }

        if ($account->journalLines()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف حساب مسجل عليه حركات محاسبية، يمكن تعطيله فقط.',
            ], 422);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الحساب بنجاح.',
        ]);
    }
}
