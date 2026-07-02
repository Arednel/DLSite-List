<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\GenreGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenreGroupRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_genres_relationship_orders_by_pivot_order_then_title_and_exposes_pivot_data(): void
    {
        $group = GenreGroup::query()->create([
            'title' => 'Ordered Group',
            'description' => null,
            'order' => 1,
        ]);

        $second = Genre::query()->create([
            'title' => 'Relationship Second',
            'description' => null,
            'order' => 1,
        ]);
        $thirdByTitle = Genre::query()->create([
            'title' => 'Relationship Zebra',
            'description' => null,
            'order' => 2,
        ]);
        $firstByTitle = Genre::query()->create([
            'title' => 'Relationship Alpha',
            'description' => null,
            'order' => 3,
        ]);

        $secondPivotId = $this->attachTagToGroup($group, $second, 2);
        $thirdPivotId = $this->attachTagToGroup($group, $thirdByTitle, 3);
        $firstPivotId = $this->attachTagToGroup($group, $firstByTitle, 3);

        $genres = $group->genres()->get();

        $this->assertSame([
            'Relationship Second',
            'Relationship Alpha',
            'Relationship Zebra',
        ], $genres->pluck('title')->all());

        $this->assertSame($secondPivotId, (int) $genres[0]->pivot->id);
        $this->assertSame(2, (int) $genres[0]->pivot->order);
        $this->assertSame($firstPivotId, (int) $genres[1]->pivot->id);
        $this->assertSame($thirdPivotId, (int) $genres[2]->pivot->id);
    }

    public function test_genre_groups_relationship_orders_by_group_order_then_pivot_order_then_title_and_exposes_pivot_data(): void
    {
        $genre = Genre::query()->create([
            'title' => 'Multi Group Tag',
            'description' => null,
            'order' => 1,
        ]);

        $secondGroup = GenreGroup::query()->create([
            'title' => 'Second Ordered Group',
            'description' => null,
            'order' => 2,
        ]);
        $thirdByTitle = GenreGroup::query()->create([
            'title' => 'Zebra Ordered Group',
            'description' => null,
            'order' => 3,
        ]);
        $firstByTitle = GenreGroup::query()->create([
            'title' => 'Alpha Ordered Group',
            'description' => null,
            'order' => 3,
        ]);

        $secondPivotId = $this->attachTagToGroup($secondGroup, $genre, 3);
        $thirdPivotId = $this->attachTagToGroup($thirdByTitle, $genre, 2);
        $firstPivotId = $this->attachTagToGroup($firstByTitle, $genre, 2);

        $groups = $genre->groups()->get();

        $this->assertSame([
            'Second Ordered Group',
            'Alpha Ordered Group',
            'Zebra Ordered Group',
        ], $groups->pluck('title')->all());

        $this->assertSame($secondPivotId, (int) $groups[0]->pivot->id);
        $this->assertSame(3, (int) $groups[0]->pivot->order);
        $this->assertSame($firstPivotId, (int) $groups[1]->pivot->id);
        $this->assertSame($thirdPivotId, (int) $groups[2]->pivot->id);
    }

    public function test_visibility_scopes_match_index_tag_visibility_rules(): void
    {
        $visibleGroup = GenreGroup::query()->create([
            'title' => 'Visible Scope Group',
            'description' => null,
            'order' => 1,
            'hidden_on_index' => false,
        ]);
        $hiddenGroup = GenreGroup::query()->create([
            'title' => 'Hidden Scope Group',
            'description' => null,
            'order' => 2,
            'hidden_on_index' => true,
        ]);

        $ungroupedVisible = Genre::query()->create([
            'title' => 'Scope Ungrouped Visible',
            'description' => null,
            'order' => 1,
            'hidden_on_index' => false,
        ]);
        $hiddenByTag = Genre::query()->create([
            'title' => 'Scope Hidden By Tag',
            'description' => null,
            'order' => 2,
            'hidden_on_index' => true,
        ]);
        $hiddenByGroup = Genre::query()->create([
            'title' => 'Scope Hidden By Group',
            'description' => null,
            'order' => 3,
            'hidden_on_index' => false,
        ]);
        $visibleByGroup = Genre::query()->create([
            'title' => 'Scope Visible By Group',
            'description' => null,
            'order' => 4,
            'hidden_on_index' => false,
        ]);
        $visibleThroughOneGroup = Genre::query()->create([
            'title' => 'Scope Visible Through One Group',
            'description' => null,
            'order' => 5,
            'hidden_on_index' => false,
        ]);

        $this->attachTagToGroup($hiddenGroup, $hiddenByGroup, 1);
        $this->attachTagToGroup($visibleGroup, $visibleByGroup, 1);
        $this->attachTagToGroup($hiddenGroup, $visibleThroughOneGroup, 2);
        $this->attachTagToGroup($visibleGroup, $visibleThroughOneGroup, 2);

        $this->assertSame([
            'Scope Ungrouped Visible',
            'Scope Visible By Group',
        ], Genre::query()->visibleOnIndex()->orderBy('title')->pluck('title')->all());

        $this->assertSame([
            'Scope Hidden By Group',
            'Scope Hidden By Tag',
            'Scope Visible Through One Group',
        ], Genre::query()->hiddenOnIndex()->orderBy('title')->pluck('title')->all());

        $this->assertSame([
            'Visible Scope Group',
        ], GenreGroup::query()->visibleOnIndex()->pluck('title')->all());

        $this->assertSame([
            'Hidden Scope Group',
        ], GenreGroup::query()->hiddenOnIndex()->pluck('title')->all());
    }

    public function test_tags_and_groups_can_persist_optional_hex_colors(): void
    {
        $genre = Genre::query()->create([
            'title' => 'Colored Relationship Tag',
            'description' => null,
            'order' => 1,
            'color' => '#ff3366',
            'text_color' => '#111111',
        ]);
        $group = GenreGroup::query()->create([
            'title' => 'Colored Relationship Group',
            'description' => null,
            'order' => 1,
            'color' => '#3366ff',
            'text_color' => '#eeeeee',
        ]);

        $this->assertSame('#ff3366', $genre->refresh()->color);
        $this->assertSame('#111111', $genre->refresh()->text_color);
        $this->assertSame('#3366ff', $group->refresh()->color);
        $this->assertSame('#eeeeee', $group->refresh()->text_color);

        $genre->forceFill(['color' => null, 'text_color' => null])->save();
        $group->forceFill(['color' => null, 'text_color' => null])->save();

        $this->assertNull($genre->refresh()->color);
        $this->assertNull($genre->refresh()->text_color);
        $this->assertNull($group->refresh()->color);
        $this->assertNull($group->refresh()->text_color);
    }

    private function attachTagToGroup(GenreGroup $group, Genre $genre, int $order): int
    {
        DB::table('genre_group_genre')->insert([
            'genre_group_id' => $group->getKey(),
            'genre_id' => $genre->getKey(),
            'order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('genre_group_genre')
            ->where('genre_group_id', $group->getKey())
            ->where('genre_id', $genre->getKey())
            ->value('id');
    }
}
