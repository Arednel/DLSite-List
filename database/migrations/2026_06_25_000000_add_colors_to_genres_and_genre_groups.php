<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('genres', function (Blueprint $table): void {
            $table->string('color', 7)->nullable()->after('hidden_on_index');
            $table->string('text_color', 7)->nullable()->after('color');
        });

        Schema::table('genre_groups', function (Blueprint $table): void {
            $table->string('color', 7)->nullable()->after('hidden_on_index');
            $table->string('text_color', 7)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('genres', function (Blueprint $table): void {
            $table->dropColumn(['color', 'text_color']);
        });

        Schema::table('genre_groups', function (Blueprint $table): void {
            $table->dropColumn(['color', 'text_color']);
        });
    }
};
