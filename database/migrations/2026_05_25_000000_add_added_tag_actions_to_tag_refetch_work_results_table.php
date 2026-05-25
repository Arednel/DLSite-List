<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_refetch_work_results', function (Blueprint $table): void {
            $table->string('added_japanese_action', 32)
                ->default('add_as_fetched')
                ->after('error');
            $table->string('added_english_action', 32)
                ->default('add_as_fetched')
                ->after('added_japanese_action');
        });
    }

    public function down(): void
    {
        Schema::table('tag_refetch_work_results', function (Blueprint $table): void {
            $table->dropColumn([
                'added_japanese_action',
                'added_english_action',
            ]);
        });
    }
};
