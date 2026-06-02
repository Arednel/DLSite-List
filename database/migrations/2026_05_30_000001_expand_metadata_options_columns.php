<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
            $table->text('description_english')->nullable()->change();
        });

        Schema::table('options', function (Blueprint $table): void {
            $table->text('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('options', function (Blueprint $table): void {
            $table->string('value')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('description', 1000)->nullable()->change();
            $table->string('description_english', 1000)->nullable()->change();
        });
    }
};
