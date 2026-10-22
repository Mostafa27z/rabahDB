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
            $table->string('facebook_link')->nullable()->after('whatsapp_link');
            $table->string('linkedin_link')->nullable()->after('facebook_link');
            $table->string('twitter_link')->nullable()->after('linkedin_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['facebook_link', 'linkedin_link', 'twitter_link']);
        });
    }
};
