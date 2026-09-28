<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات دخول مدير النظام غير صحيحة (البريد الإلكتروني أو كلمة السر).',
            ], 401);
        }

        if (! $user->is_super_admin) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، هذا الحساب ليس لديه صلاحيات مدير النظام (SuperAdmin).',
            ], 403);
        }

        $token = $user->createToken('superadmin_auth_token', ['superadmin'])->plainTextToken;

        LoginLog::record($user, null, $request);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل دخول مدير النظام بنجاح.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_superadmin' => true,
                ],
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::where('status', 'ACTIVE')->count();
        $suspendedCompanies = Company::where('status', 'INACTIVE')->count();
        $totalUsers = User::count();
        $totalJournalEntries = JournalEntry::withoutGlobalScopes()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_companies' => $totalCompanies,
                'active_companies' => $activeCompanies,
                'suspended_companies' => $suspendedCompanies,
                'total_users' => $totalUsers,
                'total_journal_entries' => $totalJournalEntries,
                'system_health' => 'HEALTHY',
                'php_version' => PHP_VERSION,
            ],
        ]);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $newStatus = $company->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $company->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => sprintf('تم تغيير حالة الشركة (%s) إلى %s بنجاح.', $company->name, $newStatus === 'ACTIVE' ? 'نشطة' : 'معطلة'),
            'data' => $company,
        ]);
    }
}
