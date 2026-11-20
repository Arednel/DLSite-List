<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ProductMetadataMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const BACKFILL = '2026_05_30_000002_backfill_product_metadata_from_storage.php';

    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => array_values(array_filter(
                glob(database_path('migrations/*.php')),
                fn(string $path): bool => basename($path) < self::BACKFILL,
            )),
            '--realpath' => true,
        ];
    }

    public function test_backfill_skips_missing_json(): void
    {
        Storage::fake('local');

        $this->insertProduct('RJ999999', [
            'circle' => 'Existing Circle',
        ]);

        $this->runBackfill();

        $this->assertSame('Existing Circle', DB::table('products')->where('id', 'RJ999999')->value('circle'));
    }

    public function test_backfill_skips_invalid_json(): void
    {
        Storage::fake('local');

        $this->insertProduct('RJ888888', [
            'description' => 'Existing Description',
        ]);
        Storage::disk('local')->put('Works/RJ888888.json', '{not json');

        $this->runBackfill();

        $this->assertSame('Existing Description', DB::table('products')->where('id', 'RJ888888')->value('description'));
    }

    public function test_historical_backfill_preserves_columns_identity_role_memberships_and_timestamps(): void
    {
        Storage::fake('local');
        $this->insertProduct('RJ900001', ['notes' => 'Personal notes', 'series' => 'Personal series', 'rj_number' => 777]);
        $this->insertProduct('RJ900002');
        $shared = $this->insertContributor('SHARED NAME');
        $circle = $this->insertContributor('Circle', 'RGKEEP');
        $stale = $this->insertContributor('Stale voice');
        DB::table('contributor_product')->insert([
            ['product_id' => 'RJ900001', 'contributor_id' => $shared, 'role' => 'scenario', 'created_at' => '2000-01-01', 'updated_at' => '2000-01-01'],
            ['product_id' => 'RJ900001', 'contributor_id' => $shared, 'role' => 'legacy_role', 'created_at' => '2000-01-01', 'updated_at' => '2000-01-01'],
            ['product_id' => 'RJ900001', 'contributor_id' => $stale, 'role' => 'voice_actor', 'created_at' => '2000-01-01', 'updated_at' => '2000-01-01'],
            ['product_id' => 'RJ900002', 'contributor_id' => $shared, 'role' => 'voice_actor', 'created_at' => '2000-01-01', 'updated_at' => '2000-01-01'],
        ]);
        Storage::disk('local')->put('Works/RJ900001.json', json_encode([
            'japanese' => [
                'maker_id' => ' RG100 ',
                'circle' => ' Circle ',
                'description' => ' Japanese description ',
                'scenario' => [' Shared Name ', 'shared name', '', []],
                'voice_actor' => ['Shared Name'],
                'author' => null,
                'creater' => [' Japanese Author '],
                'illustration' => ' Artist ',
            ],
            'english' => ['description' => ' English description ', 'author' => ['English Author']],
        ], JSON_THROW_ON_ERROR));
        $this->assertTrue(Schema::hasColumn('products', 'description_english'));
        $this->assertFalse(Schema::hasColumn('products', 'code_number'));
        $this->assertFalse(Schema::hasTable('product_localizations'));
        $before = (array) DB::table('products')->where('id', 'RJ900001')->first();
        $otherMembership = (array) DB::table('contributor_product')->where('product_id', 'RJ900002')->first();

        $this->runBackfill();

        $after = (array) DB::table('products')->where('id', 'RJ900001')->first();
        $this->assertSame(array_replace($before, [
            'maker_id' => 'RG100',
            'circle' => 'Circle',
            'description' => 'Japanese description',
            'description_english' => 'English description',
        ]), $after);
        $this->assertSame('RGKEEP', DB::table('contributors')->where('id', $circle)->value('maker_id'));
        $this->assertSame('SHARED NAME', DB::table('contributors')->where('id', $shared)->value('name'));
        $this->assertSame(1, DB::table('contributors')->where('name_key', 'shared name')->count());
        $roles = DB::table('contributor_product')->where('product_id', 'RJ900001')->get()->groupBy('role');
        $this->assertSame([$shared], $roles['scenario']->pluck('contributor_id')->all());
        $this->assertSame([$shared], $roles['voice_actor']->pluck('contributor_id')->all());
        $this->assertSame([$shared], $roles['legacy_role']->pluck('contributor_id')->all());
        $this->assertSame([$circle], $roles['circle']->pluck('contributor_id')->all());
        $this->assertSame('2000-01-01 00:00:00', $roles['scenario']->first()->created_at);
        $this->assertSame('2000-01-01 00:00:00', $roles['scenario']->first()->updated_at);
        $this->assertCount(1, $roles['author']);
        $this->assertSame('Japanese Author', DB::table('contributors')->where('id', $roles['author']->first()->contributor_id)->value('name'));
        $this->assertSame('Artist', DB::table('contributors')->where('id', $roles['illustration']->first()->contributor_id)->value('name'));
        $this->assertSame($otherMembership, (array) DB::table('contributor_product')->where('product_id', 'RJ900002')->first());
    }

    public function test_backfill_preserves_pre_refactor_description_normalization(): void
    {
        Storage::fake('local');
        $cases = [
            'RJ900041' => [[], ['description' => ' English only '], null, 'English only'],
            'RJ900042' => [['description' => null], ['description' => 'English only'], null, 'English only'],
            'RJ900043' => [['description' => ' '], ['description' => []], null, null],
            'RJ900044' => [['description' => ' Japanese '], ['description' => null], 'Japanese', null],
            'RJ900045' => [['intro_s' => ' Japanese alias '], ['intro_s' => ' English alias '], 'Japanese alias', 'English alias'],
            'RJ900046' => [['intro_masked' => ' Duplicate ', 'intro_s' => 'Ignored'], ['intro' => 'Duplicate'], 'Duplicate', null],
            'RJ900047' => [['description' => null, 'intro' => 'Ignored'], ['description' => '', 'intro' => 'Ignored'], null, null],
        ];
        foreach ($cases as $id => [$japanese, $english]) {
            $this->insertProduct($id, ['description' => 'Old Japanese', 'description_english' => 'Old English']);
            Storage::disk('local')->put("Works/{$id}.json", json_encode(['japanese' => $japanese, 'english' => $english], JSON_THROW_ON_ERROR));
        }

        $this->runBackfill();

        foreach ($cases as $id => [,, $description, $englishDescription]) {
            $this->assertDatabaseHas('products', [
                'id' => $id,
                'description' => $description,
                'description_english' => $englishDescription,
            ]);
        }
    }

    public function test_backfill_keeps_canonical_metadata_japanese_only_and_suppresses_duplicate_descriptions(): void
    {
        Storage::fake('local');
        $circleId = $this->insertContributor('Japanese Circle');
        foreach (
            [
                'RJ900011' => [],
                'RJ900012' => ['maker_id' => null, 'circle' => null, 'scenario' => null],
                'RJ900013' => ['maker_id' => '', 'circle' => '', 'scenario' => []],
                'RJ900014' => ['maker_id' => ' RG300 ', 'maker_name' => ' Japanese Circle ', 'scenario' => [' Japanese scenario ']],
            ] as $id => $japanese
        ) {
            $this->insertProduct($id);
            $japanese['description'] = ' Duplicate description ';
            $english = [
                'maker_id' => 'RG200',
                'circle' => 'Fallback Circle',
                'scenario' => ['Fallback scenario'],
                'description' => 'Duplicate description',
            ];
            Storage::disk('local')->put("Works/{$id}.json", json_encode(['japanese' => $japanese, 'english' => $english], JSON_THROW_ON_ERROR));
        }

        $this->runBackfill();

        foreach (['RJ900011', 'RJ900012', 'RJ900013', 'RJ900014'] as $id) {
            $hasJapaneseMetadata = $id === 'RJ900014';
            $product = DB::table('products')->where('id', $id)->first();
            $this->assertSame($hasJapaneseMetadata ? 'RG300' : null, $product->maker_id);
            $this->assertSame($hasJapaneseMetadata ? 'Japanese Circle' : null, $product->circle);
            $this->assertSame('Duplicate description', $product->description);
            $this->assertNull($product->description_english);
            $names = DB::table('contributor_product')->join('contributors', 'contributors.id', '=', 'contributor_product.contributor_id')
                ->where('product_id', $id)->where('role', 'scenario')->pluck('name')->all();
            $this->assertSame($hasJapaneseMetadata ? ['Japanese scenario'] : [], $names);
        }
        $this->assertSame('RG300', DB::table('contributors')->where('id', $circleId)->value('maker_id'));
    }

    public function test_historical_backfill_flows_through_later_localization_migrations_and_is_not_rerun(): void
    {
        Storage::fake('local');
        $this->insertProduct('RJ900021', ['work_name_english' => 'Existing English title']);
        Storage::disk('local')->put('Works/RJ900021.json', json_encode([
            'japanese' => ['description' => 'Japanese description', 'voice_actor' => ['Original voice']],
            'english' => ['description' => 'English description'],
        ], JSON_THROW_ON_ERROR));
        $this->runBackfill();
        Storage::disk('local')->put('Works/RJ900021.json', json_encode([
            'japanese' => ['description' => 'Would overwrite if rerun'],
        ], JSON_THROW_ON_ERROR));

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $this->assertFalse(Schema::hasColumn('products', 'description_english'));
        $this->assertFalse(Schema::hasColumn('products', 'work_name_english'));
        $this->assertTrue(Schema::hasColumn('products', 'code_number'));
        $this->assertSame('Japanese description', DB::table('products')->where('id', 'RJ900021')->value('description'));
        $this->assertDatabaseHas('product_localizations', [
            'product_id' => 'RJ900021',
            'locale' => 'en',
            'title' => 'Existing English title',
            'description' => 'English description',
        ]);
        $this->assertSame(1, DB::table('migrations')->where('migration', pathinfo(self::BACKFILL, PATHINFO_FILENAME))->count());
    }

    public function test_backfill_preserves_explicit_canonical_clears_and_uses_the_existing_product_id(): void
    {
        Storage::fake('local');
        $this->insertProduct('RJ900051', ['circle' => 'Old circle']);
        $author = $this->insertContributor('Old author');
        DB::table('contributor_product')->insert([
            'product_id' => 'RJ900051',
            'contributor_id' => $author,
            'role' => 'author',
            'created_at' => '2000-01-01',
            'updated_at' => '2000-01-01',
        ]);
        Storage::disk('local')->put('Works/RJ900051.json', json_encode([
            'japanese' => [
                'product_id' => '',
                'maker_id' => 'RG300',
                'maker_name' => 'Ignored alias',
                'circle' => '',
                'author' => [],
                'creater' => ['Ignored creator'],
                'description' => 'Imported description',
            ],
            'english' => ['circle' => 'Ignored English circle', 'author' => ['Ignored English author']],
        ], JSON_THROW_ON_ERROR));

        $this->runBackfill();

        $this->assertDatabaseHas('products', [
            'id' => 'RJ900051',
            'maker_id' => 'RG300',
            'circle' => null,
            'description' => 'Imported description',
            'description_english' => null,
            'updated_at' => '2000-01-01 00:00:00',
        ]);
        $this->assertSame(0, DB::table('contributor_product')->where('product_id', 'RJ900051')->count());
        $this->assertSame(1, DB::table('contributors')->count());
    }

    public function test_database_write_errors_escape_the_historical_backfill(): void
    {
        Storage::fake('local');
        $this->insertProduct('RJ900031');
        Storage::disk('local')->put('Works/RJ900031.json', '{"japanese":{"description":"Valid"}}');
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = clone $originalDispatcher;
        $dispatcher->listen(QueryExecuted::class, static function (QueryExecuted $query): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'update') && str_contains($query->sql, 'description_english')) {
                throw new RuntimeException('Simulated historical backfill write failure.');
            }
        });
        $connection->setEventDispatcher($dispatcher);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Simulated historical backfill write failure.');
            (require database_path('migrations/' . self::BACKFILL))->up();
        } finally {
            $connection->setEventDispatcher($originalDispatcher);
        }
    }

    private function runBackfill(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/' . self::BACKFILL, '--force' => true])->assertExitCode(0);
    }

    private function insertProduct(string $id, array $attributes = []): void
    {
        DB::table('products')->insert(array_replace([
            'id' => $id,
            'work_name' => 'Existing Japanese title',
            'created_at' => '2000-01-01',
            'updated_at' => '2000-01-01',
        ], $attributes));
    }

    private function insertContributor(string $name, ?string $makerId = null): int
    {
        return DB::table('contributors')->insertGetId([
            'name' => $name,
            'name_key' => mb_convert_case($name, MB_CASE_FOLD, 'UTF-8'),
            'maker_id' => $makerId,
            'created_at' => '2000-01-01',
            'updated_at' => '2000-01-01',
        ]);
    }
}
