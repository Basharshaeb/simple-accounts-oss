<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use App\Models\Receipt;

class FinancialReportService
{
    /**
     * Trial Balance (ميزان المراجعة)
     */
    public function getTrialBalance(int $companyId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null): array
    {
        $accounts = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $reportData = [];
        $totalOpeningDebit = 0.0;
        $totalOpeningCredit = 0.0;
        $totalPeriodDebit = 0.0;
        $totalPeriodCredit = 0.0;
        $totalEndingDebit = 0.0;
        $totalEndingCredit = 0.0;

        foreach ($accounts as $account) {
            // Opening balance query (before $fromDate)
            $openingQuery = JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $fromDate, $branchId) {
                    $q->withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('status', 'POSTED');
                    if ($fromDate) {
                        $q->where('entry_date', '<', $fromDate);
                    }
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                })
                ->where('account_id', $account->id);

            // Without a start date everything is period movement, so there is no opening balance
            $opDebit = $fromDate ? (float) $openingQuery->sum('base_debit') : 0.0;
            $opCredit = $fromDate ? (float) $openingQuery->sum('base_credit') : 0.0;

            // Period movements query
            $periodQuery = JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $fromDate, $toDate, $branchId) {
                    $q->withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('status', 'POSTED');
                    if ($fromDate) {
                        $q->where('entry_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->where('entry_date', '<=', $toDate);
                    }
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                })
                ->where('account_id', $account->id);

            $pDebit = (float) $periodQuery->sum('base_debit');
            $pCredit = (float) $periodQuery->sum('base_credit');

            // Balances go in the debit or credit column by their actual sign, not by account nature
            $netOpening = $opDebit - $opCredit;
            $opDebitDisplay = $netOpening > 0 ? $netOpening : 0.0;
            $opCreditDisplay = $netOpening < 0 ? abs($netOpening) : 0.0;

            $netEnding = ($opDebit + $pDebit) - ($opCredit + $pCredit);

            $endingDebit = $netEnding > 0 ? $netEnding : 0.0;
            $endingCredit = $netEnding < 0 ? abs($netEnding) : 0.0;

            if ($opDebitDisplay != 0 || $opCreditDisplay != 0 || $pDebit != 0 || $pCredit != 0 || $endingDebit != 0 || $endingCredit != 0) {
                $reportData[] = [
                    'account_id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'nature' => $account->nature,
                    'opening_debit' => $opDebitDisplay,
                    'opening_credit' => $opCreditDisplay,
                    'period_debit' => $pDebit,
                    'period_credit' => $pCredit,
                    'ending_debit' => $endingDebit,
                    'ending_credit' => $endingCredit,
                ];

                $totalOpeningDebit += $opDebitDisplay;
                $totalOpeningCredit += $opCreditDisplay;
                $totalPeriodDebit += $pDebit;
                $totalPeriodCredit += $pCredit;
                $totalEndingDebit += $endingDebit;
                $totalEndingCredit += $endingCredit;
            }
        }

        return [
            'accounts' => $reportData,
            'totals' => [
                'opening_debit' => $totalOpeningDebit,
                'opening_credit' => $totalOpeningCredit,
                'period_debit' => $totalPeriodDebit,
                'period_credit' => $totalPeriodCredit,
                'ending_debit' => $totalEndingDebit,
                'ending_credit' => $totalEndingCredit,
            ],
        ];
    }

    /**
     * Account Statement / General Ledger (كشف الحساب ودفتر الأستاذ)
     */
    public function getAccountStatement(int $companyId, int $accountId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null): array
    {
        $account = Account::withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($accountId);

        // Opening balance calculation
        $openingQuery = JournalEntryLine::query()
            ->whereHas('journalEntry', function ($q) use ($companyId, $fromDate, $branchId) {
                $q->withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('status', 'POSTED');
                if ($fromDate) {
                    $q->where('entry_date', '<', $fromDate);
                }
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->where('account_id', $accountId);

        $opDebit = $fromDate ? (float) $openingQuery->sum('base_debit') : 0.0;
        $opCredit = $fromDate ? (float) $openingQuery->sum('base_credit') : 0.0;
        $openingBalance = $account->nature === 'DEBIT' ? ($opDebit - $opCredit) : ($opCredit - $opDebit);

        // Lines in range
        $linesQuery = JournalEntryLine::query()
            ->with(['journalEntry'])
            ->whereHas('journalEntry', function ($q) use ($companyId, $fromDate, $toDate, $branchId) {
                $q->withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('status', 'POSTED');
                if ($fromDate) {
                    $q->where('entry_date', '>=', $fromDate);
                }
                if ($toDate) {
                    $q->where('entry_date', '<=', $toDate);
                }
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->where('account_id', $accountId);

        $lines = $linesQuery->get()->sortBy(fn ($l) => $l->journalEntry->entry_date->format('Y-m-d').'-'.$l->journalEntry->entry_number);

        $runningBalance = $openingBalance;
        $movements = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $debit = (float) $line->base_debit;
            $credit = (float) $line->base_credit;

            $change = $account->nature === 'DEBIT' ? ($debit - $credit) : ($credit - $debit);
            $runningBalance += $change;

            $totalDebit += $debit;
            $totalCredit += $credit;

            $movements[] = [
                'date' => $line->journalEntry->entry_date->format('Y-m-d'),
                'entry_number' => $line->journalEntry->entry_number,
                'reference' => $line->journalEntry->reference,
                'description' => $line->description ?? $line->journalEntry->description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance,
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'nature' => $account->nature,
            ],
            'opening_balance' => $openingBalance,
            'movements' => $movements,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
            ],
            'ending_balance' => $runningBalance,
        ];
    }

    /**
     * Income Statement (قائمة الدخل)
     */
    public function getIncomeStatement(int $companyId, ?string $fromDate = null, ?string $toDate = null, ?int $branchId = null): array
    {
        $revenueAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', 'REVENUE')
            ->where('is_postable', true)
            ->get();

        $expenseAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', 'EXPENSE')
            ->where('is_postable', true)
            ->get();

        $totalRevenue = 0.0;
        $revenueList = [];

        foreach ($revenueAccounts as $acc) {
            $sumCredit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $fromDate, $toDate, $branchId) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED');
                    if ($fromDate) {
                        $q->where('entry_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->where('entry_date', '<=', $toDate);
                    }
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                })
                ->where('account_id', $acc->id)
                ->sum('base_credit');

            $sumDebit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $fromDate, $toDate, $branchId) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED');
                    if ($fromDate) {
                        $q->where('entry_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->where('entry_date', '<=', $toDate);
                    }
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                })
                ->where('account_id', $acc->id)
                ->sum('base_debit');

            $net = $sumCredit - $sumDebit;
            if ($net != 0) {
                $revenueList[] = [
                    'account_id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'amount' => $net,
                ];
                $totalRevenue += $net;
            }
        }

        $totalExpense = 0.0;
        $expenseList = [];

        foreach ($expenseAccounts as $acc) {
            $sumDebit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $fromDate, $toDate, $branchId) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED');
                    if ($fromDate) {
                        $q->where('entry_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->where('entry_date', '<=', $toDate);
                    }
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                })
                ->where('account_id', $acc->id)
                ->sum('base_debit');

            $sumCredit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $fromDate, $toDate, $branchId) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED');
                    if ($fromDate) {
                        $q->where('entry_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->where('entry_date', '<=', $toDate);
                    }
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                })
                ->where('account_id', $acc->id)
                ->sum('base_credit');

            $net = $sumDebit - $sumCredit;
            if ($net != 0) {
                $expenseList[] = [
                    'account_id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'amount' => $net,
                ];
                $totalExpense += $net;
            }
        }

        $netProfit = $totalRevenue - $totalExpense;

        return [
            'revenues' => $revenueList,
            'total_revenue' => $totalRevenue,
            'expenses' => $expenseList,
            'total_expense' => $totalExpense,
            'net_profit' => $netProfit,
        ];
    }

    /**
     * Balance Sheet (الميزانية العمومية)
     */
    public function getBalanceSheet(int $companyId, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $assetAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', 'ASSET')
            ->where('is_postable', true)
            ->get();

        $liabilityAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', 'LIABILITY')
            ->where('is_postable', true)
            ->get();

        $equityAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', 'EQUITY')
            ->where('is_postable', true)
            ->get();

        $totalAssets = 0.0;
        $assetsList = [];
        foreach ($assetAccounts as $acc) {
            $debit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $asOfDate) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED')->where('entry_date', '<=', $asOfDate);
                })
                ->where('account_id', $acc->id)
                ->sum('base_debit');

            $credit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $asOfDate) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED')->where('entry_date', '<=', $asOfDate);
                })
                ->where('account_id', $acc->id)
                ->sum('base_credit');

            $net = $debit - $credit;
            if ($net != 0) {
                $assetsList[] = ['id' => $acc->id, 'code' => $acc->code, 'name' => $acc->name, 'amount' => $net];
                $totalAssets += $net;
            }
        }

        $totalLiabilities = 0.0;
        $liabilitiesList = [];
        foreach ($liabilityAccounts as $acc) {
            $debit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $asOfDate) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED')->where('entry_date', '<=', $asOfDate);
                })
                ->where('account_id', $acc->id)
                ->sum('base_debit');

            $credit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $asOfDate) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED')->where('entry_date', '<=', $asOfDate);
                })
                ->where('account_id', $acc->id)
                ->sum('base_credit');

            $net = $credit - $debit;
            if ($net != 0) {
                $liabilitiesList[] = ['id' => $acc->id, 'code' => $acc->code, 'name' => $acc->name, 'amount' => $net];
                $totalLiabilities += $net;
            }
        }

        $totalEquity = 0.0;
        $equityList = [];
        foreach ($equityAccounts as $acc) {
            $debit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $asOfDate) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED')->where('entry_date', '<=', $asOfDate);
                })
                ->where('account_id', $acc->id)
                ->sum('base_debit');

            $credit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', function ($q) use ($companyId, $asOfDate) {
                    $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED')->where('entry_date', '<=', $asOfDate);
                })
                ->where('account_id', $acc->id)
                ->sum('base_credit');

            $net = $credit - $debit;
            if ($net != 0) {
                $equityList[] = ['id' => $acc->id, 'code' => $acc->code, 'name' => $acc->name, 'amount' => $net];
                $totalEquity += $net;
            }
        }

        // Calculate Net Profit up to $asOfDate and add to equity
        $incomeStmt = $this->getIncomeStatement($companyId, null, $asOfDate);
        $retainedEarnings = $incomeStmt['net_profit'];

        $totalEquityAndLiabilities = $totalLiabilities + $totalEquity + $retainedEarnings;
        $isBalanced = abs($totalAssets - $totalEquityAndLiabilities) < 0.001;

        return [
            'as_of_date' => $asOfDate,
            'assets' => $assetsList,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilitiesList,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equityList,
            'total_equity' => $totalEquity,
            'retained_earnings' => $retainedEarnings,
            'total_equity_and_liabilities' => $totalEquityAndLiabilities,
            'is_balanced' => $isBalanced,
        ];
    }

    /**
     * Executive Dashboard Summary
     */
    public function getDashboardSummary(int $companyId): array
    {
        $incomeStmt = $this->getIncomeStatement($companyId);
        $balanceSheet = $this->getBalanceSheet($companyId);

        // Cash & Bank balances
        $cashBankAccounts = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('code', ['1110', '1120']) // 1110: الصندوق, 1120: البنك
            ->orWhere(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                    ->where('name', 'like', '%صندوق%')
                    ->orWhere('name', 'like', '%بنك%');
            })
            ->where('is_postable', true)
            ->get();

        $cashBalance = 0.0;
        $bankBalance = 0.0;

        foreach ($cashBankAccounts as $acc) {
            $debit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED'))
                ->where('account_id', $acc->id)
                ->sum('base_debit');

            $credit = (float) JournalEntryLine::query()
                ->whereHas('journalEntry', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'POSTED'))
                ->where('account_id', $acc->id)
                ->sum('base_credit');

            $net = $debit - $credit;
            if (str_contains($acc->name, 'صندوق') || $acc->code === '1110') {
                $cashBalance += $net;
            } else {
                $bankBalance += $net;
            }
        }

        $entriesCount = JournalEntry::withoutGlobalScopes()->where('company_id', $companyId)->count();
        $receiptsCount = Receipt::withoutGlobalScopes()->where('company_id', $companyId)->count();
        $paymentsCount = Payment::withoutGlobalScopes()->where('company_id', $companyId)->count();

        return [
            'total_assets' => $balanceSheet['total_assets'],
            'total_liabilities' => $balanceSheet['total_liabilities'],
            'total_equity' => $balanceSheet['total_equity'] + $balanceSheet['retained_earnings'],
            'total_revenue' => $incomeStmt['total_revenue'],
            'total_expense' => $incomeStmt['total_expense'],
            'net_profit' => $incomeStmt['net_profit'],
            'cash_balance' => $cashBalance,
            'bank_balance' => $bankBalance,
            'counts' => [
                'journal_entries' => $entriesCount,
                'receipts' => $receiptsCount,
                'payments' => $paymentsCount,
            ],
        ];
    }
}
