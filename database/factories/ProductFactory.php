<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = $this->nextProductId();

        return [
            'id' => $id,
            'maker_id' => 'RG' . substr($id, 2),
            'work_name' => 'WORK_' . $id,
            'work_name_english' => 'WORK_EN_' . $id,
            'age_category' => 'ALL_AGES',
            'circle' => 'Circle',
            'work_image' => "storage/Works/{$id}/cover.jpg",
            'description' => 'Description',
            'description_english' => 'Description English',
            'notes' => null,
            'sample_images' => json_encode([]),
            'score' => null,
            'series' => null,
            'progress' => 'Plan to Listen',
            'start_date' => null,
            'end_date' => null,
            'num_re_listen_times' => null,
            're_listen_value' => null,
            'priority' => null,
        ];
    }

    public function listening(): static
    {
        return $this->state(fn(array $attributes) => [
            'progress' => 'Listening',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'progress' => 'Completed',
        ]);
    }

    private function nextProductId(): string
    {
        do {
            $id = 'RJ' . $this->faker->numberBetween(100000000, 999999999);
        } while (Product::query()->whereKey($id)->exists());

        return $id;
    }
}
