<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leads', 'created_by')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('assigned_to')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leads', 'created_by')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
