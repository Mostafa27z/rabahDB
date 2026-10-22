<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (!Schema::hasColumn('ads', 'distinction')) {
                $table->string('distinction')->nullable()->after('title');
            }
            if (!Schema::hasColumn('ads', 'description')) {
                $table->text('description')->nullable()->after('distinction');
            }
            if (!Schema::hasColumn('ads', 'button_title')) {
                $table->string('button_title')->nullable()->after('cta_text');
            }
            if (!Schema::hasColumn('ads', 'button_link')) {
                $table->string('button_link')->nullable()->after('cta_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('ads', 'distinction')) $columns[] = 'distinction';
            if (Schema::hasColumn('ads', 'description')) $columns[] = 'description';
            if (Schema::hasColumn('ads', 'button_title')) $columns[] = 'button_title';
            if (Schema::hasColumn('ads', 'button_link')) $columns[] = 'button_link';

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
