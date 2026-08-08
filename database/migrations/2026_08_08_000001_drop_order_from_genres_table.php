<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('genres', function (Blueprint $table): void {
            $table->dropColumn('order');
        });
    }

    public function down(): void
    {
        Schema::table('genres', function (Blueprint $table): void {
            $table->integer('order')->nullable()->default(null)->after('description');
        });
    }
};
