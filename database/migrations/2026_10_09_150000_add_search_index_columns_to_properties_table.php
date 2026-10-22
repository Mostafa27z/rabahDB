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
        Schema::table('properties', function (Blueprint $table) {
            $table->text('search_title')->nullable()->after('seo_keywords');
            $table->mediumText('search_text')->nullable()->after('search_title');
            $table->unsignedTinyInteger('search_bedrooms')->nullable()->index()->after('search_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['search_bedrooms']);
            $table->dropColumn(['search_title', 'search_text', 'search_bedrooms']);
        });
    }
};
