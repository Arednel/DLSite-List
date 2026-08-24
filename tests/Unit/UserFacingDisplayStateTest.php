<?php

namespace Tests\Unit;

use App\Enums\UiLanguage;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\Refetch\RefetchService;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class UserFacingDisplayStateTest extends TestCase
{
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
                'This work was removed from your library before it could be refetched.' => 'この作品は再取得される前にライブラリから削除されました。',
                'DLSite fetch failed.' => 'DLSite情報の取得に失敗しました。',
                'GeoBlocked DLSite work' => '地域制限によりアクセスできないDLSite作品',
                'This work was deleted or could not be found on DLSite' => 'この作品は削除されたか、DLSiteで見つかりませんでした。',
                'This work could not be found on DLSite' => 'この作品はDLSiteで見つかりませんでした。',
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
}
