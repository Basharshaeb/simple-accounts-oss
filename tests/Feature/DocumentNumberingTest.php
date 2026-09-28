<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Receipt;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_numbering_is_scoped_by_company_and_year(): void
    {
        $user = User::factory()->create();
        $currency = Currency::create(['code' => 'SAR', 'name' => 'SAR', 'symbol' => 'SAR', 'decimal_places' => 2]);

        $companyA = Company::create(['name' => 'Company A', 'code' => 'COA', 'base_currency_id' => $currency->id]);
        $companyB = Company::create(['name' => 'Company B', 'code' => 'COB', 'base_currency_id' => $currency->id]);

        $accountA = Account::create([
            'company_id' => $companyA->id,
            'code' => '1001',
            'name' => 'Cash Box A',
            'type' => 'ASSET',
            'nature' => 'DEBIT',
            'is_group' => false,
            'is_postable' => true,
        ]);

        // First receipt for Company A in 2026
        $numA1 = DocumentNumberingService::generate($companyA->id, 'RV', 2026);
        $this->assertEquals('RV-2026-000001', $numA1);

        // Record dummy receipt for Company A in 2026
        Receipt::create([
            'company_id' => $companyA->id,
            'receipt_number' => $numA1,
            'receipt_date' => '2026-01-15',
            'account_id' => $accountA->id,
            'amount' => 100,
            'base_amount' => 100,
            'exchange_rate' => 1.0,
            'cash_or_bank_account_id' => $accountA->id,
            'currency_id' => $currency->id,
            'status' => 'DRAFT',
            'created_by' => $user->id,
        ]);

        // Next receipt for Company A in 2026 should be sequence 000002
        $numA2 = DocumentNumberingService::generate($companyA->id, 'RV', 2026);
        $this->assertEquals('RV-2026-000002', $numA2);

        // Next receipt for Company A in 2027 should restart at sequence 000001
        $numA2027 = DocumentNumberingService::generate($companyA->id, 'RV', 2027);
        $this->assertEquals('RV-2027-000001', $numA2027);

        // Receipt for Company B in 2026 should also start at sequence 000001
        $numB1 = DocumentNumberingService::generate($companyB->id, 'RV', 2026);
        $this->assertEquals('RV-2026-000001', $numB1);
    }
}
