<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('membership_type_id')->nullable()->constrained('membership_types')->nullOnDelete();
            $table->string('title');
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_period', 30)->nullable();
            $table->string('recurring_by', 30)->nullable();
            $table->string('recurring_date', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('reminder_days_before_due')->nullable();
            $table->unsignedInteger('reminder_days_after_due')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'membership_type_id', 'slug'], 'mpi_org_type_slug_unique');
            $table->index(['organization_id', 'is_active', 'is_mandatory'], 'mpi_org_active_mandatory_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('member_payment_items'); }
};
