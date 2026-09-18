<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('employee_id');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('marital_status', 30)->nullable()->after('date_of_birth');
            $table->string('blood_group', 10)->nullable()->after('marital_status');
            $table->string('alternate_mobile_number', 30)->nullable()->after('mobile_number');
            $table->string('street_address')->nullable()->after('address');
            $table->string('city', 100)->nullable()->after('street_address');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->string('state', 100)->nullable()->after('postal_code');
            $table->string('country', 100)->nullable()->after('state');
            $table->string('work_mode', 20)->nullable()->after('department');
            $table->string('employee_type', 30)->nullable()->after('work_mode');
            $table->string('team')->nullable()->after('employee_type');
            $table->foreignId('assigned_shift_id')->nullable()->after('team')->constrained('shifts')->nullOnDelete();
            $table->foreignId('reporting_manager_id')->nullable()->after('assigned_shift_id')->constrained('users')->nullOnDelete();
            $table->string('employment_status', 30)->default('Active')->after('status');
            $table->string('probation_period', 30)->nullable()->after('employment_status');
            $table->string('notice_period', 30)->nullable()->after('probation_period');
            $table->string('salary_type', 30)->default('Monthly')->after('monthly_salary');
            $table->boolean('sales_target_enabled')->default(false)->after('salary_type');
            $table->decimal('sales_target', 12, 2)->nullable()->after('sales_target_enabled');
            $table->string('account_holder_name')->nullable()->after('sales_target');
            $table->string('bank_name')->nullable()->after('account_holder_name');
            $table->string('account_number', 50)->nullable()->after('bank_name');
            $table->string('ifsc_code', 30)->nullable()->after('account_number');
            $table->string('branch_name')->nullable()->after('ifsc_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_shift_id');
            $table->dropConstrainedForeignId('reporting_manager_id');
            $table->dropColumn([
                'gender', 'date_of_birth', 'marital_status', 'blood_group',
                'alternate_mobile_number', 'street_address', 'city', 'postal_code', 'state',
                'country', 'work_mode', 'employee_type', 'team', 'employment_status',
                'probation_period', 'notice_period', 'salary_type', 'sales_target_enabled',
                'sales_target', 'account_holder_name', 'bank_name', 'account_number',
                'ifsc_code', 'branch_name',
            ]);
        });
    }
};
