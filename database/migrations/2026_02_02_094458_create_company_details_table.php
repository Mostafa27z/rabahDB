<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_details', function (Blueprint $table) {
            $table->id();

            // Contact info
            $table->string('phone')->nullable();          // الهاتف
            $table->string('mobile')->nullable();         // موبايل
            $table->string('management_email')->nullable(); // الإدارة
            $table->string('clients_email')->nullable();    // العملاء
            $table->string('support_email')->nullable();    // الدعم
            $table->string('career_email')->nullable();     // التوظيف

            // Social links
            $table->string('linkedin')->nullable();
            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('instagram')->nullable();
            $table->string('whatsapp')->nullable(); // link

            // Work times
            $table->string('first_shift')->nullable();
            $table->string('second_shift')->nullable();
            $table->string('holiday')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_details');
    }
};
