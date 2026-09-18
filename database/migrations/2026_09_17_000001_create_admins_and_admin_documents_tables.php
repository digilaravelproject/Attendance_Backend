<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('employee_id')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->text('address')->nullable();
            $table->string('avatar')->nullable();
            $table->string('status')->default('Active');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('admin_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_documents');
        Schema::dropIfExists('admins');
    }
};
