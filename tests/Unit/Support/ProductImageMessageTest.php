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

    public function test_image_promotion_errors_are_localized_at_transfer_and_refetch_boundaries(): void
    {
        $messages = [
            'Failed to promote staged image.' => '一時保存された画像を保存先へ反映できませんでした。',
            'Unable to read staged image.' => '一時保存された画像を読み取れませんでした。',
            'Failed to replace current image.' => '現在の画像を置き換えられませんでした。',
            'Invalid image recovery journal. Retain backups for manual recovery.' => '画像の復元記録が無効です。手動で復元できるよう、バックアップを保持してください。',
        ];

        foreach ($messages as $message => $translation) {
            $result = new RefetchWorkResult(['error' => $message]);
            App::setLocale(UiLanguage::Japanese->value);
            $this->assertSame($translation, TransferMessage::diagnostic($message));
            $this->assertSame($translation, $result->displayError());
            $this->assertSame($message, $result->error);

            App::setLocale(UiLanguage::English->value);
            $this->assertSame($message, TransferMessage::diagnostic($message));
            $this->assertSame($message, $result->displayError());
        }
    }

    public function test_image_recovery_wrapper_localizes_known_causes_and_preserves_unknown_causes(): void
    {
        $prefix = 'Image recovery failed; backups were retained. ';
        $details = [
            'Unable to finish image recovery.' => '画像の復元処理を完了できませんでした。',
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

            App::setLocale(UiLanguage::English->value);
            $this->assertSame($message, ProductImageMessage::display($message));
        }
    }

    public function test_empty_messages_and_unknown_messages_are_preserved(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertNull(ProductImageMessage::display(null));
        $this->assertSame('', ProductImageMessage::display(''));
        $this->assertSame('Options', ProductImageMessage::display('Options'));
    }
}
