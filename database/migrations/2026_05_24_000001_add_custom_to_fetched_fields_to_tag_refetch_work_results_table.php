<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_refetch_work_results', function (Blueprint $table): void {
            $table->json('custom_to_fetched_japanese_tags')->nullable()->after('stale_english_tags');
            $table->json('custom_to_fetched_english_tags')->nullable()->after('custom_to_fetched_japanese_tags');
            $table->string('custom_to_fetched_action', 32)
                ->default('promote_to_fetched')
                ->after('stale_english_action');
        });
    }

    public function down(): void
    {
        Schema::table('tag_refetch_work_results', function (Blueprint $table): void {
            $table->dropColumn([
                'custom_to_fetched_japanese_tags',
                'custom_to_fetched_english_tags',
                'custom_to_fetched_action',
            ]);
        });
    }
};
