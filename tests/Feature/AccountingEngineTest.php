<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\FinancialReportService;
use App\Services\JournalEntryService;
use App\Services\ReceiptService;
use App\Services\ReversalService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $companyA;

    protected Company $companyB;

    protected Currency $sar;

    protected Account $bankA;

    protected Account $capitalA;

    protected Account $rentA;

    protected Account $customerA;

    protected function setUp(): void
    {
        parent::setUp();

        CompanyContextService::clear();

        $this->user = User::factory()->create();
        $this->sar = Currency::create(['code' => 'SAR', 'name' => 'SAR', 'symbol' => 'SAR', 'decimal_places' => 2]);

        $this->companyA = Company::create([
            'name' => 'Company A',
            'code' => 'COMP-A',
            'base_currency_id' => $this->sar->id,
        ]);

        $this->companyB = Company::create([
            'name' => 'Company B',
            'code' => 'COMP-B',
            'base_currency_id' => $this->sar->id,
        ]);

        CompanyContextService::setCompanyId($this->companyA->id);

        // Fiscal period for 2026
        $fy = FiscalYear::create(['company_id' => $this->companyA->id, 'year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        FiscalPeriod::create(['fiscal_year_id' => $fy->id, 'company_id' => $this->companyA->id, 'period_number' => 1, 'name' => 'Jan', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => 'OPEN']);

        $fyB = FiscalYear::create(['company_id' => $this->companyB->id, 'year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        FiscalPeriod::create(['fiscal_year_id' => $fyB->id, 'company_id' => $this->companyB->id, 'period_number' => 1, 'name' => 'Jan', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => 'OPEN']);

        // Accounts for Company A
        $assets = Account::create(['company_id' => $this->companyA->id, 'code' => '1000', 'name' => 'Assets', 'type' => 'ASSET', 'nature' => 'DEBIT', 'is_group' => true, 'is_postable' => false]);
        $this->bankA = Account::create(['company_id' => $this->companyA->id, 'parent_id' => $assets->id, 'code' => '1120', 'name' => 'Bank A', 'type' => 'ASSET', 'nature' => 'DEBIT', 'is_group' => false, 'is_postable' => true]);
        $this->customerA = Account::create(['company_id' => $this->companyA->id, 'parent_id' => $assets->id, 'code' => '1131', 'name' => 'Customer A', 'type' => 'ASSET', 'nature' => 'DEBIT', 'is_group' => false, 'is_postable' => true]);

        $equity = Account::create(['company_id' => $this->companyA->id, 'code' => '3000', 'name' => 'Equity', 'type' => 'EQUITY', 'nature' => 'CREDIT', 'is_group' => true, 'is_postable' => false]);
        $this->capitalA = Account::create(['company_id' => $this->companyA->id, 'parent_id' => $equity->id, 'code' => '3110', 'name' => 'Capital A', 'type' => 'EQUITY', 'nature' => 'CREDIT', 'is_group' => false, 'is_postable' => true]);

        $expenses = Account::create(['company_id' => $this->companyA->id, 'code' => '5000', 'name' => 'Expenses', 'type' => 'EXPENSE', 'nature' => 'DEBIT', 'is_group' => true, 'is_postable' => false]);
        $this->rentA = Account::create(['company_id' => $this->companyA->id, 'parent_id' => $expenses->id, 'code' => '5110', 'name' => 'Rent A', 'type' => 'EXPENSE', 'nature' => 'DEBIT', 'is_group' => false, 'is_postable' => true]);
    }

    public function test_balanced_journal_entry_is_accepted(): void
    {
        $service = app(JournalEntryService::class);

        $entry = $service->create([
            'company_id' => $this->companyA->id,
            'entry_date' => '2026-01-10',
            'description' => 'Test Capital',
            'currency_id' => $this->sar->id,
            'created_by' => $this->user->id,
            'lines' => [
                ['account_id' => $this->bankA->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $this->capitalA->id, 'debit' => 0, 'credit' => 5000],
            ],
        ], autoPost: true);

        $this->assertEquals('POSTED', $entry->status);
        $this->assertEquals(5000, $entry->total_debit);
        $this->assertEquals(5000, $entry->total_credit);
    }

    public function test_unbalanced_journal_entry_is_rejected(): void
    {
        $this->expectException(Exception::class);

        $service = app(JournalEntryService::class);

        $service->create([
            'company_id' => $this->companyA->id,
            'entry_date' => '2026-01-10',
            'description' => 'Unbalanced',
            'currency_id' => $this->sar->id,
            'created_by' => $this->user->id,
            'lines' => [
                ['account_id' => $this->bankA->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $this->capitalA->id, 'debit' => 0, 'credit' => 4000],
            ],
        ]);
    }

    public function test_multi_company_isolation(): void
    {
        // Set Context to Company A
        CompanyContextService::setCompanyId($this->companyA->id);

        $accountsCountA = Account::count();
        $this->assertGreaterThan(0, $accountsCountA);

        // Set Context to Company B
        CompanyContextService::setCompanyId($this->companyB->id);
        $accountsCountB = Account::count();

        // Company B has 0 accounts because of Global CompanyScope
        $this->assertEquals(0, $accountsCountB);

        // Reset to Company A
        CompanyContextService::setCompanyId($this->companyA->id);
    }

    public function test_journal_reversal_creates_opposite_entry(): void
    {
        CompanyContextService::setCompanyId($this->companyA->id);

        $journalService = app(JournalEntryService::class);
        $reversalService = app(ReversalService::class);

        $entry = $journalService->create([
            'company_id' => $this->companyA->id,
            'entry_date' => '2026-01-10',
            'description' => 'Original Entry',
            'currency_id' => $this->sar->id,
            'created_by' => $this->user->id,
            'lines' => [
                ['account_id' => $this->bankA->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $this->capitalA->id, 'debit' => 0, 'credit' => 1000],
            ],
        ], autoPost: true);

        $reversal = $reversalService->reverseJournalEntry($entry, $this->user->id, 'Testing reversal');

        $this->assertEquals('REVERSED', $entry->fresh()->status);
        $this->assertEquals('POSTED', $reversal->status);
        $this->assertStringContainsString('REVERSAL', $reversal->reference);
    }

    public function test_receipt_voucher_auto_posts_journal(): void
    {
        CompanyContextService::setCompanyId($this->companyA->id);

        $receiptService = app(ReceiptService::class);

        $receipt = $receiptService->create([
            'company_id' => $this->companyA->id,
            'receipt_date' => '2026-01-12',
            'account_id' => $this->customerA->id,
            'cash_or_bank_account_id' => $this->bankA->id,
            'amount' => 3000,
            'currency_id' => $this->sar->id,
            'created_by' => $this->user->id,
        ], autoPost: true);

        $this->assertEquals('POSTED', $receipt->status);
        $this->assertNotNull($receipt->journal_entry_id);

        $je = JournalEntry::find($receipt->journal_entry_id);
        $this->assertNotNull($je);
        $this->assertEquals('POSTED', $je->status);
        $this->assertEquals(3000, $je->total_debit);
    }

    public function test_balance_sheet_equation_holds(): void
    {
        CompanyContextService::setCompanyId($this->companyA->id);

        $journalService = app(JournalEntryService::class);
        $reportService = app(FinancialReportService::class);

        // 1. Capital 10,000 in Bank
        $journalService->create([
            'company_id' => $this->companyA->id,
            'entry_date' => '2026-01-05',
            'description' => 'Capital',
            'currency_id' => $this->sar->id,
            'created_by' => $this->user->id,
            'lines' => [
                ['account_id' => $this->bankA->id, 'debit' => 10000, 'credit' => 0],
                ['account_id' => $this->capitalA->id, 'debit' => 0, 'credit' => 10000],
            ],
        ], autoPost: true);

        // 2. Rent expense 2,000 from Bank
        $journalService->create([
            'company_id' => $this->companyA->id,
            'entry_date' => '2026-01-08',
            'description' => 'Rent Expense',
            'currency_id' => $this->sar->id,
            'created_by' => $this->user->id,
            'lines' => [
                ['account_id' => $this->rentA->id, 'debit' => 2000, 'credit' => 0],
                ['account_id' => $this->bankA->id, 'debit' => 0, 'credit' => 2000],
            ],
        ], autoPost: true);

        $bs = $reportService->getBalanceSheet($this->companyA->id, '2026-01-31');

        $this->assertTrue($bs['is_balanced']);
        $this->assertEquals(8000, $bs['total_assets']); // Bank balance = 10000 - 2000 = 8000
        $this->assertEquals(10000, $bs['total_equity']); // Capital
        $this->assertEquals(-2000, $bs['retained_earnings']); // Net Loss = -2000
        $this->assertEquals(8000, $bs['total_equity_and_liabilities']);
    }

    public function test_trial_balance_without_dates_balances_and_has_no_opening(): void
    {
        $this->postCapitalAndRent();

        $tb = app(FinancialReportService::class)->getTrialBalance($this->companyA->id);
        $totals = $tb['totals'];

        $this->assertEquals(0, $totals['opening_debit']);
        $this->assertEquals(0, $totals['opening_credit']);
        $this->assertEquals(12000, $totals['period_debit']);
        $this->assertEquals(12000, $totals['period_credit']);
        $this->assertEquals($totals['ending_debit'], $totals['ending_credit']);
        $this->assertEquals(10000, $totals['ending_debit']);

        $capital = collect($tb['accounts'])->firstWhere('code', '3110');
        $this->assertEquals(0, $capital['ending_debit']);
        $this->assertEquals(10000, $capital['ending_credit']);
    }

    public function test_trial_balance_with_from_date_splits_opening_and_period(): void
    {
        $this->postCapitalAndRent();

        $tb = app(FinancialReportService::class)->getTrialBalance($this->companyA->id, '2026-01-06', '2026-01-31');
        $totals = $tb['totals'];

        $this->assertEquals(10000, $totals['opening_debit']);
        $this->assertEquals(10000, $totals['opening_credit']);
        $this->assertEquals(2000, $totals['period_debit']);
        $this->assertEquals(2000, $totals['period_credit']);
        $this->assertEquals(10000, $totals['ending_debit']);
        $this->assertEquals(10000, $totals['ending_credit']);
    }

    public function test_account_statement_without_from_date_has_zero_opening(): void
    {
        $this->postCapitalAndRent();

        $statement = app(FinancialReportService::class)->getAccountStatement($this->companyA->id, $this->bankA->id);

        $this->assertEquals(0, $statement['opening_balance']);
        $this->assertEquals(8000, $statement['ending_balance']);
    }

    public function test_entry_date_serializes_as_plain_date(): void
    {
        $this->postCapitalAndRent();

        $entry = JournalEntry::query()->orderBy('id')->first();

        $this->assertSame('2026-01-05', $entry->toArray()['entry_date']);
    }

    private function postCapitalAndRent(): void
    {
        $journalService = app(JournalEntryService::class);

        $journalService->create([
            'company_id' => $this->companyA->id,
            'entry_date' => '2026-01-05',
            'description' => 'Capital',
            'currency_id' => $this->sar->id,
            'created_by' => $this->user->id,
            'lines' => [
                ['account_id' => $this->bankA->id, 'debit' => 10000, 'credit' => 0],
                ['account_id' => $this->capitalA->id, 'debit' => 0, 'credit' => 10000],
            ],
        ], autoPost: true);

        $journalService->create([
            'company_id' => $this->companyA->id,
            'entry_date' => '2026-01-08',
            'description' => 'Rent Expense',
            'currency_id' => $this->sar->id,
            'created_by' => $this->user->id,
            'lines' => [
                ['account_id' => $this->rentA->id, 'debit' => 2000, 'credit' => 0],
                ['account_id' => $this->bankA->id, 'debit' => 0, 'credit' => 2000],
            ],
        ], autoPost: true);
    }
}
