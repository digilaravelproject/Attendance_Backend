<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('salary_month');
            $table->decimal('gross_earnings', 12, 2);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_payable', 12, 2);
            $table->json('earnings');
            $table->json('deductions');
            $table->json('attendance_summary');
            $table->date('payment_date');
            $table->string('payment_mode', 30);
            $table->string('bank_name')->nullable();
            $table->string('account_upi_address')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('Paid');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'salary_month']);
            $table->index(['salary_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
