<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->unique()->after('name');
            $table->boolean('cross_midnight')->default(false)->after('end_time');
            $table->boolean('breaks_enabled')->default(true)->after('cross_midnight');
            $table->json('breaks')->nullable()->after('breaks_enabled');
            $table->unsignedSmallInteger('grace_period_minutes')->default(15)->after('grace_time_late');
            $table->unsignedSmallInteger('late_after_minutes')->default(15)->after('grace_period_minutes');
            $table->unsignedSmallInteger('minimum_working_minutes')->default(480)->after('late_after_minutes');
            $table->boolean('early_leaving_allowed')->default(false)->after('minimum_working_minutes');
            $table->boolean('auto_mark_late')->default(true)->after('early_leaving_allowed');
            $table->boolean('auto_mark_half_day')->default(true)->after('auto_mark_late');
            $table->unsignedSmallInteger('late_threshold_minutes')->default(30)->after('auto_mark_half_day');
            $table->unsignedSmallInteger('half_day_after_minutes')->default(240)->after('late_threshold_minutes');
            $table->boolean('overtime_enabled')->default(false)->after('overtime_after');
            $table->unsignedSmallInteger('overtime_starts_after_minutes')->default(480)->after('overtime_enabled');
            $table->unsignedSmallInteger('minimum_overtime_minutes')->default(30)->after('overtime_starts_after_minutes');
            $table->string('overtime_calculation', 30)->default('Hourly')->after('minimum_overtime_minutes');
            $table->boolean('overtime_approval_required')->default(true)->after('overtime_calculation');
            $table->json('working_days')->nullable()->after('overtime_approval_required');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'cross_midnight', 'breaks_enabled', 'breaks', 'grace_period_minutes',
                'late_after_minutes', 'minimum_working_minutes', 'early_leaving_allowed',
                'auto_mark_late', 'auto_mark_half_day', 'late_threshold_minutes',
                'half_day_after_minutes', 'overtime_enabled', 'overtime_starts_after_minutes',
                'minimum_overtime_minutes', 'overtime_calculation', 'overtime_approval_required',
                'working_days',
            ]);
        });
    }
};
