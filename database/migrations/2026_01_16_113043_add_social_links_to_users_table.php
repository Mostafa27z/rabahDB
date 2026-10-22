<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('instagram_link')->nullable()->after('phone');
            $table->string('tiktok_link')->nullable()->after('instagram_link');
            $table->string('whatsapp_link')->nullable()->after('tiktok_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['instagram_link', 'tiktok_link', 'whatsapp_link']);
        });
    }
};
