<?php

namespace Tests\Unit\Support;

use App\Enums\UiLanguage;
use App\Models\RefetchWorkResult;
use App\Support\ProductImageMessage;
use App\Support\Transfers\TransferMessage;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ProductImageMessageTest extends TestCase
{
    protected function tearDown(): void
    {
        App::setLocale(UiLanguage::English->value);

        parent::tearDown();
    }

    public function test_image_promotion_error_codes_are_localized_at_transfer_and_refetch_boundaries(): void
    {
        $errors = [
            'image.promotion.stage_failed' => ['一時保存された画像を保存先へ反映できませんでした。', 'Failed to promote staged image.'],
            'image.promotion.read_staged_failed' => ['一時保存された画像を読み取れませんでした。', 'Unable to read staged image.'],
            'image.promotion.replace_failed' => ['現在の画像を置き換えられませんでした。', 'Failed to replace current image.'],
            'image.recovery.invalid_journal' => ['画像の復元記録が無効です。手動で復元できるよう、バックアップを保持してください。', 'Invalid image recovery journal. Retain backups for manual recovery.'],
        ];

        foreach ($errors as $error => [$japanese, $english]) {
            $result = new RefetchWorkResult(['error' => $error]);
            App::setLocale(UiLanguage::Japanese->value);
            $this->assertSame($japanese, TransferMessage::diagnostic($error));
            $this->assertSame($japanese, $result->displayError());
            $this->assertSame($error, $result->error);

            App::setLocale(UiLanguage::English->value);
            $this->assertSame($english, TransferMessage::diagnostic($error));
            $this->assertSame($english, $result->displayError());
        }
    }

    public function test_image_recovery_wrapper_localizes_known_details_and_preserves_unknown_details(): void
    {
        $prefix = 'image.recovery.failed_with_detail|';
        $details = [
            'image.recovery.finish_failed' => '画像の復元処理を完了できませんでした。',
            'Options' => 'Options',
            "Permission denied: Works/RJ123456/:detail.jpg\n<upstream detail>" => "Permission denied: Works/RJ123456/:detail.jpg\n<upstream detail>",
        ];

        foreach ($details as $detail => $expectedDetail) {
            $message = $prefix . $detail;
            $result = new RefetchWorkResult(['error' => $message]);
            $translation = '画像の復元に失敗しました。バックアップは保持されています。 ' . $expectedDetail;

            App::setLocale(UiLanguage::Japanese->value);
            $this->assertSame($translation, ProductImageMessage::display($message));
            $this->assertSame($translation, TransferMessage::diagnostic($message));
            $this->assertSame($translation, $result->displayError());
            $this->assertSame($message, $result->error);
        }
    }

    public function test_image_recovery_wrapper_without_detail_uses_generic_recovery_error(): void
    {
        App::setLocale(UiLanguage::English->value);

        $this->assertSame(
            'Image recovery failed; backups were retained.',
            ProductImageMessage::display('image.recovery.failed_with_detail|'),
        );
    }

    public function test_empty_messages_are_preserved_and_unknown_messages_use_laravel_missing_keys(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertNull(ProductImageMessage::display(null));
        $this->assertSame('', ProductImageMessage::display(''));
        $this->assertSame('errors.Options', ProductImageMessage::display('Options'));
    }
}
