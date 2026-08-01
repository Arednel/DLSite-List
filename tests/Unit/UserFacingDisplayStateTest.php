<?php

namespace Tests\Unit;

use App\Enums\UiLanguage;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\Refetch\RefetchService;
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
                RefetchRun::STATUS_RUNNING => '実行中',
                RefetchRun::STATUS_CANCELLING => 'キャンセル中',
                RefetchRun::STATUS_REVIEW => '確認',
                RefetchRun::STATUS_APPLIED => '適用済み',
                RefetchRun::STATUS_REJECTED => '拒否済み',
            ] as $status => $label
        ) {
            $run = new RefetchRun(['status' => $status]);

            $this->assertSame($label, $run->statusLabel());
            $this->assertSame($status, $run->status);
        }

        foreach (
            [
                RefetchWorkResult::STATUS_PENDING => '待機中',
                RefetchWorkResult::STATUS_FETCHED => '取得済み',
                RefetchWorkResult::STATUS_FAILED => '失敗',
            ] as $status => $label
        ) {
            $result = new RefetchWorkResult(['status' => $status]);

            $this->assertSame($label, $result->statusLabel());
            $this->assertSame($status, $result->status);
        }
    }

    public function test_known_errors_are_localized_while_scraper_details_pass_through(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(
            'Refetch was cancelled before this work was fetched.',
            RefetchService::CANCELLED_BEFORE_FETCH_MESSAGE,
        );
        foreach (
            [
                'Refetch was cancelled before this work was fetched.' => 'この作品を取得する前に再取得がキャンセルされました。',
                'Product no longer exists.' => '作品が削除されたため見つかりません。',
                'DLSite work fetch failed.' => 'DLSite作品情報の取得に失敗しました。',
                'GeoBlocked DLSite work' => '地域制限によりアクセスできないDLSite作品',
                'Deleted or Non-existing DLSite work' => '削除済み、または存在しないDLSite作品',
                'Non-existing DLSite work' => '存在しないDLSite作品',
            ] as $message => $localized
        ) {
            $result = new RefetchWorkResult(['error' => $message]);

            $this->assertSame($localized, $result->displayError());
            $this->assertSame($message, $result->error);
        }

        foreach (['validation', 'Unexpected scraper detail'] as $message) {
            $result = new RefetchWorkResult(['error' => $message]);

            $this->assertSame($message, $result->displayError());
        }

        $emptyResult = new RefetchWorkResult(['error' => null]);

        $this->assertNull($emptyResult->displayError());
    }

    public function test_persisted_canonical_error_uses_the_current_locale_without_changing_storage(): void
    {
        $run = RefetchRun::query()->create([
            'status' => RefetchRun::STATUS_REVIEW,
        ]);
        $result = $run->results()->create([
            'product_id' => 'RJ000001',
            'status' => RefetchWorkResult::STATUS_FAILED,
            'error' => 'Product no longer exists.',
        ]);

        App::setLocale(UiLanguage::English->value);
        $this->assertSame('Product no longer exists.', $result->fresh()->displayError());

        App::setLocale(UiLanguage::Japanese->value);
        $this->assertSame('作品が削除されたため見つかりません。', $result->fresh()->displayError());
        $this->assertDatabaseHas('refetch_work_results', [
            'id' => $result->getKey(),
            'error' => 'Product no longer exists.',
        ]);
    }
}
