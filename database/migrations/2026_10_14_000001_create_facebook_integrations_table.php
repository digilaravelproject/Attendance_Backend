<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('state', 128)->unique();
            $table->string('client_state', 255)->nullable();
            $table->string('client_id', 255);
            $table->text('client_secret');
            $table->text('redirect_uri');
            $table->text('access_token')->nullable();
            $table->string('token_type', 40)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('page_id', 191)->nullable()->index();
            $table->string('page_name')->nullable();
            $table->text('page_access_token')->nullable();
            $table->string('status', 40)->default('awaiting_authorization')->index();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_integrations');
    }
};
