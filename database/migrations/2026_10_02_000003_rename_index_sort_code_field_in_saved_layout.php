<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameField('rj', 'product_code');
    }

    public function down(): void
    {
        $this->renameField('product_code', 'rj');
    }

    private function renameField(string $from, string $to): void
    {
        $query = DB::table('options')->where('key', 'index_sort_field_layout');
        $value = $query->value('value');

        if ($value === null) {
            return;
        }

        $layout = collect(json_decode($value, true, 512, JSON_THROW_ON_ERROR));

        if (! $layout->contains('field', $from)) {
            return;
        }

        $layout = $layout->map(
            fn($row) => ($row['field'] ?? null) === $from
                ? [...$row, 'field' => $to]
                : $row
        );

        $query->update([
            'value' => $layout->toJson(JSON_THROW_ON_ERROR),
        ]);
    }
};
