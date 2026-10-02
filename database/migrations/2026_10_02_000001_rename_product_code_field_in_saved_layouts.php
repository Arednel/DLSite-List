<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LAYOUT_KEYS = [
        'quick_add_field_layout',
        'bulk_import_field_layout',
        'custom_quick_add_field_layout',
    ];

    public function up(): void
    {
        $this->renameField('rj_code', 'product_code');
    }

    public function down(): void
    {
        $this->renameField('product_code', 'rj_code');
    }

    private function renameField(string $from, string $to): void
    {
        $options = DB::table('options')->whereIn('key', self::LAYOUT_KEYS)->get(['id', 'value']);

        foreach ($options as $option) {
            $layout = json_decode((string) $option->value, true, 512, JSON_THROW_ON_ERROR);

            $changed = false;
            foreach ($layout as &$row) {
                if (is_array($row) && ($row['field'] ?? null) === $from) {
                    $row['field'] = $to;
                    $changed = true;
                }
            }
            unset($row);

            if ($changed) {
                DB::table('options')->where('id', $option->id)->update([
                    'value' => json_encode($layout, JSON_THROW_ON_ERROR),
                ]);
            }
        }
    }
};
