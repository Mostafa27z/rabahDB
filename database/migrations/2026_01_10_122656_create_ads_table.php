<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('content');

            $table->decimal('discount', 8, 2)->default(0);
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');

            $table->unsignedBigInteger('bitrix_item_id')->nullable();

            $table->string('image_url')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_link')->nullable();

            $table->enum('status', ['draft', 'active', 'paused', 'expired'])->default('draft');

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->integer('priority')->default(0);
            $table->integer('max_views')->nullable();

            $table->timestamps();

            // Optional index for fast lookup
            $table->index('bitrix_item_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
