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
        if (!Schema::hasTable('property_submissions')) {
            Schema::create('property_submissions', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->nullable()->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('client_name');
                $table->string('contact_phone');
                $table->string('email')->nullable();
                $table->string('title');
                $table->string('property_type')->nullable();
                $table->string('area_name')->nullable();
                $table->decimal('area_sqm', 12, 2)->nullable();
                $table->decimal('price', 15, 2)->nullable();
                $table->decimal('price_per_sqm', 15, 2)->nullable();
                $table->text('property_details')->nullable();
                $table->json('images')->nullable();
                $table->json('pdf_file')->nullable();
                $table->string('status')->default('new')->index(); // new, contacted, inspecting, published, rejected
                $table->string('priority')->default('normal')->index(); // low, normal, high, urgent
                $table->text('notes')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('converted_property_id')->nullable()->constrained('properties')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_submissions');
    }
};

