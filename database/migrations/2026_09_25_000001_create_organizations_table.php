<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedInteger('seat_price_cents')->default(14900);
            $table->date('renewal_date')->nullable();
            $table->string('billing_cycle', 16)->nullable(); // annual | monthly
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16); // owner | admin | viewer
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')->nullable()->after('id')
                ->constrained('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_organization_id');
        });
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
