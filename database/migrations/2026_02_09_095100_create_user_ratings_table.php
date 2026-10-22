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
        Schema::create('user_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User being rated
            $table->foreignId('rater_id')->constrained('users')->onDelete('cascade'); // User giving the rating
            $table->tinyInteger('rating')->unsigned(); // 1-5 stars
            $table->text('comment')->nullable();
            $table->timestamps();

            // Prevent duplicate ratings from same rater to same user
            $table->unique(['user_id', 'rater_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_ratings');
    }
};
