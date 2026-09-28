<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\CompanyContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'company_code' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة (البريد أو كلمة السر).',
            ], 401);
        }

        $company = null;
        if (! empty($credentials['company_code'])) {
            $company = Company::where('code', $credentials['company_code'])->first();
            if (! $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'رمز / كود الشركة غير صحيح.',
                ], 422);
            }

            $membership = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->first();

            if (! $membership) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المستخدم ليس لديه صلاحية الوصول للشركة المحددة.',
                ], 403);
            }

            if (! $membership->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'تم إيقاف حسابك في هذه الشركة. تواصل مع مدير الشركة.',
                ], 403);
            }
        }

        $token = $user->createToken('accounting_auth_token')->plainTextToken;

        $targetCompanyUser = null;
        if ($company) {
            $targetCompanyUser = CompanyUser::with('company.baseCurrency')
                ->where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->first();
        } else {
            $targetCompanyUser = CompanyUser::with('company.baseCurrency')
                ->where('user_id', $user->id)
                ->orderByDesc('is_default')
                ->first();
        }

        $loginLog = LoginLog::record($user, $targetCompanyUser?->company_id, $request);

        $targetCompanyUser?->update([
            'last_login_at' => $loginLog->created_at,
            'last_login_ip' => $loginLog->ip_address,
            'last_seen_at' => $loginLog->created_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'current_company' => $targetCompanyUser ? $targetCompanyUser->company : null,
                'role' => $targetCompanyUser ? $targetCompanyUser->role : null,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyUsers = CompanyUser::with('company.baseCurrency')
            ->where('user_id', $user->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'companies' => $companyUsers->map(fn ($cu) => [
                    'company' => $cu->company,
                    'role' => $cu->role,
                    'is_default' => $cu->is_default,
                ]),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        CompanyUser::where('user_id', $request->user()->id)
            ->where('company_id', CompanyContextService::getCompanyId())
            ->update(['last_seen_at' => null]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }
}
