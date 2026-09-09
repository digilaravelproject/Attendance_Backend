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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Morning Shift
            $table->string('shift_type')->default('Morning'); // Morning, Evening, Night, General
            $table->string('start_time'); // e.g. 09:00 AM or 09:00
            $table->string('end_time'); // e.g. 06:00 PM or 18:00
            $table->string('break_duration')->default('01:00'); // hh:mm e.g. 01:00
            $table->string('total_duration')->nullable(); // e.g. 8h 00m
            $table->string('grace_time_late')->default('00:15'); // e.g. 00:15
            $table->string('overtime_after')->default('08:00'); // e.g. 08:00
            $table->text('description')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
