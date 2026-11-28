<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique(); // non-enumerable external identifier
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('business_type', 32)->index();
            $table->string('contact_person');
            $table->string('email')->index();
            $table->string('phone', 20)->index();
            $table->string('trade_license_no', 64)->unique();
            $table->string('tin_no', 32)->nullable()->unique();
            $table->string('bin_no', 32)->nullable()->unique();
            $table->string('website')->nullable();

            // KYC
            $table->string('kyc_status', 16)->default('pending')->index();
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->foreignId('kyc_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->text('kyc_rejection_reason')->nullable();

            // Commercial terms
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->default(0); // Net-N
            // Denormalised running receivable, mutated ONLY by CreditLedgerService under row lock.
            $table->decimal('outstanding_balance', 14, 2)->default(0);
            $table->boolean('credit_enabled')->default(false);
            $table->boolean('credit_on_hold')->default(false);

            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kyc_status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });
        Schema::dropIfExists('companies');
    }
};
