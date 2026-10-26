<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile_number', 30)->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('Active');
            $table->text('notes')->nullable();
            $table->foreignId('lead_id')->nullable()->unique()->constrained('leads')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'name']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->unique()->after('id')->constrained('leads')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->after('lead_id')->constrained('clients')->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('Standard')->after('category');
            $table->unsignedInteger('working_days')->nullable()->after('end_date');
            $table->decimal('agreed_value', 15, 2)->nullable()->after('working_days');
            $table->text('deliverables')->nullable()->after('description');
            $table->text('special_instructions')->nullable()->after('deliverables');
        });

        Schema::create('client_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('subject');
            $table->dateTime('communicated_at');
            $table->string('recipient');
            $table->text('description');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'type', 'communicated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_communications');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_manager_id');
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('lead_id');
            $table->dropColumn(['priority', 'working_days', 'agreed_value', 'deliverables', 'special_instructions']);
        });

        Schema::dropIfExists('clients');
    }
};
