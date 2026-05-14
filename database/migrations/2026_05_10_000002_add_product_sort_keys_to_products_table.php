<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('rj_number')->nullable()->after('id');
            $table->unsignedInteger('start_date_sort')->nullable()->after('start_date');
            $table->unsignedInteger('end_date_sort')->nullable()->after('end_date');
        });

        DB::table('products')
            ->select(['id', 'start_date', 'end_date'])
            ->orderBy('id')
            ->chunk(500, function ($products): void {
                foreach ($products as $product) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            'rj_number' => $this->rjNumber($product->id),
                            'start_date_sort' => $this->dateSortValue($product->start_date),
                            'end_date_sort' => $this->dateSortValue($product->end_date),
                        ]);
                }
            });

        Schema::table('products', function (Blueprint $table): void {
            $table->index('rj_number', 'products_rj_number_index');
            $table->index('start_date_sort', 'products_start_date_sort_index');
            $table->index('end_date_sort', 'products_end_date_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_rj_number_index');
            $table->dropIndex('products_start_date_sort_index');
            $table->dropIndex('products_end_date_sort_index');

            $table->dropColumn([
                'rj_number',
                'start_date_sort',
                'end_date_sort',
            ]);
        });
    }

    private function rjNumber(?string $id): ?int
    {
        if ($id === null || ! preg_match('/^RJ(\d+)$/i', $id, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function dateSortValue(mixed $date): ?int
    {
        if (is_string($date)) {
            $decoded = json_decode($date, true);
            $date = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (! is_array($date)) {
            return null;
        }

        $year = $this->dateSortPart($date['year'] ?? null);
        $month = $this->dateSortPart($date['month'] ?? null);
        $day = $this->dateSortPart($date['day'] ?? null);

        if ($year === null && $month === null && $day === null) {
            return null;
        }

        return (int) sprintf('%04d%02d%02d', $year ?? 0, $month ?? 0, $day ?? 0);
    }

    private function dateSortPart(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
};
