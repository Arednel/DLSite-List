<?php

namespace Tests\Unit\Support\Transfers;

use App\Enums\UiLanguage;
use App\Models\LibraryTransferRun;
use App\Support\Transfers\ImportValue;
use App\Support\Transfers\TransferMessage;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class TransferMessageTest extends TestCase
{
    protected function tearDown(): void
    {
        App::setLocale(UiLanguage::English->value);

        parent::tearDown();
    }

    public function test_known_transfer_stages_are_localized_without_translating_stage_data(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame('レビューを準備中', TransferMessage::stage('Preparing review'));
        $this->assertSame(
            'RJ123456 とその画像をエクスポート用に準備中',
            TransferMessage::stage('Preparing RJ123456 and its images for export'),
        );
        $this->assertSame(
            '作品 / カバー / RJ123456 を適用中',
            TransferMessage::stage('Applying works / cover / RJ123456'),
        );
        $this->assertSame('画像パート 2 を検証中', TransferMessage::stage('Validating images part 2'));
        $this->assertSame('データパート 2 を検証中', TransferMessage::stage('Validating data part 2'));
        $this->assertSame('設定 / Errors を準備中', TransferMessage::stage('Preparing options / Errors'));
        $this->assertSame('作品 / サイトID / RJ123456 を適用中', TransferMessage::stage('Applying works / site_id / RJ123456'));
        $this->assertSame('作品 / メーカーID / RJ123456 を適用中', TransferMessage::stage('Applying works / maker / RJ123456'));
        $this->assertSame('データ / test.zip を準備中', TransferMessage::stage('Preparing data / test.zip'));
        $this->assertSame('サイトID', ImportValue::label('site_id'));
        $this->assertSame(
            '作品 / 作品 / Apply All を適用中',
            TransferMessage::stage('Applying works / work / Apply All'),
        );
        $this->assertSame(
            'タグライブラリ / Junior / Senior を準備中',
            TransferMessage::stage('Preparing tag-library / Junior / Senior'),
        );
        $this->assertSame(
            '作品 / 作品 / Junior / Senior を適用中',
            TransferMessage::stage('Applying works / work / Junior / Senior'),
        );

        $stage = 'Validating images part 2';
        App::setLocale(UiLanguage::English->value);
        $this->assertSame('Validating images part 2', TransferMessage::stage($stage));
        $this->assertSame('Validating images part 2', $stage);
        $this->assertSame('Validating data part 2', TransferMessage::stage('Validating data part 2'));

        App::setLocale(UiLanguage::Japanese->value);
        $this->assertSame('Unexpected upstream detail', TransferMessage::stage('Unexpected upstream detail'));
    }

    public function test_import_entity_names_are_not_parsed_as_export_messages(): void
    {
        foreach (['ready for export', 'ready and its images for export', 'Junior / Senior for export'] as $name) {
            $stage = 'Preparing tag-library / ' . $name;

            App::setLocale(UiLanguage::Japanese->value);
            $this->assertSame('タグライブラリ / ' . $name . ' を準備中', TransferMessage::stage($stage));

            App::setLocale(UiLanguage::English->value);
            $this->assertSame('Preparing Tag Library / ' . $name, TransferMessage::stage($stage));
        }
    }

    public function test_upload_component_translates_client_errors_without_message_props(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->blade('<x-transfer-upload :initial="true" />')
            ->assertSee('data-file-too-large="__SIZE__ MiBのアップロード上限を超えています。"', false)
            ->assertSee('data-start-failed="インポートを開始できませんでした。"', false)
            ->assertSee('data-no-files="インポートを開始する前にZIPファイルを1つ以上選択してください。"', false)
            ->assertSee('data-interrupted="アップロードが中断されたか、通信が停止しました。このファイルを再試行してください。"', false)
            ->assertSee('data-failed="一部のファイルをアップロードできませんでした。"', false);

        $run = new LibraryTransferRun;
        $run->id = 1;

        $this->blade('<x-transfer-upload :run="$run" />', ['run' => $run])
            ->assertSee('data-no-files="ZIPファイルを1つ以上選択してください。"', false)
            ->assertSee('data-cancelled="転送をキャンセルしました。"', false);
    }

    public function test_known_transfer_diagnostics_are_localized_and_unknown_diagnostics_are_preserved(): void
    {
        App::setLocale(UiLanguage::Japanese->value);

        $this->assertSame(
            'RJ123456: エクスポートの準備前に作品がライブラリから削除されました。',
            TransferMessage::diagnostic('RJ123456: work was removed before export planning.'),
        );
        $this->assertSame(
            'RJ123456: カバー画像が不完全です。',
            TransferMessage::diagnostic('RJ123456: cover image is incomplete.'),
        );
        $this->assertSame(
            'RJ123456: サンプル画像 2 が不完全です。',
            TransferMessage::diagnostic('RJ123456: sample image 2 is incomplete.'),
        );
        $this->assertSame(
            'エントリのチェックサムが一致しません: images/RJ123456/sample_2.jpg',
            TransferMessage::diagnostic('Entry checksum mismatch: images/RJ123456/sample_2.jpg'),
        );
        $this->assertSame('不明または移行対象外の設定です: mystery_option', TransferMessage::diagnostic('Unknown or non-portable option: mystery_option'));

        $known = [
            'transfer.archive.tag_identity_mismatch' => 'タグの識別子がタイトルと一致しません。',
            'transfer.archive.invalid_manifest_types' => 'マニフェストの件数はJSON整数、インベントリはJSONリストである必要があります。',
            'transfer.archive.invalid_manifest' => 'サポートされていない、または無効なアーカイブマニフェストです。',
            'transfer.archive.fragment_record_count_limit' => 'JSONフラグメントが設定されたレコード数上限を超えています。',
            'transfer.import.invalid_group_records' => '無効なグループデータがあるため、グループを完全に置き換えられません。',
            'transfer.import.invalid_tags' => '無効なタグがあるため、親子関係を完全に置き換えられません。',
            'transfer.image_validation.worker_stopped' => '画像検証ワーカーが停止しました。',
            'transfer.worker_stopped' => '転送ワーカーが停止しました。',
            'transfer.custom_css.staged_changed' => '一時保存したカスタムCSSが見つからないか変更されています。',
            'transfer.custom_css.busy' => '別のカスタムCSS処理が進行中です。しばらくしてから再試行してください。',
            'transfer.custom_css.recovery.failed' => 'カスタムCSSの復元に失敗しました。バックアップは保持されています。',
            'transfer.custom_css.recovery.busy' => '別のカスタムCSS処理が進行中です。しばらくしてから復元を再試行してください。',
        ];

        foreach ($known as $error => $translation) {
            $this->assertSame($translation, TransferMessage::diagnostic($error));
        }

        $this->assertSame(
            'インポートまたはエクスポートの処理中は転送履歴をクリーンアップできません。',
            TransferMessage::diagnostic('transfer.cleanup.unavailable'),
        );
        $this->assertSame(
            '別の転送処理が進行中のため、転送履歴を一時的にクリーンアップできません。',
            TransferMessage::diagnostic('transfer.cleanup.busy'),
        );
        $this->assertSame(
            '一時ファイルの削除中に転送履歴のクリーンアップに失敗しました。',
            TransferMessage::diagnostic('transfer.cleanup.failed'),
        );

        $this->assertSame(
            'カスタムCSSの復元に失敗しました。バックアップは保持されています。 一時保存したカスタムCSSが見つからないか変更されています。',
            TransferMessage::diagnostic(
                'transfer.custom_css.recovery.failed_with_detail|transfer.custom_css.staged_changed',
            ),
        );
        $this->assertSame(
            'カスタムCSSの復元に失敗しました。バックアップは保持されています。 別のカスタムCSS処理が進行中です。しばらくしてから復元を再試行してください。',
            TransferMessage::diagnostic(
                'transfer.custom_css.recovery.failed_with_detail|transfer.custom_css.recovery.busy',
            ),
        );
        $this->assertSame(
            'カスタムCSSの復元に失敗しました。バックアップは保持されています。 Syntax error',
            TransferMessage::diagnostic(
                'transfer.custom_css.recovery.failed_with_detail|Syntax error',
            ),
        );
        $this->assertSame(
            'カスタムCSSの復元に失敗しました。バックアップは保持されています。 transfer',
            TransferMessage::diagnostic(
                'transfer.custom_css.recovery.failed_with_detail|transfer',
            ),
        );
        $this->assertSame(
            'カスタムCSSの復元に失敗しました。バックアップは保持されています。',
            TransferMessage::diagnostic('transfer.custom_css.recovery.failed_with_detail|'),
        );

        $this->assertSame('Unexpected upstream detail', TransferMessage::diagnostic('Unexpected upstream detail'));
        $this->assertSame('Options', TransferMessage::diagnostic('Options'));
        $this->assertSame('Errors', TransferMessage::diagnostic('Errors'));
    }
}
