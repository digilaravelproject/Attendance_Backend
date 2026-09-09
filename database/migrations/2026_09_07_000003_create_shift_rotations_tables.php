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
        Schema::create('shift_rotations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Rotation - May 2025 - Week 5
            $table->date('start_date');
            $table->date('end_date');
            $table->string('frequency_cycle')->default('Weekly'); // Weekly, Bi-Weekly, Monthly
            $table->enum('status', ['Upcoming', 'Active', 'Completed'])->default('Upcoming');
            $table->timestamps();
        });

        Schema::create('shift_rotation_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_rotation_id')->constrained('shift_rotations')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('shift_rotation_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_rotation_id')->constrained('shift_rotations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['shift_rotation_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_rotation_users');
        Schema::dropIfExists('shift_rotation_shifts');
        Schema::dropIfExists('shift_rotations');
    }
};
