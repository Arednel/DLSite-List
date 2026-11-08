<?php

namespace Tests\Unit\Localization;

use App\Enums\UiLanguage;
use App\Support\Localization\ErrorMessage;
use App\Support\DLSite\DLSiteFetchResult;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ErrorLocalizationTest extends TestCase
{
    protected function tearDown(): void
    {
        App::setLocale(UiLanguage::English->value);

        parent::tearDown();
    }

    public function test_error_codes_use_laravel_translation_keys_directly(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame('転送ワーカーが停止しました。', __('errors.transfer.worker_stopped'));
        $this->assertSame(
            '一括インポートが待機中または実行中の間は、一括インポート履歴のクリーンアップを利用できません。',
            __('errors.bulk_import.cleanup.unavailable'),
        );
        $this->assertSame(
            '別の再取得操作の処理中は再取得のクリーンアップを利用できません。',
            __('errors.refetch.cleanup.busy'),
        );

        App::setLocale(UiLanguage::English->value);

        $this->assertSame('Transfer worker stopped.', __('errors.transfer.worker_stopped'));
    }

    public function test_laravel_handles_error_replacements(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(
            'エントリのチェックサムが一致しません: Works/RJ123456/Errors / sample.jpg',
            __('errors.transfer.archive.entry_checksum_mismatch', [
                'value' => 'Works/RJ123456/Errors / sample.jpg',
            ]),
        );
    }

    public function test_dlsite_image_warning_keeps_its_specialized_dynamic_format(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(
            'DLsite情報は取得できましたが、次の画像をダウンロードできませんでした: cover.jpg, sample_1.jpg',
            DLSiteFetchResult::displayImageFailureMessage(
                'DLsite data was fetched, but these images could not be downloaded: cover.jpg, sample_1.jpg',
            ),
        );
        $this->assertSame(
            'Unknown warning: <upstream detail>',
            DLSiteFetchResult::displayImageFailureMessage('Unknown warning: <upstream detail>'),
        );
    }

    public function test_error_display_localizes_known_codes_and_preserves_unknown_messages(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame('転送ワーカーが停止しました。', ErrorMessage::display('transfer.worker_stopped'));
        $this->assertSame('Legacy stored error.', ErrorMessage::display('Legacy stored error.'));
        $this->assertSame('RAW_DIAGNOSTIC_TOKEN', ErrorMessage::display('RAW_DIAGNOSTIC_TOKEN'));
        $this->assertSame('transfer', ErrorMessage::display('transfer'));
        $this->assertNull(ErrorMessage::display(null));
        $this->assertSame('', ErrorMessage::display(''));
    }
}
