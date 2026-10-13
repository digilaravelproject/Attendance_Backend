<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_code', 30)->nullable()->unique();
            $table->string('source', 40)->index();
            $table->string('external_id', 191)->nullable();
            $table->string('full_name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('mobile_number', 40)->nullable()->index();
            $table->string('designation')->nullable();
            $table->text('requirement')->nullable();
            $table->string('status', 40)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location')->nullable();
            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->date('expected_closing_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id'], 'leads_source_external_unique');
            $table->index(['source', 'status']);
        });

        Schema::create('lead_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['lead_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_histories');
        Schema::dropIfExists('leads');
    }
};
