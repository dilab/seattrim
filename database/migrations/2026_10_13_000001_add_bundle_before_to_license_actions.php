<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_actions', function (Blueprint $table) {
            // Bundle option on the user before a downgrade ({"zoom_one_type": 16} or {"plan_united_type": "16"}),
            // copied onto the matching restore row so the bundle can be put back with the seat.
            $table->json('bundle_before')->nullable()->after('to_type');
        });
    }

    public function down(): void
    {
        Schema::table('license_actions', function (Blueprint $table) {
            $table->dropColumn('bundle_before');
        });
    }
};
