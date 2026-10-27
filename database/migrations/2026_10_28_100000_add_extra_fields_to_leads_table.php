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
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'location')) {
                $table->string('location')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('leads', 'property_type')) {
                $table->string('property_type')->nullable()->after('location');
            }
            if (!Schema::hasColumn('leads', 'notes')) {
                $table->text('notes')->nullable()->after('message');
            }
            if (!Schema::hasColumn('leads', 'priority')) {
                $table->string('priority')->default('normal')->after('status');
            }
            if (!Schema::hasColumn('leads', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->after('priority');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['location', 'property_type', 'notes', 'priority', 'assigned_to']);
        });
    }
};
