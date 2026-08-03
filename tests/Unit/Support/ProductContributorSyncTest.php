<?php

namespace Tests\Unit\Support;

use App\Enums\ProductContributorRole;
use App\Models\Contributor;
use App\Models\Product;
use App\Support\ProductContributorSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductContributorSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deduplicates_contributor_names_by_case_folded_key(): void
    {
        $product = Product::factory()->create();

        app(ProductContributorSync::class)->sync($product, [
            ProductContributorRole::VoiceActor->value => ['Voice Name', 'voice name', 'VOICE NAME'],
        ]);

        $this->assertSame(1, Contributor::query()->count());
        $this->assertSame(
            ['Voice Name'],
            app(ProductContributorSync::class)->namesByRole($product)[ProductContributorRole::VoiceActor->value]
        );
    }

    public function test_circle_sync_persists_maker_id_for_existing_contributor(): void
    {
        $product = Product::factory()->create();
        $sync = app(ProductContributorSync::class);

        $sync->sync($product, [
            ProductContributorRole::Circle->value => ['Circle Name'],
        ]);

        $this->assertNull(Contributor::query()->where('name', 'Circle Name')->value('maker_id'));

        $sync->sync($product, [
            ProductContributorRole::Circle->value => ['circle name'],
        ], 'RG123456');

        $this->assertSame(1, Contributor::query()->count());
        $this->assertSame('RG123456', Contributor::query()->firstWhere('name_key', 'circle name')->maker_id);
        $this->assertSame(
            ['Circle Name'],
            $sync->namesByRole($product)[ProductContributorRole::Circle->value]
        );
    }

    public function test_sync_role_replaces_only_selected_role_and_preserves_other_roles(): void
    {
        $product = Product::factory()->create();
        $sync = app(ProductContributorSync::class);

        $sync->sync($product, [
            ProductContributorRole::VoiceActor->value => ['Old Voice'],
            ProductContributorRole::Scenario->value => ['Scenario Writer'],
        ]);

        $sync->syncRole($product, ProductContributorRole::VoiceActor, ['New Voice']);

        $namesByRole = $sync->namesByRole($product);

        $this->assertSame(['New Voice'], $namesByRole[ProductContributorRole::VoiceActor->value]);
        $this->assertSame(['Scenario Writer'], $namesByRole[ProductContributorRole::Scenario->value]);
        $this->assertDatabaseMissing('contributor_product', [
            'product_id' => $product->getKey(),
            'contributor_id' => DB::table('contributors')->where('name', 'Old Voice')->value('id'),
            'role' => ProductContributorRole::VoiceActor->value,
        ]);
    }

    public function test_sync_role_preserves_same_contributor_attached_to_other_roles(): void
    {
        $product = Product::factory()->create();
        $sync = app(ProductContributorSync::class);

        $sync->sync($product, [
            ProductContributorRole::VoiceActor->value => ['Shared Creator'],
            ProductContributorRole::Author->value => ['Shared Creator'],
        ]);

        $contributorId = Contributor::query()->where('name', 'Shared Creator')->value('id');

        $sync->syncRole($product, ProductContributorRole::VoiceActor, []);

        $this->assertDatabaseMissing('contributor_product', [
            'product_id' => $product->getKey(),
            'contributor_id' => $contributorId,
            'role' => ProductContributorRole::VoiceActor->value,
        ]);
        $this->assertDatabaseHas('contributor_product', [
            'product_id' => $product->getKey(),
            'contributor_id' => $contributorId,
            'role' => ProductContributorRole::Author->value,
        ]);
        $this->assertSame(
            ['Shared Creator'],
            $sync->namesByRole($product)[ProductContributorRole::Author->value]
        );
    }

    public function test_sync_role_touches_the_product_only_when_its_contributors_change(): void
    {
        $product = Product::factory()->create();
        $sync = app(ProductContributorSync::class);

        $this->assertTrue(
            $sync->syncRole($product, ProductContributorRole::VoiceActor, ['Voice Name'])
        );

        $updatedAt = $product->fresh()->updated_at;
        $this->travelTo($updatedAt->copy()->addMinute());

        $this->assertFalse(
            $sync->syncRole($product, ProductContributorRole::VoiceActor, ['voice name'])
        );
        $this->assertTrue($product->fresh()->updated_at->equalTo($updatedAt));

        $this->assertTrue(
            $sync->syncRole($product, ProductContributorRole::VoiceActor, ['New Voice'])
        );
        $this->assertTrue($product->fresh()->updated_at->greaterThan($updatedAt));

        $this->travelBack();
    }
}
