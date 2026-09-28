<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Models\LoginLog;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $companyUsers = CompanyUser::with(['user', 'roleObj'])
            ->where('company_id', $companyId)
            ->get();

        $roles = Role::where('company_id', $companyId)->get();

        return response()->json([
            'success' => true,
            'data' => $companyUsers,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:ADMIN,ACCOUNTANT,AUDITOR,VIEWER'],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
        }

        $exists = CompanyUser::where('company_id', $companyId)->where('user_id', $user->id)->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'هذا المستخدم مرتبط بالشركة بالفعل.',
            ], 422);
        }

        $companyUser = CompanyUser::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'role' => $validated['role'],
            'role_id' => $validated['role_id'] ?? null,
            'is_default' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المستخدم للشركة بنجاح.',
            'data' => $companyUser->load(['user', 'roleObj']),
        ], 201);
    }

    public function logins(Request $request, int $id): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();

        if (! $this->isCompanyAdmin($request->user(), $companyId)) {
            return response()->json([
                'success' => false,
                'message' => 'سجل الدخول متاح لمدير الشركة فقط.',
            ], 403);
        }

        $companyUser = CompanyUser::where('company_id', $companyId)->findOrFail($id);

        $logins = LoginLog::where('company_id', $companyId)
            ->where('user_id', $companyUser->user_id)
            ->latest()
            ->limit(50)
            ->get(['id', 'ip_address', 'user_agent', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $logins,
        ]);
    }

    private function isCompanyAdmin(User $actor, ?int $companyId): bool
    {
        if ($actor->is_super_admin) {
            return true;
        }

        $role = CompanyUser::where('company_id', $companyId)
            ->where('user_id', $actor->id)
            ->value('role');

        return in_array($role, ['COMPANY_ADMIN', 'ADMIN', 'SUPER_ADMIN'], true);
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $companyId = CompanyContextService::getCompanyId();
        $actor = $request->user();

        if (! $this->isCompanyAdmin($actor, $companyId)) {
            return response()->json([
                'success' => false,
                'message' => 'تغيير حالة المستخدمين متاح لمدير الشركة فقط.',
            ], 403);
        }

        $companyUser = CompanyUser::where('company_id', $companyId)->findOrFail($id);

        if ($companyUser->user_id === $actor->id) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك إيقاف حسابك بنفسك.',
            ], 422);
        }

        $companyUser->update(['is_active' => ! $companyUser->is_active]);

        return response()->json([
            'success' => true,
            'message' => $companyUser->is_active ? 'تم تفعيل المستخدم.' : 'تم إيقاف المستخدم.',
            'data' => $companyUser->load(['user', 'roleObj']),
        ]);
    }
}
