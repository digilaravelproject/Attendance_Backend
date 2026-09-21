<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sales_target_metric_type', 30)->nullable();
            $table->string('sales_target_period', 20)->nullable();
            $table->decimal('incentive_commission_percent', 5, 2)->nullable();
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session', 20)->default('Full Day');
            $table->text('address_during_leave')->nullable();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['date', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to_user_id');
            $table->dropColumn(['session', 'address_during_leave']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sales_target_metric_type', 'sales_target_period', 'incentive_commission_percent']);
        });
    }
};
