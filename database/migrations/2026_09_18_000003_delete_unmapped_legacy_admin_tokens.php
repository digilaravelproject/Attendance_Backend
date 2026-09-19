<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tokens left from a previously removed Admin model cannot be authenticated safely.
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\Admin')
            ->delete();
    }

    public function down(): void
    {
        // Deleted authentication tokens cannot and should not be recreated.
    }
};
