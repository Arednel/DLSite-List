<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('rj_number', 'code_number');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->renameIndex('products_rj_number_index', 'products_code_number_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->renameIndex('products_code_number_index', 'products_rj_number_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('code_number', 'rj_number');
        });
    }
};
