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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('mobile_number');
            $table->string('department')->nullable()->after('phone');
            $table->string('designation')->nullable()->after('department');
            $table->string('employee_id')->nullable()->after('designation');
            $table->date('date_of_joining')->nullable()->after('employee_id');
            $table->text('address')->nullable()->after('date_of_joining');
            $table->string('avatar')->nullable()->after('address');
            $table->string('status')->default('Active')->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'department',
                'designation',
                'employee_id',
                'date_of_joining',
                'address',
                'avatar',
                'status',
            ]);
        });
    }
};
