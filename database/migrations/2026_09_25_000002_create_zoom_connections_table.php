<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoom_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('zoom_account_id')->index();
            $table->string('installer_zoom_user_id')->nullable();
            $table->string('installer_email')->nullable();
            $table->text('access_token')->nullable();   // encrypted cast
            $table->text('refresh_token')->nullable();  // encrypted cast
            $table->timestamp('expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status', 16)->default('active'); // active | revoked | error
            $table->text('last_error')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoom_connections');
    }
};
