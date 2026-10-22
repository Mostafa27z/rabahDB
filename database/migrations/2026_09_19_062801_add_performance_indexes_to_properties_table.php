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
            $table->index(['status', 'created_at'], 'idx_properties_status_created');
            $table->index(['status', 'updated_at'], 'idx_properties_status_updated');
            $table->index(['status', 'price'], 'idx_properties_status_price');
            $table->index(['purpose', 'status'], 'idx_properties_purpose_status');
            $table->index(['is_featured', 'status'], 'idx_properties_featured_status');
            $table->index('is_back_to_market', 'idx_properties_back_market');
            $table->index('is_new', 'idx_properties_is_new');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('idx_properties_status_created');
            $table->dropIndex('idx_properties_status_updated');
            $table->dropIndex('idx_properties_status_price');
            $table->dropIndex('idx_properties_purpose_status');
            $table->dropIndex('idx_properties_featured_status');
            $table->dropIndex('idx_properties_back_market');
            $table->dropIndex('idx_properties_is_new');
        });
    }
};
