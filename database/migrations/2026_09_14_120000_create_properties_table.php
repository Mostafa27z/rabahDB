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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bitrix_id')->nullable()->index();
            $table->string('reference')->nullable()->index();
            $table->string('title', 500);
            $table->longText('description')->nullable();
            $table->longText('property_details')->nullable();
            $table->string('property_type')->nullable()->index();
            $table->string('purpose')->default('sale'); // sale, rent
            
            // Financial & Investment metrics
            $table->decimal('price', 15, 2)->nullable()->index();
            $table->decimal('previous_price', 15, 2)->nullable();
            $table->string('currency', 10)->default('AED');
            $table->timestamp('price_changed_at')->nullable();
            $table->decimal('rental_income', 15, 2)->nullable();
            $table->decimal('roi_percentage', 5, 2)->nullable()->index();
            $table->integer('property_age')->nullable();
            $table->decimal('area_sqft', 12, 2)->nullable()->index();
            $table->decimal('price_per_foot', 12, 2)->nullable();
            
            // Location
            $table->string('area_name')->nullable()->index();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->text('google_map_url')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Status & Flags
            $table->string('status')->default('new')->index(); // new, available, under_procedure, sold, rented
            $table->timestamp('status_changed_at')->nullable();
            $table->string('priority')->default('normal'); // low, normal, high, hot_deal
            $table->boolean('is_back_to_market')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(true);
            
            // Media & Files
            $table->text('cover_image')->nullable();
            $table->text('cover_image_watermark')->nullable();
            $table->json('images')->nullable();
            $table->text('brochure_pdf')->nullable();
            $table->text('assets_notes')->nullable();
            
            // Ownership & Responsibility
            $table->unsignedBigInteger('user_id')->nullable()->index(); // assigned agent
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
