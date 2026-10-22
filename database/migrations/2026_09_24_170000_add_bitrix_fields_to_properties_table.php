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
            if (!Schema::hasColumn('properties', 'company_details')) {
                $table->longText('company_details')->nullable()->after('description');
            }
            if (!Schema::hasColumn('properties', 'company_images')) {
                $table->json('company_images')->nullable()->after('images');
            }
            if (!Schema::hasColumn('properties', 'source_name')) {
                $table->string('source_name', 255)->nullable()->after('assets_notes');
            }
            if (!Schema::hasColumn('properties', 'source_files')) {
                $table->json('source_files')->nullable()->after('source_name');
            }
            if (!Schema::hasColumn('properties', 'source_notes')) {
                $table->text('source_notes')->nullable()->after('source_files');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'company_details',
                'company_images',
                'source_name',
                'source_files',
                'source_notes',
            ]);
        });
    }
};
