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
        // Tasks Table
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('cascade');
            $table->string('category')->nullable(); // e.g. UI/UX Design, Development, Testing, Bug
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium');
            $table->enum('status', [
                'Pending',
                'In Progress',
                'On Hold',
                'Completed',
                'In Review',
                'Submitted For Testing',
                'Cancelled'
            ])->default('Pending');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('estimated_hours')->nullable(); // e.g. "05h 30m" or "5.5"
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->unsignedInteger('total_logged_seconds')->default(0);
            $table->boolean('is_timer_running')->default(false);
            $table->timestamp('timer_started_at')->nullable();
            $table->timestamp('testing_submitted_at')->nullable();
            $table->text('testing_remarks')->nullable();
            $table->foreignId('testing_submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // Task User Pivot Table (Multiple Assigned Employees)
        Schema::create('task_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // Task Subtasks Table
        Schema::create('task_subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Task Comments Table
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('comment');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
        });

        // Task Attachments Table
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_type')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        // Task Time Logs Table
        Schema::create('task_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('action', ['start', 'pause', 'stop']);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_time_logs');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_subtasks');
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
    }
};
