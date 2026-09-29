<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_request_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['leave_request_id', 'user_id']);
        });

        DB::table('leave_requests')->whereNotNull('assigned_to_user_id')->orderBy('id')->each(function ($leave) {
            DB::table('leave_request_assignees')->insertOrIgnore([
                'leave_request_id' => $leave->id,
                'user_id' => $leave->assigned_to_user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_assignees');
    }
};
