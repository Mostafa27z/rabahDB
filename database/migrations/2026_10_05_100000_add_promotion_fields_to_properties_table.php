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
            $table->boolean('is_promoted')->default(false)->after('status');
            $table->timestamp('promoted_at')->nullable()->after('is_promoted');
            $table->foreignId('promoted_by')->nullable()->constrained('users')->nullOnDelete()->after('promoted_at');
            $table->foreignId('promoted_designer_id')->nullable()->constrained('users')->nullOnDelete()->after('promoted_by');
            $table->json('campaign_links')->nullable()->after('promoted_designer_id');
            $table->text('designer_notes')->nullable()->after('campaign_links');
            $table->timestamp('promotion_completed_at')->nullable()->after('designer_notes');

            $table->index('is_promoted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['promoted_by']);
            $table->dropForeign(['promoted_designer_id']);
            $table->dropIndex(['is_promoted']);
            $table->dropColumn([
                'is_promoted',
                'promoted_at',
                'promoted_by',
                'promoted_designer_id',
                'campaign_links',
                'designer_notes',
                'promotion_completed_at',
            ]);
        });
    }
};
