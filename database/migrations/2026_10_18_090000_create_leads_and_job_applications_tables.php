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
        if (!Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone');
                $table->text('message')->nullable();
                $table->string('available_time')->nullable();
                $table->string('source')->default('web');
                $table->string('status')->default('new'); // new, contacted, qualified, lost, converted
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('job_applications')) {
            Schema::create('job_applications', function (Blueprint $table) {
                $table->id();
                $table->string('full_name');
                $table->string('phone');
                $table->string('email');
                $table->integer('experience')->nullable()->default(0);
                $table->string('cv_path')->nullable();
                $table->string('photo_path')->nullable();
                $table->string('linkedin')->nullable();
                $table->string('facebook')->nullable();
                $table->string('instagram')->nullable();
                $table->string('twitter')->nullable();
                $table->string('tiktok')->nullable();
                $table->text('reason')->nullable();
                $table->string('status')->default('pending'); // pending, reviewed, interviewed, accepted, rejected
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('leads');
    }
};
