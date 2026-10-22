<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Ad;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (!Schema::hasColumn('ads', 'property_id')) {
                $table->unsignedBigInteger('property_id')->nullable()->after('bitrix_item_id')->index();
            }
        });

        // Backfill property_id from button_link or cta_link for existing ads
        try {
            $ads = Ad::all();
            foreach ($ads as $ad) {
                $link = $ad->button_link ?: $ad->cta_link;
                if ($link && preg_match('#/property/(\d+)#', $link, $matches)) {
                    $ad->property_id = (int)$matches[1];
                    $ad->save();
                }
            }
        } catch (\Throwable $e) {
            // Ignore during rollback/fresh scenarios
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (Schema::hasColumn('ads', 'property_id')) {
                $table->dropColumn('property_id');
            }
        });
    }
};
