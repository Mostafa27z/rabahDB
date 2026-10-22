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
        Schema::table('company_details', function (Blueprint $table) {
            $table->string('watermark_image')->nullable();
            $table->string('watermark_type')->nullable()->default('navbar_logo');
            $table->string('watermark_position')->nullable()->default('center');
            $table->integer('watermark_opacity')->nullable()->default(30);
            $table->integer('watermark_size')->nullable()->default(20);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_details', function (Blueprint $table) {
            $table->dropColumn([
                'watermark_image',
                'watermark_type',
                'watermark_position',
                'watermark_opacity',
                'watermark_size'
            ]);
        });
    }
};
