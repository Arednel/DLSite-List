<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameAttachmentLanguage('jp', 'ja');

        DB::table('options')
            ->where('key', 'fetched_tag_language')
            ->where('value', 'jp')
            ->update(['value' => 'ja']);
    }

    public function down(): void
    {
        $this->renameAttachmentLanguage('ja', 'jp');

        DB::table('options')
            ->where('key', 'fetched_tag_language')
            ->where('value', 'ja')
            ->update(['value' => 'jp']);
    }

    private function renameAttachmentLanguage(string $from, string $to): void
    {
        DB::table('genre_product_languages as source')
            ->select('source.id')
            ->where('source.language', $from)
            ->whereExists(function ($query) use ($to): void {
                $query->selectRaw('1')
                    ->from('genre_product_languages as target')
                    ->whereColumn('target.genre_product_id', 'source.genre_product_id')
                    ->where('target.language', $to);
            })
            ->chunkById(500, function ($rows): void {
                DB::table('genre_product_languages')
                    ->whereIn('id', $rows->pluck('id'))
                    ->delete();
            }, 'source.id', 'id');

        DB::table('genre_product_languages')
            ->where('language', $from)
            ->update(['language' => $to]);
    }
};
