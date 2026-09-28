<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('payment_number', 50);
            $table->date('payment_date');
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('cash_or_bank_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);
            $table->decimal('base_amount', 18, 4);
            $table->enum('payment_method', ['CASH', 'BANK_TRANSFER', 'CHEQUE', 'OTHER'])->default('CASH');
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['DRAFT', 'POSTED', 'VOIDED'])->default('DRAFT');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'payment_number']);
            $table->index(['company_id', 'payment_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
