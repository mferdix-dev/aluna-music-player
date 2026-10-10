<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user');
        });
        Schema::table('tracks', function (Blueprint $table) {
            $table->boolean('is_public')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tracks', fn (Blueprint $table) => $table->dropColumn('is_public'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
