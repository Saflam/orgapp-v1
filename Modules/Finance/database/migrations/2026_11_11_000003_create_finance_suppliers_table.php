<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->json('billing_address')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'finance_suppliers_org_code_uq');
            $table->index(['organization_id', 'name'], 'finance_suppliers_org_name_idx');
        });

        Schema::create('finance_sales_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('finance_scope_id')->constrained('finance_scopes')->restrictOnDelete();
            $table->foreignId('finance_customer_id')->constrained('finance_customers')->restrictOnDelete();
            $table->foreignId('finance_period_id')->constrained('finance_periods')->restrictOnDelete();
            $table->foreignId('finance_journal_id')->nullable()->constrained('finance_journals')->restrictOnDelete();
            $table->string('invoice_number', 100);
            $table->string('request_key', 64)->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('status', 24)->default('posted');
            $table->string('currency', 3);
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_total', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->decimal('balance_due', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'invoice_number'], 'finance_sales_invoice_number_uq');
            $table->unique(['organization_id', 'request_key'], 'finance_sales_invoice_request_uq');
            $table->index(['organization_id', 'status', 'due_date'], 'finance_sales_invoice_age_idx');
            $table->index(['finance_customer_id', 'invoice_date'], 'finance_sales_invoice_customer_idx');
        });

        Schema::create('finance_sales_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_sales_invoice_id')->constrained('finance_sales_invoices')->cascadeOnDelete();
            $table->foreignId('finance_item_id')->nullable()->constrained('finance_items')->nullOnDelete();
            $table->foreignId('finance_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('finance_cost_center_id')->nullable()->constrained('finance_cost_centers')->nullOnDelete();
            $table->foreignId('finance_tax_rate_id')->nullable()->constrained('finance_tax_rates')->restrictOnDelete();
            $table->text('description');
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('tax_rate', 9, 4)->default(0);
            $table->decimal('line_subtotal', 20, 4);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('line_total', 20, 4);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_purchase_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('finance_scope_id')->constrained('finance_scopes')->restrictOnDelete();
            $table->foreignId('finance_supplier_id')->constrained('finance_suppliers')->restrictOnDelete();
            $table->foreignId('finance_period_id')->constrained('finance_periods')->restrictOnDelete();
            $table->foreignId('finance_journal_id')->nullable()->constrained('finance_journals')->restrictOnDelete();
            $table->string('bill_number', 100);
            $table->string('request_key', 64)->nullable();
            $table->string('supplier_invoice_number', 100)->nullable();
            $table->date('bill_date');
            $table->date('due_date');
            $table->string('status', 24)->default('posted');
            $table->string('currency', 3);
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_total', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->decimal('balance_due', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'bill_number'], 'finance_purchase_bill_number_uq');
            $table->unique(['organization_id', 'request_key'], 'finance_purchase_bill_request_uq');
            $table->unique(['organization_id', 'finance_supplier_id', 'supplier_invoice_number'], 'finance_supplier_invoice_uq');
            $table->index(['organization_id', 'status', 'due_date'], 'finance_purchase_bill_age_idx');
            $table->index(['finance_supplier_id', 'bill_date'], 'finance_purchase_bill_supplier_idx');
        });

        Schema::create('finance_purchase_bill_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_purchase_bill_id')->constrained('finance_purchase_bills')->cascadeOnDelete();
            $table->foreignId('finance_item_id')->nullable()->constrained('finance_items')->nullOnDelete();
            $table->foreignId('finance_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('finance_cost_center_id')->nullable()->constrained('finance_cost_centers')->nullOnDelete();
            $table->foreignId('finance_tax_rate_id')->nullable()->constrained('finance_tax_rates')->restrictOnDelete();
            $table->text('description');
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('tax_rate', 9, 4)->default(0);
            $table->decimal('line_subtotal', 20, 4);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('line_total', 20, 4);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('finance_scope_id')->constrained('finance_scopes')->restrictOnDelete();
            $table->foreignId('finance_period_id')->constrained('finance_periods')->restrictOnDelete();
            $table->foreignId('finance_journal_id')->nullable()->constrained('finance_journals')->restrictOnDelete();
            $table->string('direction', 24); // customer_receipt | supplier_payment
            $table->string('payment_number', 100);
            $table->string('request_key', 64)->nullable();
            $table->date('payment_date');
            $table->foreignId('finance_account_id')->constrained('finance_accounts')->restrictOnDelete(); // cash/bank
            $table->decimal('amount', 20, 4);
            $table->string('currency', 3);
            $table->string('reference', 150)->nullable();
            $table->string('status', 20)->default('posted');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'payment_number'], 'finance_payment_number_uq');
            $table->unique(['organization_id', 'request_key'], 'finance_payment_request_uq');
            $table->index(['organization_id', 'direction', 'payment_date'], 'finance_payment_direction_date_idx');
        });

        Schema::create('finance_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_payment_id')->constrained('finance_payments')->cascadeOnDelete();
            $table->foreignId('finance_sales_invoice_id')->nullable()->constrained('finance_sales_invoices')->restrictOnDelete();
            $table->foreignId('finance_purchase_bill_id')->nullable()->constrained('finance_purchase_bills')->restrictOnDelete();
            $table->foreignId('finance_cost_center_id')->nullable()->constrained('finance_cost_centers')->nullOnDelete();
            $table->decimal('amount', 20, 4);
            $table->timestamps();
            $table->index(['finance_sales_invoice_id'], 'finance_alloc_sales_invoice_idx');
            $table->index(['finance_purchase_bill_id'], 'finance_alloc_purchase_bill_idx');
            $table->index(['finance_cost_center_id'], 'finance_alloc_cost_center_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payment_allocations');
        Schema::dropIfExists('finance_payments');
        Schema::dropIfExists('finance_purchase_bill_lines');
        Schema::dropIfExists('finance_purchase_bills');
        Schema::dropIfExists('finance_sales_invoice_lines');
        Schema::dropIfExists('finance_sales_invoices');
        Schema::dropIfExists('finance_suppliers');
    }
};
