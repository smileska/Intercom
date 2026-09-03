<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->nullable()->after('role');
            $table->timestamp('last_active_at')->nullable()->after('last_seen_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('body');
        });

        Schema::table('channel_user', function (Blueprint $table) {
            $table->timestamp('last_read_at')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_active_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('channel_user', function (Blueprint $table) {
            $table->dropColumn('last_read_at');
        });
    }
};
