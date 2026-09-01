<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('avatar_color');
            $table->string('bio', 255)->nullable()->after('avatar_path');
            $table->timestamp('last_seen_at')->nullable()->after('bio');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('is_edited');
            $table->index(['receiver_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'bio', 'last_seen_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['receiver_id', 'read_at']);
            $table->dropColumn('read_at');
        });
    }
};