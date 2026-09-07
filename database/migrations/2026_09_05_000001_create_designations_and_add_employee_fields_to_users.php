<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('hierarchy_level', 20);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('designation_id')
                ->nullable()
                ->after('designation')
                ->constrained('designations')
                ->nullOnDelete();
            $table->string('emergency_contact', 30)->nullable()->after('mobile_number');
            $table->decimal('monthly_salary', 12, 2)->nullable()->after('date_of_joining');
            $table->json('skills')->nullable()->after('monthly_salary');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
            $table->dropColumn(['emergency_contact', 'monthly_salary', 'skills']);
        });

        Schema::dropIfExists('designations');
    }
};
