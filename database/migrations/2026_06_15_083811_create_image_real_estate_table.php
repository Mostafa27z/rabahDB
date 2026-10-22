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
        Schema::create('image_real_estate', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('real_estate_id')->unique();
            $table->text('cover_image_original')->nullable();
            $table->text('cover_image_watermark')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_real_estate');
    }
};
