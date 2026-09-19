<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('department')
                ->constrained('departments')->nullOnDelete();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('annual_allowance')->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('total_days', 6, 2);
            $table->text('reason');
            $table->string('contact_during_leave', 50)->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'from_date', 'to_date']);
        });

        Schema::create('leave_request_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->string('action', 30);
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_actions');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('departments');
    }
};
