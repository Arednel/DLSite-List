<?php

namespace Tests\Unit\Support\Localization;

use App\Enums\UiLanguage;
use App\Support\BulkImport\BulkImportCleanupService;
use App\Support\DLSite\DLSiteFetchResult;
use App\Support\Localization\ErrorMessage;
use App\Support\Refetch\RefetchCleanupService;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ErrorMessageTest extends TestCase
{
    protected function tearDown(): void
    {
        App::setLocale(UiLanguage::English->value);

        parent::tearDown();
    }

    public function test_known_errors_use_the_catalog_and_unknown_errors_remain_verbatim(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame('転送ワーカーが停止しました。', ErrorMessage::display('Transfer worker stopped.'));
        $this->assertSame('Options', ErrorMessage::display('Options'));
        $this->assertSame('Connection reset by peer', ErrorMessage::display('Connection reset by peer'));
        $this->assertSame('Imported value is invalid.', ErrorMessage::display('Imported value is invalid.'));
        $this->assertSame('errors.Options', ErrorMessage::display('errors.Options'));
        $this->assertNull(ErrorMessage::display(null));
        $this->assertSame('', ErrorMessage::display(''));
        $recoveryMessage = 'Image recovery failed; backups were retained. Unable to finish image recovery.';
        $this->assertSame($recoveryMessage, ErrorMessage::display($recoveryMessage));

        App::setLocale(UiLanguage::English->value);

        $this->assertSame('Transfer worker stopped.', ErrorMessage::display('Transfer worker stopped.'));
    }

    public function test_known_cleanup_errors_use_the_error_catalog(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(
            '一括インポートが待機中または実行中の間は、一括インポート履歴のクリーンアップを利用できません。',
            ErrorMessage::display(BulkImportCleanupService::UNAVAILABLE_MESSAGE),
        );
        $this->assertSame(
            '別の再取得操作の処理中は再取得のクリーンアップを利用できません。',
            ErrorMessage::display(RefetchCleanupService::BUSY_MESSAGE),
        );
    }

    public function test_known_warnings_and_validation_messages_use_the_error_catalog(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(
            'DLSite情報は取得できましたが、次の画像をダウンロードできませんでした: cover.jpg, sample_1.jpg',
            DLSiteFetchResult::displayImageFailureMessage(
                'DLSite data was fetched, but these images could not be downloaded: cover.jpg, sample_1.jpg',
            ),
        );
        $this->assertSame(
            'アーカイブ内の画像が不完全です。利用可能な画像のみインポートできます。',
            ErrorMessage::display('Archive images are incomplete. You can import only available images.'),
        );
        $this->assertSame(
            '作品形式の値が無効です。',
            ErrorMessage::display('The Product Format value is invalid.'),
        );
        $this->assertSame(
            'Legacy warning: <upstream detail>',
            DLSiteFetchResult::displayImageFailureMessage('Legacy warning: <upstream detail>'),
        );
    }

    public function test_dynamic_error_templates_preserve_parameter_values(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(
            'エントリのチェックサムが一致しません: Works/RJ123456/Errors / sample.jpg',
            ErrorMessage::format('Entry checksum mismatch: :value', [
                'value' => 'Works/RJ123456/Errors / sample.jpg',
            ]),
        );
        $this->assertSame(
            'Uncatalogued error: Errors / sample.jpg',
            ErrorMessage::format('Uncatalogued error: :value', [
                'value' => 'Errors / sample.jpg',
            ]),
        );
    }
}
