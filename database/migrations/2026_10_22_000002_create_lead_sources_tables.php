<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source_key', 60)->unique();
            $table->string('allocation_strategy', 30)->default('round_robin');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_configured')->default(false);
            $table->unsignedInteger('round_robin_cursor')->default(0);
            $table->string('color', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lead_source_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['lead_source_id', 'user_id']);
        });

        $now = now();
        DB::table('lead_sources')->insert([
            ['name' => 'Website', 'source_key' => 'website', 'allocation_strategy' => 'primary', 'is_active' => true, 'is_configured' => false, 'color' => '#10B981', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Google Ads', 'source_key' => 'google', 'allocation_strategy' => 'round_robin', 'is_active' => true, 'is_configured' => false, 'color' => '#EF4444', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Facebook Ads', 'source_key' => 'facebook', 'allocation_strategy' => 'round_robin', 'is_active' => true, 'is_configured' => false, 'color' => '#3B82F6', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'JustDial', 'source_key' => 'justdial', 'allocation_strategy' => 'round_robin', 'is_active' => true, 'is_configured' => false, 'color' => '#F97316', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Referral', 'source_key' => 'referral', 'allocation_strategy' => 'primary', 'is_active' => true, 'is_configured' => false, 'color' => '#8B5CF6', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_source_user');
        Schema::dropIfExists('lead_sources');
    }
};
