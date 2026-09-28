<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashBox;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\JournalEntryService;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Currencies
        $sar = Currency::create([
            'code' => 'SAR',
            'name' => 'ريال سعودي',
            'symbol' => 'ر.س',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        $usd = Currency::create([
            'code' => 'USD',
            'name' => 'دولار أمريكي',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        $eur = Currency::create([
            'code' => 'EUR',
            'name' => 'يورو',
            'symbol' => '€',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        // 2. Create Default Admin & SuperAdmin Users
        $superAdmin = User::create([
            'name' => 'مدير النظام (SuperAdmin)',
            'email' => 'superadmin@accounts.com',
            'password' => Hash::make('superadmin123'),
            'is_super_admin' => true,
        ]);

        $userA = User::create([
            'name' => 'مدير شركة الحلول المتقدمة',
            'email' => 'admin@accounts.com',
            'password' => Hash::make('password123'),
        ]);

        $userB = User::create([
            'name' => 'مدير Global Commerce Tech',
            'email' => 'admin@gbltech.com',
            'password' => Hash::make('password123'),
        ]);

        // 3. Create Company A (SAR)
        $companyA = Company::create([
            'name' => 'شركة الحلول المتقدمة المحاسبية',
            'legal_name' => 'شركة الحلول المتقدمة ذات مسؤولية محدودة',
            'code' => 'ADV-SOL',
            'tax_number' => '300123456700003',
            'commercial_registration' => '1010987654',
            'address' => 'الرياض - طريق الملك فهد',
            'phone' => '+966112345678',
            'email' => 'info@adv-solutions.sa',
            'base_currency_id' => $sar->id,
            'fiscal_year_start_month' => 1,
            'timezone' => 'Asia/Riyadh',
            'status' => 'ACTIVE',
        ]);

        // Create Company B (USD) for Multi-Tenant testing
        $companyB = Company::create([
            'name' => 'Global Commerce Tech',
            'legal_name' => 'Global Commerce Tech LLC',
            'code' => 'GBL-TECH',
            'tax_number' => '9988776655',
            'commercial_registration' => '4030123456',
            'address' => 'Dubai - Business Bay',
            'phone' => '+97141234567',
            'email' => 'contact@gbltech.com',
            'base_currency_id' => $usd->id,
            'fiscal_year_start_month' => 1,
            'timezone' => 'Asia/Dubai',
            'status' => 'ACTIVE',
        ]);

        // Assign Users to Companies
        CompanyUser::create([
            'user_id' => $userA->id,
            'company_id' => $companyA->id,
            'role' => 'COMPANY_ADMIN',
            'is_default' => true,
        ]);

        CompanyUser::create([
            'user_id' => $userA->id,
            'company_id' => $companyB->id,
            'role' => 'COMPANY_ADMIN',
            'is_default' => false,
        ]);

        CompanyUser::create([
            'user_id' => $userB->id,
            'company_id' => $companyB->id,
            'role' => 'COMPANY_ADMIN',
            'is_default' => true,
        ]);

        // 4. Create Branches for Company A
        $mainBranch = Branch::create([
            'company_id' => $companyA->id,
            'code' => 'BR-01',
            'name' => 'الفرع الرئيسي - الرياض',
            'phone' => '+966112345678',
            'address' => 'الرياض - البرج الشمالي',
            'is_active' => true,
        ]);

        $jeddahBranch = Branch::create([
            'company_id' => $companyA->id,
            'code' => 'BR-02',
            'name' => 'فرع جدة الغربي',
            'phone' => '+966126543210',
            'address' => 'جدة - شارع التحلية',
            'is_active' => true,
        ]);

        // 5. Exchange Rates
        ExchangeRate::create([
            'company_id' => $companyA->id,
            'currency_id' => $usd->id,
            'rate_date' => now()->startOfYear()->toDateString(),
            'rate' => 3.750000,
        ]);

        ExchangeRate::create([
            'company_id' => $companyA->id,
            'currency_id' => $eur->id,
            'rate_date' => now()->startOfYear()->toDateString(),
            'rate' => 4.050000,
        ]);

        // 6. Fiscal Year & Periods for 2026
        $year2026 = FiscalYear::create([
            'company_id' => $companyA->id,
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'OPEN',
        ]);

        for ($i = 1; $i <= 12; $i++) {
            $startDate = sprintf('2026-%02d-01', $i);
            $endDate = date('Y-m-t', strtotime($startDate));

            FiscalPeriod::create([
                'fiscal_year_id' => $year2026->id,
                'company_id' => $companyA->id,
                'period_number' => $i,
                'name' => sprintf('فترة %02d-2026', $i),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'OPEN',
            ]);
        }

        // 7. Chart of Accounts for Company A
        // Assets
        $assets = Account::create([
            'company_id' => $companyA->id,
            'code' => '1000',
            'name' => 'الأصول',
            'type' => 'ASSET',
            'nature' => 'DEBIT',
            'level' => 1,
            'is_group' => true,
            'is_postable' => false,
        ]);

        $currAssets = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $assets->id,
            'code' => '1100',
            'name' => 'الأصول المتداولة',
            'type' => 'ASSET',
            'nature' => 'DEBIT',
            'level' => 2,
            'is_group' => true,
            'is_postable' => false,
        ]);

        $cash = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $currAssets->id,
            'code' => '1110',
            'name' => 'الصندوق الرئيسي',
            'type' => 'ASSET',
            'nature' => 'DEBIT',
            'level' => 3,
            'is_group' => false,
            'is_postable' => true,
            'currency_id' => $sar->id,
        ]);

        $bank = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $currAssets->id,
            'code' => '1120',
            'name' => 'البنك الأهلي السعودي',
            'type' => 'ASSET',
            'nature' => 'DEBIT',
            'level' => 3,
            'is_group' => false,
            'is_postable' => true,
            'currency_id' => $sar->id,
        ]);

        // Link Bank to SAR & USD (Multi-Currency Account)
        $bank->currencies()->sync([$sar->id, $usd->id]);

        $customersGroup = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $currAssets->id,
            'code' => '1130',
            'name' => 'العملاء',
            'type' => 'ASSET',
            'nature' => 'DEBIT',
            'level' => 3,
            'is_group' => true,
            'is_postable' => false,
        ]);

        $customerA = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $customersGroup->id,
            'code' => '1131',
            'name' => 'شركة الأفق التجارية',
            'type' => 'ASSET',
            'nature' => 'DEBIT',
            'level' => 4,
            'is_group' => false,
            'is_postable' => true,
            'currency_id' => $sar->id,
        ]);
        $customerA->currencies()->sync([$sar->id, $usd->id]);

        // Liabilities
        $liabilities = Account::create([
            'company_id' => $companyA->id,
            'code' => '2000',
            'name' => 'الخصوم',
            'type' => 'LIABILITY',
            'nature' => 'CREDIT',
            'level' => 1,
            'is_group' => true,
            'is_postable' => false,
        ]);

        $currLiab = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $liabilities->id,
            'code' => '2100',
            'name' => 'الخصوم المتداولة',
            'type' => 'LIABILITY',
            'nature' => 'CREDIT',
            'level' => 2,
            'is_group' => true,
            'is_postable' => false,
        ]);

        $suppliersGroup = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $currLiab->id,
            'code' => '2110',
            'name' => 'الموردون',
            'type' => 'LIABILITY',
            'nature' => 'CREDIT',
            'level' => 3,
            'is_group' => true,
            'is_postable' => false,
        ]);

        $supplierA = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $suppliersGroup->id,
            'code' => '2111',
            'name' => 'شركة التوريدات الكبرى',
            'type' => 'LIABILITY',
            'nature' => 'CREDIT',
            'level' => 4,
            'is_group' => false,
            'is_postable' => true,
            'currency_id' => $sar->id,
        ]);

        // Equity
        $equity = Account::create([
            'company_id' => $companyA->id,
            'code' => '3000',
            'name' => 'حقوق الملكية',
            'type' => 'EQUITY',
            'nature' => 'CREDIT',
            'level' => 1,
            'is_group' => true,
            'is_postable' => false,
        ]);

        $capital = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $equity->id,
            'code' => '3110',
            'name' => 'رأس المال',
            'type' => 'EQUITY',
            'nature' => 'CREDIT',
            'level' => 2,
            'is_group' => false,
            'is_postable' => true,
        ]);

        // Revenue
        $revenue = Account::create([
            'company_id' => $companyA->id,
            'code' => '4000',
            'name' => 'الإيرادات',
            'type' => 'REVENUE',
            'nature' => 'CREDIT',
            'level' => 1,
            'is_group' => true,
            'is_postable' => false,
        ]);

        $sales = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $revenue->id,
            'code' => '4110',
            'name' => 'مبيعات الخدمات والأنظمة',
            'type' => 'REVENUE',
            'nature' => 'CREDIT',
            'level' => 2,
            'is_group' => false,
            'is_postable' => true,
        ]);

        // Expenses
        $expense = Account::create([
            'company_id' => $companyA->id,
            'code' => '5000',
            'name' => 'المصروفات',
            'type' => 'EXPENSE',
            'nature' => 'DEBIT',
            'level' => 1,
            'is_group' => true,
            'is_postable' => false,
        ]);

        $rent = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $expense->id,
            'code' => '5110',
            'name' => 'مصروف الإيجار',
            'type' => 'EXPENSE',
            'nature' => 'DEBIT',
            'level' => 2,
            'is_group' => false,
            'is_postable' => true,
        ]);

        $salaries = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $expense->id,
            'code' => '5120',
            'name' => 'الرواتب والأجور',
            'type' => 'EXPENSE',
            'nature' => 'DEBIT',
            'level' => 2,
            'is_group' => false,
            'is_postable' => true,
        ]);

        $electricity = Account::create([
            'company_id' => $companyA->id,
            'parent_id' => $expense->id,
            'code' => '5130',
            'name' => 'الكهرباء والمرافق',
            'type' => 'EXPENSE',
            'nature' => 'DEBIT',
            'level' => 2,
            'is_group' => false,
            'is_postable' => true,
        ]);

        // 8. Create Cash Boxes
        $cashBoxMain = CashBox::create([
            'company_id' => $companyA->id,
            'branch_id' => $mainBranch->id,
            'code' => 'CB-01',
            'name' => 'خزينة الفرع الرئيسي',
            'account_id' => $cash->id,
            'keeper_name' => 'سعد الحارثي',
            'is_active' => true,
            'is_default' => true,
        ]);

        $cashBoxJeddah = CashBox::create([
            'company_id' => $companyA->id,
            'branch_id' => $jeddahBranch->id,
            'code' => 'CB-02',
            'name' => 'خزينة فرع جدة',
            'account_id' => $cash->id,
            'keeper_name' => 'فهد القحطاني',
            'is_active' => true,
        ]);

        $bankBoxMain = CashBox::create([
            'company_id' => $companyA->id,
            'branch_id' => $mainBranch->id,
            'code' => 'BK-01',
            'name' => 'حساب البنك الأهلي السعودي',
            'account_id' => $bank->id,
            'keeper_name' => 'إدارة الحسابات البنكية',
            'is_active' => true,
        ]);

        // 9. Initial Seed Transactions using Accounting Engine
        $journalService = app(JournalEntryService::class);
        $receiptService = app(ReceiptService::class);
        $paymentService = app(PaymentService::class);

        // Transaction 1: Opening Capital Deposit (100,000 SAR)
        $journalService->create([
            'company_id' => $companyA->id,
            'branch_id' => $mainBranch->id,
            'entry_date' => '2026-01-05',
            'description' => 'إيداع رأس المال التأسيسي في البنك',
            'reference' => 'OPENING-2026',
            'currency_id' => $sar->id,
            'exchange_rate' => 1.0,
            'created_by' => $userA->id,
            'lines' => [
                [
                    'account_id' => $bank->id,
                    'description' => 'إيداع في حساب البنك',
                    'debit' => 100000.00,
                    'credit' => 0,
                ],
                [
                    'account_id' => $capital->id,
                    'description' => 'إثبات رأس المال',
                    'debit' => 0,
                    'credit' => 100000.00,
                ],
            ],
        ], autoPost: true);

        // Transaction 2: Receipt Voucher from Customer (15,000 SAR)
        $receiptService->create([
            'company_id' => $companyA->id,
            'branch_id' => $mainBranch->id,
            'cash_box_id' => $cashBoxMain->id,
            'receipt_date' => '2026-01-10',
            'account_id' => $customerA->id,
            'cash_or_bank_account_id' => $bank->id,
            'amount' => 15000.00,
            'currency_id' => $sar->id,
            'exchange_rate' => 1.0,
            'payment_method' => 'BANK_TRANSFER',
            'reference' => 'TRANS-88776',
            'description' => 'استلام دفعة لحساب تطوير نظام محاسبي',
            'created_by' => $userA->id,
        ], autoPost: true);

        // Transaction 3: Payment Voucher for Office Rent (5,000 SAR)
        $paymentService->create([
            'company_id' => $companyA->id,
            'branch_id' => $mainBranch->id,
            'cash_box_id' => $cashBoxMain->id,
            'payment_date' => '2026-01-15',
            'account_id' => $rent->id,
            'cash_or_bank_account_id' => $bank->id,
            'amount' => 5000.00,
            'currency_id' => $sar->id,
            'exchange_rate' => 1.0,
            'payment_method' => 'BANK_TRANSFER',
            'reference' => 'RENT-JAN-2026',
            'description' => 'سداد قيمة إيجار المكتب لشهر يناير',
            'created_by' => $userA->id,
        ], autoPost: true);
    }
}
