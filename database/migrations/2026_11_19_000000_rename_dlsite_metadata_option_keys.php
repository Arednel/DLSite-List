<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEYS = [
        'fetched_tag_language_follows_ui' => 'dlsite_metadata_language_follows_ui',
        'fetched_tag_language' => 'dlsite_metadata_language',
    ];

    public function up(): void
    {
        foreach (self::KEYS as $from => $to) {
            $this->renameKey($from, $to);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::KEYS, true) as $from => $to) {
            $this->renameKey($to, $from);
        }
    }

    private function renameKey(string $from, string $to): void
    {
        $source = DB::table('options')->where('key', $from)->first(['id']);
        if ($source === null) {
            return;
        }

        if (DB::table('options')->where('key', $to)->exists()) {
            DB::table('options')->where('id', $source->id)->delete();

            return;
        }

        DB::table('options')->where('id', $source->id)->update(['key' => $to]);
    }
};
