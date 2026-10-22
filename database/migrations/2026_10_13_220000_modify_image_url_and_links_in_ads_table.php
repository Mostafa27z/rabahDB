<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->text('image_url')->nullable()->change();
            $table->text('button_link')->nullable()->change();
            $table->text('cta_link')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->string('image_url', 255)->nullable()->change();
            $table->string('button_link', 255)->nullable()->change();
            $table->string('cta_link', 255)->nullable()->change();
        });
    }
};
