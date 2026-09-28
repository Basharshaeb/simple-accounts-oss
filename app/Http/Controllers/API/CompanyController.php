<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = Company::with(['baseCurrency', 'companyUsers.user'])->get();

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->has('fiscal_year_start_month')) {
            $request->merge(['fiscal_year_start_month' => 1]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'min:2', 'max:50', 'alpha_dash', 'unique:companies,code'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'commercial_registration' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'base_currency_id' => ['required', 'exists:currencies,id'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ], [
            'name.required' => 'يرجى إدخال اسم الشركة الرئيسي.',
            'name.min' => 'اسم الشركة يجب أن يتكون من 3 أحرف على الأقل.',
            'code.required' => 'يرجى إدخال كود / رمز الشركة الفريد.',
            'code.alpha_dash' => 'كود الشركة يجب أن يتكون من أحرف وأرقام وشرطات فقط بدون مسافات.',
            'code.unique' => 'كود الشركة هذا مستخدم بالفعل لشركة أخرى، يرجى كتابة رمز فريد.',
            'base_currency_id.required' => 'يرجى اختيار العملة الأساسية للشركة.',
            'base_currency_id.exists' => 'العملة المختارة غير موجودة بالنظام.',
            'email.email' => 'البريد الإلكتروني للشركة صيغته غير صحيحة.',
        ]);

        $company = Company::create($validated);

        // 1. Create Default Dedicated Admin User for this Company
        $cleanCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $company->code));
        $defaultEmail = 'admin.'.$cleanCode.'@accounts.com';

        $defaultUser = User::firstOrCreate(
            ['email' => $defaultEmail],
            [
                'name' => 'مدير '.$company->name,
                'password' => Hash::make('password123'),
            ]
        );

        CompanyUser::create([
            'user_id' => $defaultUser->id,
            'company_id' => $company->id,
            'role' => 'COMPANY_ADMIN',
            'is_default' => true,
        ]);

        // 2. Assign current user as COMPANY_ADMIN if different
        if ($request->user() && $request->user()->id !== $defaultUser->id) {
            CompanyUser::create([
                'user_id' => $request->user()->id,
                'company_id' => $company->id,
                'role' => 'COMPANY_ADMIN',
                'is_default' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الشركة والمستخدم الافتراضي بنجاح.',
            'data' => $company->load(['baseCurrency', 'companyUsers.user']),
        ], 201);
    }
}
