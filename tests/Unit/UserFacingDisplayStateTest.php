<?php

namespace Tests\Unit;

use App\Enums\UiLanguage;
use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use App\Support\TagRefetch\TagRefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class UserFacingDisplayStateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        App::setLocale(UiLanguage::English->value);

        parent::tearDown();
    }

    public function test_refetch_status_labels_are_localized_without_changing_status_values(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        foreach (
            [
                TagRefetchRun::STATUS_RUNNING => '実行中',
                TagRefetchRun::STATUS_CANCELLING => 'キャンセル中',
                TagRefetchRun::STATUS_REVIEW => '確認',
                TagRefetchRun::STATUS_APPLIED => '適用済み',
            ] as $status => $label
        ) {
            $run = new TagRefetchRun(['status' => $status]);

            $this->assertSame($label, $run->statusLabel());
            $this->assertSame($status, $run->status);
        }

        foreach (
            [
                TagRefetchWorkResult::STATUS_PENDING => '待機中',
                TagRefetchWorkResult::STATUS_FETCHED => '取得済み',
                TagRefetchWorkResult::STATUS_SKIPPED => 'スキップ',
            ] as $status => $label
        ) {
            $result = new TagRefetchWorkResult(['status' => $status]);

            $this->assertSame($label, $result->statusLabel());
            $this->assertSame($status, $result->status);
        }
    }

    public function test_only_allowlisted_errors_are_localized_while_other_errors_pass_through(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(
            'Refetch was cancelled before this work was fetched.',
            TagRefetchService::CANCELLED_BEFORE_FETCH_MESSAGE,
        );
        foreach (
            [
                'Refetch was cancelled before this work was fetched.' => 'この作品のタグを取得する前に再取得がキャンセルされました。',
                'Product no longer exists.' => '作品が削除されたため見つかりません。',
                'Custom-only work is skipped.' => 'カスタム作品のためスキップしました。',
                'DLSite tag fetch failed.' => 'DLSiteタグの取得に失敗しました。',
                'DLSite tag fetch returned invalid JSON.' => 'DLSiteタグ取得の応答が不正なJSONでした。',
                'GeoBlocked DLSite work' => '地域制限によりアクセスできないDLSite作品',
                'Deleted or Non-existing DLSite work' => '削除済み、または存在しないDLSite作品',
                'Non-existing DLSite work' => '存在しないDLSite作品',
            ] as $message => $localized
        ) {
            $result = new TagRefetchWorkResult(['error' => $message]);

            $this->assertSame($localized, $result->displayError());
            $this->assertSame($message, $result->error);
        }

        foreach (['Pending', 'validation', 'Unexpected scraper detail'] as $message) {
            $result = new TagRefetchWorkResult(['error' => $message]);

            $this->assertSame($message, $result->displayError());
        }

        $emptyResult = new TagRefetchWorkResult(['error' => null]);

        $this->assertNull($emptyResult->displayError());
    }

    public function test_persisted_canonical_error_uses_the_current_locale_without_changing_storage(): void
    {
        $run = TagRefetchRun::query()->create([
            'status' => TagRefetchRun::STATUS_REVIEW,
            'selected_product_ids' => ['RJ000001'],
        ]);
        $result = $run->results()->create([
            'product_id' => 'RJ000001',
            'status' => TagRefetchWorkResult::STATUS_SKIPPED,
            'error' => 'Product no longer exists.',
        ]);

        App::setLocale(UiLanguage::English->value);
        $this->assertSame('Product no longer exists.', $result->fresh()->displayError());

        App::setLocale(UiLanguage::Japanese->value);
        $this->assertSame('作品が削除されたため見つかりません。', $result->fresh()->displayError());
        $this->assertDatabaseHas('tag_refetch_work_results', [
            'id' => $result->getKey(),
            'error' => 'Product no longer exists.',
        ]);
    }
}
