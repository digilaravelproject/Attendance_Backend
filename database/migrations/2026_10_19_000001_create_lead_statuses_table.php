<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug', 40)->unique();
            $table->string('category', 40)->index();
            $table->text('description')->nullable();
            $table->string('badge_color', 7)->default('#3B82F6');
            $table->unsignedInteger('order_position')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('lead_statuses')->insert([
            ['name' => 'New', 'slug' => 'new', 'category' => 'prospecting', 'description' => 'Fresh lead captured, pending first outreach call.', 'badge_color' => '#3B82F6', 'order_position' => 1, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Interested', 'slug' => 'interested', 'category' => 'active_lead', 'description' => 'Client expressed interest and requested a proposal or demo.', 'badge_color' => '#10B981', 'order_position' => 2, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Follow-up', 'slug' => 'follow_up', 'category' => 'active_lead', 'description' => 'Callback scheduled; active discussions are ongoing.', 'badge_color' => '#F59E0B', 'order_position' => 3, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pending Call', 'slug' => 'pending_call', 'category' => 'active_lead', 'description' => 'Lead is waiting for the next call.', 'badge_color' => '#8B5CF6', 'order_position' => 4, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Contacted', 'slug' => 'contacted', 'category' => 'active_lead', 'description' => 'Initial contact has been completed.', 'badge_color' => '#6366F1', 'order_position' => 5, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Qualified', 'slug' => 'qualified', 'category' => 'active_lead', 'description' => 'Lead meets the qualification criteria.', 'badge_color' => '#0D9488', 'order_position' => 6, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Proposal Sent', 'slug' => 'proposal_sent', 'category' => 'active_lead', 'description' => 'Proposal or quotation has been sent.', 'badge_color' => '#7C3AED', 'order_position' => 7, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Converted', 'slug' => 'converted', 'category' => 'won', 'description' => 'Deal closed and advance payment received.', 'badge_color' => '#059669', 'order_position' => 8, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Unreachable', 'slug' => 'unreachable', 'category' => 'lost', 'description' => 'The lead could not be reached after repeated attempts.', 'badge_color' => '#EF4444', 'order_position' => 9, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Wrong Number', 'slug' => 'wrong_number', 'category' => 'lost', 'description' => 'The supplied contact number is invalid.', 'badge_color' => '#64748B', 'order_position' => 10, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lost', 'slug' => 'lost', 'category' => 'lost', 'description' => 'The opportunity has been closed as lost.', 'badge_color' => '#DC2626', 'order_position' => 11, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_statuses');
    }
};
