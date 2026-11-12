<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductLocalization;
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
            'age_category' => 'ALL_AGES',
            'product_format' => null,
            'announce_date' => null,
            'circle' => 'Circle',
            'work_image' => "storage/Works/{$id}/cover.jpg",
            'description' => 'Description',
            'notes' => null,
            'sample_images' => [],
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

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            Product::withoutTouching(fn() => $product->syncLocalization(
                ProductLocalization::LOCALE_ENGLISH,
                [
                    'title' => 'WORK_EN_' . $product->id,
                    'description' => 'Description English',
                ],
            ));
        });
    }

    public function withTitles(string $japanese, ?string $english = null): static
    {
        return $this
            ->state(fn(array $attributes) => ['work_name' => $japanese])
            ->withEnglishTitle($english);
    }

    public function withEnglishTitle(?string $english): static
    {
        return $this->afterCreating(function (Product $product) use ($english): void {
            Product::withoutTouching(fn() => $product->syncLocalization(
                ProductLocalization::LOCALE_ENGLISH,
                ['title' => blank($english) ? null : $english],
            ));
        });
    }

    public function withEnglishDescription(?string $english): static
    {
        return $this->afterCreating(function (Product $product) use ($english): void {
            Product::withoutTouching(fn() => $product->syncLocalization(
                ProductLocalization::LOCALE_ENGLISH,
                ['description' => blank($english) ? null : $english],
            ));
        });
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
