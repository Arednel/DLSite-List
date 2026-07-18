<?php

namespace Tests\Unit\Support;

use App\Enums\ProductField;
use App\Enums\UiLanguage;
use App\Support\ProductFieldLayout;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ProductFieldLayoutTest extends TestCase
{
    public function test_fetched_tag_field_uses_a_generic_key_and_the_current_ui_language_label(): void
    {
        $this->assertSame('fetched_tags', ProductField::FetchedTags->value);

        App::setLocale(UiLanguage::English->value);

        $this->assertSame('Fetched EN Tags', ProductField::FetchedTags->label());

        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame('取得済みJPタグ', ProductField::FetchedTags->label());
    }

    public function test_product_field_exposes_surface_field_order(): void
    {
        $expectedOrders = [
            ProductFieldLayout::SURFACE_INDEX => [
                ProductField::Image,
                ProductField::Title,
                ProductField::Score,
                ProductField::Series,
                ProductField::AgeCategory,
                ProductField::Progress,
                ProductField::Circle,
                ProductField::Scenario,
                ProductField::Illustration,
                ProductField::VoiceActor,
                ProductField::Author,
                ProductField::DescriptionJapanese,
                ProductField::DescriptionEnglish,
                ProductField::Tags,
                ProductField::Notes,
                ProductField::StartDate,
                ProductField::FinishDate,
                ProductField::TotalTimesReListened,
                ProductField::ReListenValue,
                ProductField::Priority,
            ],
            ProductFieldLayout::SURFACE_EDIT => [
                ProductField::Progress,
                ProductField::Score,
                ProductField::Series,
                ProductField::Title,
                ProductField::FetchedTags,
                ProductField::Tags,
                ProductField::Notes,
                ProductField::StartDate,
                ProductField::FinishDate,
                ProductField::TotalTimesReListened,
                ProductField::ReListenValue,
                ProductField::Priority,
                ProductField::AgeCategory,
                ProductField::Circle,
                ProductField::Scenario,
                ProductField::Illustration,
                ProductField::VoiceActor,
                ProductField::Author,
                ProductField::DescriptionJapanese,
                ProductField::DescriptionEnglish,
            ],
            ProductFieldLayout::SURFACE_FILTER => [
                ProductField::Title,
                ProductField::Score,
                ProductField::Series,
                ProductField::AgeCategory,
                ProductField::Progress,
                ProductField::Notes,
                ProductField::Priority,
                ProductField::TotalTimesReListened,
                ProductField::ReListenValue,
                ProductField::Tags,
                ProductField::StartDate,
                ProductField::FinishDate,
                ProductField::CreatedAt,
                ProductField::UpdatedAt,
                ProductField::Circle,
                ProductField::Scenario,
                ProductField::Illustration,
                ProductField::VoiceActor,
                ProductField::Author,
                ProductField::DescriptionJapanese,
                ProductField::DescriptionEnglish,
            ],
            ProductFieldLayout::SURFACE_QUICK_ADD => [
                ProductField::RjCode,
                ProductField::Progress,
                ProductField::Score,
                ProductField::Series,
                ProductField::Title,
                ProductField::Tags,
                ProductField::Notes,
                ProductField::StartDate,
                ProductField::FinishDate,
                ProductField::TotalTimesReListened,
                ProductField::ReListenValue,
                ProductField::Priority,
                ProductField::AgeCategory,
                ProductField::Circle,
                ProductField::Scenario,
                ProductField::Illustration,
                ProductField::VoiceActor,
                ProductField::Author,
                ProductField::DescriptionJapanese,
                ProductField::DescriptionEnglish,
            ],
            ProductFieldLayout::SURFACE_CUSTOM_QUICK_ADD => [
                ProductField::RjCode,
                ProductField::Progress,
                ProductField::Score,
                ProductField::Series,
                ProductField::Title,
                ProductField::Tags,
                ProductField::Notes,
                ProductField::AgeCategory,
                ProductField::Image,
                ProductField::SampleImages,
                ProductField::StartDate,
                ProductField::FinishDate,
                ProductField::TotalTimesReListened,
                ProductField::ReListenValue,
                ProductField::Priority,
                ProductField::Circle,
                ProductField::Scenario,
                ProductField::Illustration,
                ProductField::VoiceActor,
                ProductField::Author,
                ProductField::DescriptionJapanese,
                ProductField::DescriptionEnglish,
            ],
        ];

        foreach ($expectedOrders as $surface => $fields) {
            $this->assertSame($fields, ProductField::forSurface($surface));
        }
    }

    public function test_product_field_exposes_surface_availability_and_defaults(): void
    {
        $this->assertFalse(ProductField::SampleImages->isAvailableOn(ProductFieldLayout::SURFACE_QUICK_ADD));
        $this->assertTrue(ProductField::SampleImages->isAvailableOn(ProductFieldLayout::SURFACE_CUSTOM_QUICK_ADD));

        $this->assertTrue(ProductField::Title->isVisibilityLocked(ProductFieldLayout::SURFACE_EDIT));
        $this->assertTrue(ProductField::Title->isEditableByDefault(ProductFieldLayout::SURFACE_EDIT));
        $this->assertTrue(ProductField::RjCode->isVisibilityLocked(ProductFieldLayout::SURFACE_QUICK_ADD));
        $this->assertTrue(ProductField::Image->isVisibilityLocked(ProductFieldLayout::SURFACE_CUSTOM_QUICK_ADD));

        $this->assertTrue(ProductField::AgeCategory->isHiddenByDefault(ProductFieldLayout::SURFACE_EDIT));
        $this->assertTrue(ProductField::AgeCategory->isHiddenByDefault(ProductFieldLayout::SURFACE_QUICK_ADD));
        $this->assertFalse(ProductField::AgeCategory->isHiddenByDefault(ProductFieldLayout::SURFACE_CUSTOM_QUICK_ADD));
        $this->assertTrue(ProductField::DescriptionJapanese->isHiddenByDefault(ProductFieldLayout::SURFACE_FILTER));
        $this->assertTrue(ProductField::DescriptionEnglish->isHiddenByDefault(ProductFieldLayout::SURFACE_FILTER));
        $this->assertTrue(ProductField::StartDate->isHiddenByDefault(ProductFieldLayout::SURFACE_FILTER));
        $this->assertTrue(ProductField::Notes->isHiddenByDefault(ProductFieldLayout::SURFACE_INDEX));
    }

    public function test_default_layout_matches_field_order_and_visibility(): void
    {
        $layout = ProductFieldLayout::normalize(null, ProductFieldLayout::SURFACE_INDEX);

        $this->assertSame([
            ProductField::Image->value,
            ProductField::Title->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::AgeCategory->value,
            ProductField::Progress->value,
            ProductField::Circle->value,
            ProductField::Scenario->value,
            ProductField::Illustration->value,
            ProductField::VoiceActor->value,
            ProductField::Author->value,
            ProductField::DescriptionJapanese->value,
            ProductField::DescriptionEnglish->value,
            ProductField::Tags->value,
            ProductField::Notes->value,
            ProductField::StartDate->value,
            ProductField::FinishDate->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Priority->value,
        ], collect($layout)->pluck('field')->all());

        $this->assertSame([
            ProductField::Image->value,
            ProductField::Title->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::AgeCategory->value,
            ProductField::Progress->value,
            ProductField::Tags->value,
        ], ProductFieldLayout::visibleFields($layout));

        $this->assertTrue($layout[1]['visibility_locked']);
        $this->assertSame(
            'Notes are already shown inside Title; enable this for a separate column.',
            collect($layout)->firstWhere('field', ProductField::Notes->value)['note'],
        );
    }

    public function test_surface_defaults_include_requested_edit_and_filter_rows(): void
    {
        $editLayout = ProductFieldLayout::normalize(null, ProductFieldLayout::SURFACE_EDIT);

        $this->assertSame([
            ProductField::Progress->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::Title->value,
            ProductField::FetchedTags->value,
            ProductField::Tags->value,
            ProductField::Notes->value,
            ProductField::StartDate->value,
            ProductField::FinishDate->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Priority->value,
            ProductField::AgeCategory->value,
            ProductField::Circle->value,
            ProductField::Scenario->value,
            ProductField::Illustration->value,
            ProductField::VoiceActor->value,
            ProductField::Author->value,
            ProductField::DescriptionJapanese->value,
            ProductField::DescriptionEnglish->value,
        ], collect($editLayout)->pluck('field')->all());

        $this->assertSame([
            ProductField::Progress->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::Title->value,
            ProductField::FetchedTags->value,
            ProductField::Tags->value,
            ProductField::Notes->value,
            ProductField::StartDate->value,
            ProductField::FinishDate->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Priority->value,
        ], ProductFieldLayout::visibleFields($editLayout));

        $titleRow = collect($editLayout)->firstWhere('field', ProductField::Title->value);
        $ageRow = collect($editLayout)->firstWhere('field', ProductField::AgeCategory->value);

        $this->assertTrue($titleRow['visible']);
        $this->assertTrue($titleRow['visibility_locked']);
        $this->assertTrue($titleRow['editable']);
        $this->assertFalse($ageRow['visible']);
        $this->assertFalse($ageRow['editable']);

        $this->assertSame([
            ProductField::Title->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::AgeCategory->value,
            ProductField::Progress->value,
            ProductField::Notes->value,
            ProductField::Priority->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Tags->value,
            ProductField::StartDate->value,
            ProductField::FinishDate->value,
            ProductField::CreatedAt->value,
            ProductField::UpdatedAt->value,
            ProductField::Circle->value,
            ProductField::Scenario->value,
            ProductField::Illustration->value,
            ProductField::VoiceActor->value,
            ProductField::Author->value,
            ProductField::DescriptionJapanese->value,
            ProductField::DescriptionEnglish->value,
        ], collect(ProductFieldLayout::normalize(null, ProductFieldLayout::SURFACE_FILTER))->pluck('field')->all());

        $this->assertSame([
            ProductField::Title->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::AgeCategory->value,
            ProductField::Progress->value,
            ProductField::Notes->value,
            ProductField::Priority->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Tags->value,
        ], ProductFieldLayout::visibleFields(ProductFieldLayout::normalize(null, ProductFieldLayout::SURFACE_FILTER)));
    }

    public function test_edit_tags_default_to_custom_editing_with_fetched_editing_disabled(): void
    {
        $layout = ProductFieldLayout::normalize(null, ProductFieldLayout::SURFACE_EDIT);
        $tagsRow = collect($layout)->firstWhere('field', ProductField::Tags->value);
        $fetchedTagsRow = collect($layout)->firstWhere('field', ProductField::FetchedTags->value);

        $this->assertTrue($tagsRow['visible']);
        $this->assertTrue($tagsRow['editable']);
        $this->assertTrue($fetchedTagsRow['visible']);
        $this->assertFalse($fetchedTagsRow['editable']);
        $this->assertFalse(ProductFieldLayout::fetchedTagsEditable($layout));
    }

    public function test_quick_add_defaults_match_create_form_order_and_visibility(): void
    {
        $layout = ProductFieldLayout::normalize(null, ProductFieldLayout::SURFACE_QUICK_ADD);

        $this->assertSame([
            ProductField::RjCode->value,
            ProductField::Progress->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::Title->value,
            ProductField::Tags->value,
            ProductField::Notes->value,
            ProductField::StartDate->value,
            ProductField::FinishDate->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Priority->value,
            ProductField::AgeCategory->value,
            ProductField::Circle->value,
            ProductField::Scenario->value,
            ProductField::Illustration->value,
            ProductField::VoiceActor->value,
            ProductField::Author->value,
            ProductField::DescriptionJapanese->value,
            ProductField::DescriptionEnglish->value,
        ], collect($layout)->pluck('field')->all());

        $this->assertSame([
            ProductField::RjCode->value,
            ProductField::Progress->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::Title->value,
            ProductField::Tags->value,
            ProductField::Notes->value,
            ProductField::StartDate->value,
            ProductField::FinishDate->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Priority->value,
        ], ProductFieldLayout::visibleFields($layout));
        $this->assertTrue($layout[0]['visibility_locked']);
    }

    public function test_custom_quick_add_defaults_match_create_form_order_and_visibility(): void
    {
        $layout = ProductFieldLayout::normalize(null, ProductFieldLayout::SURFACE_CUSTOM_QUICK_ADD);

        $this->assertSame([
            ProductField::RjCode->value,
            ProductField::Progress->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::Title->value,
            ProductField::Tags->value,
            ProductField::Notes->value,
            ProductField::AgeCategory->value,
            ProductField::Image->value,
            ProductField::SampleImages->value,
            ProductField::StartDate->value,
            ProductField::FinishDate->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Priority->value,
            ProductField::Circle->value,
            ProductField::Scenario->value,
            ProductField::Illustration->value,
            ProductField::VoiceActor->value,
            ProductField::Author->value,
            ProductField::DescriptionJapanese->value,
            ProductField::DescriptionEnglish->value,
        ], collect($layout)->pluck('field')->all());

        $this->assertSame([
            ProductField::RjCode->value,
            ProductField::Progress->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::Title->value,
            ProductField::Tags->value,
            ProductField::Notes->value,
            ProductField::AgeCategory->value,
            ProductField::Image->value,
            ProductField::SampleImages->value,
            ProductField::StartDate->value,
            ProductField::FinishDate->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Priority->value,
        ], ProductFieldLayout::visibleFields($layout));

        foreach ([ProductField::RjCode, ProductField::Title, ProductField::AgeCategory, ProductField::Image] as $field) {
            $row = collect($layout)->firstWhere('field', $field->value);

            $this->assertTrue($row['visible']);
            $this->assertTrue($row['visibility_locked']);
        }
    }

    public function test_quick_add_layouts_normalize_invalid_duplicate_hidden_and_locked_rows(): void
    {
        $quickAddLayout = ProductFieldLayout::normalize([
            ['field' => ProductField::RjCode->value, 'visible' => false],
            ['field' => ProductField::Notes->value, 'visible' => false],
            ['field' => ProductField::Notes->value, 'visible' => true],
            ['field' => 'not_real', 'visible' => true],
        ], ProductFieldLayout::SURFACE_QUICK_ADD);

        $this->assertSame(ProductField::RjCode->value, $quickAddLayout[0]['field']);
        $this->assertTrue($quickAddLayout[0]['visible']);
        $this->assertTrue($quickAddLayout[0]['visibility_locked']);
        $this->assertFalse(collect($quickAddLayout)->firstWhere('field', ProductField::Notes->value)['visible']);
        $this->assertNotContains('not_real', collect($quickAddLayout)->pluck('field')->all());
        $this->assertNotContains(
            ProductField::Notes->value,
            collect(ProductFieldLayout::quickAddFields($quickAddLayout))->pluck('field')->all(),
        );

        $customLayout = ProductFieldLayout::normalize([
            ['field' => ProductField::RjCode->value, 'visible' => false],
            ['field' => ProductField::Title->value, 'visible' => false],
            ['field' => ProductField::AgeCategory->value, 'visible' => false],
            ['field' => ProductField::Image->value, 'visible' => false],
            ['field' => ProductField::SampleImages->value, 'visible' => false],
        ], ProductFieldLayout::SURFACE_CUSTOM_QUICK_ADD);

        foreach ([ProductField::RjCode, ProductField::Title, ProductField::AgeCategory, ProductField::Image] as $field) {
            $this->assertTrue(collect($customLayout)->firstWhere('field', $field->value)['visible']);
        }

        $this->assertFalse(collect($customLayout)->firstWhere('field', ProductField::SampleImages->value)['visible']);
    }

    public function test_it_normalizes_invalid_duplicate_and_missing_rows(): void
    {
        $layout = ProductFieldLayout::normalize([
            ['field' => 'voice_actor', 'visible' => false],
            ['field' => 'voice_actor', 'visible' => true],
            ['field' => 'not_real', 'visible' => true],
            ['field' => 'description', 'visible' => true],
            ['field' => ProductField::DescriptionJapanese->value, 'visible' => true],
            ['field' => ProductField::DescriptionEnglish->value, 'visible' => true, 'editable' => true],
        ], ProductFieldLayout::SURFACE_EDIT);

        $this->assertSame('voice_actor', $layout[0]['field']);
        $this->assertFalse($layout[0]['visible']);
        $this->assertFalse($layout[0]['editable']);
        $this->assertSame(ProductField::DescriptionJapanese->value, $layout[1]['field']);
        $this->assertTrue($layout[1]['visible']);
        $this->assertFalse($layout[1]['editable']);
        $this->assertSame(ProductField::DescriptionEnglish->value, $layout[2]['field']);
        $this->assertTrue($layout[2]['visible']);
        $this->assertTrue($layout[2]['editable']);
        $this->assertNotContains('description', collect($layout)->pluck('field')->all());
        $this->assertContains(ProductField::Score->value, collect($layout)->pluck('field')->all());
    }

    public function test_index_columns_prepare_visible_rendering_metadata(): void
    {
        $columns = ProductFieldLayout::indexColumns([
            ['field' => ProductField::DescriptionJapanese->value, 'label' => 'Japanese Description', 'visible' => false],
            ['field' => ProductField::DescriptionEnglish->value, 'label' => 'English Description', 'visible' => true],
            ['field' => ProductField::Circle->value, 'label' => 'Circle', 'visible' => true],
            ['field' => ProductField::Score->value, 'label' => 'Score', 'visible' => true],
            ['field' => ProductField::StartDate->value, 'label' => 'Start Date', 'visible' => true],
            ['field' => 'not_real', 'label' => 'Broken', 'visible' => true],
        ]);

        $this->assertSame([
            [
                'field' => ProductField::DescriptionEnglish->value,
                'label' => 'English Description',
                'class' => 'description-english',
                'sort_field' => null,
                'contributor_role' => null,
            ],
            [
                'field' => ProductField::Circle->value,
                'label' => 'Circle',
                'class' => 'circle',
                'sort_field' => 'circle',
                'contributor_role' => 'circle',
            ],
            [
                'field' => ProductField::Score->value,
                'label' => 'Score',
                'class' => 'score',
                'sort_field' => 'score',
                'contributor_role' => null,
            ],
            [
                'field' => ProductField::StartDate->value,
                'label' => 'Start Date',
                'class' => 'start-date',
                'sort_field' => 'start_date',
                'contributor_role' => null,
            ],
        ], $columns);
    }

    public function test_edit_fields_prepare_visible_rendering_metadata(): void
    {
        $fields = ProductFieldLayout::editFields([
            ['field' => ProductField::DescriptionJapanese->value, 'label' => 'Japanese Description', 'visible' => false, 'editable' => true],
            ['field' => ProductField::DescriptionEnglish->value, 'label' => 'English Description', 'visible' => true, 'editable' => true],
            ['field' => ProductField::VoiceActor->value, 'label' => 'Voice Actor', 'visible' => true, 'editable' => true],
            ['field' => ProductField::Tags->value, 'label' => 'Tags', 'visible' => true, 'editable' => false, 'fetched_editable' => true],
            ['field' => 'not_real', 'label' => 'Broken', 'visible' => true, 'editable' => true],
        ]);

        $this->assertSame([
            [
                'field' => ProductField::DescriptionEnglish->value,
                'label' => 'English Description',
                'editable' => true,
                'contributor_role' => null,
            ],
            [
                'field' => ProductField::VoiceActor->value,
                'label' => 'Voice Actor',
                'editable' => true,
                'contributor_role' => 'voice_actor',
            ],
            [
                'field' => ProductField::Tags->value,
                'label' => 'Custom Tags',
                'editable' => false,
                'contributor_role' => null,
            ],
        ], $fields);
    }

    public function test_filter_fields_prepare_visible_rendering_metadata(): void
    {
        $fields = ProductFieldLayout::filterFields([
            ['field' => ProductField::DescriptionJapanese->value, 'label' => 'Japanese Description', 'visible' => false],
            ['field' => ProductField::DescriptionEnglish->value, 'label' => 'English Description', 'visible' => true],
            ['field' => ProductField::VoiceActor->value, 'label' => 'Voice Actor', 'visible' => true],
            ['field' => ProductField::AgeCategory->value, 'label' => 'Age', 'visible' => true],
            ['field' => 'not_real', 'label' => 'Broken', 'visible' => true],
        ]);

        $this->assertSame([
            [
                'field' => ProductField::DescriptionEnglish->value,
                'label' => 'English Description',
                'class' => 'description-english',
            ],
            [
                'field' => ProductField::VoiceActor->value,
                'label' => 'Voice Actor',
                'class' => 'voice-actor',
            ],
            [
                'field' => ProductField::AgeCategory->value,
                'label' => 'Age',
                'class' => 'age-category',
            ],
        ], $fields);
    }
}
