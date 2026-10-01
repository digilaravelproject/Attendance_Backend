<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('due_date')->index();
        });

        // Preserve a usable completion timestamp for tasks completed before this
        // column existed. Future changes are maintained by TaskController.
        DB::table('tasks')
            ->where('status', 'Completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);

        Schema::create('task_quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('deliverable_accuracy');
            $table->unsignedTinyInteger('deadline_adherence');
            $table->unsignedTinyInteger('defect_prevention');
            $table->unsignedTinyInteger('collaboration');
            $table->text('feedback')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->unique(['task_id', 'employee_id', 'reviewer_id'], 'task_employee_reviewer_unique');
            $table->index(['employee_id', 'reviewed_at']);
        });

        Schema::create('performance_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['sender_id', 'receiver_id', 'created_at'], 'performance_message_conversation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_messages');
        Schema::dropIfExists('task_quality_reviews');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
