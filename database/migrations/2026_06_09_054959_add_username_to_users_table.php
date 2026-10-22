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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        // Seed username for existing users
        $users = \App\Models\User::all();
        foreach ($users as $user) {
            $emailPrefix = explode('@', $user->email)[0];
            $baseUsername = \Illuminate\Support\Str::slug($emailPrefix);

            if (empty($baseUsername)) {
                $baseUsername = \Illuminate\Support\Str::slug($user->name);
            }
            if (empty($baseUsername)) {
                $baseUsername = 'user';
            }

            $username = $baseUsername;
            $counter = 1;
            while (\App\Models\User::where('username', $username)->exists()) {
                $username = $baseUsername . '-' . $counter;
                $counter++;
            }

            $user->username = $username;
            $user->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
