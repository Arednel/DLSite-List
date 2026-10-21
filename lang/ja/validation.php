<?php

// Laravel validation messages are based on the Japanese translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/ja).
// Project-specific attribute translations were generated using machine translation and reviewed by an LLM for consistency with existing project and DLsite terminology.

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attributeを承認してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承認する必要があります。',
    'active_url' => ':attributeは、有効なURLではありません。',
    'after' => ':attributeには、:dateより後の日付を指定してください。',
    'after_or_equal' => ':attributeには、:date以降の日付を指定してください。',
    'alpha' => ':attributeには、アルファベットのみ使用できます。',
    'alpha_dash' => ":attributeには、英数字('A-Z','a-z','0-9')とハイフンと下線('-','_')が使用できます。",
    'alpha_num' => ":attributeには、英数字('A-Z','a-z','0-9')が使用できます。",
    'any_of' => ':attributeは、有効ではありません。',
    'array' => ':attributeには、配列を指定してください。',
    'ascii' => ':attributeには、英数字と記号のみ使用可能です。',
    'before' => ':attributeには、:dateより前の日付を指定してください。',
    'before_or_equal' => ':attributeには、:date以前の日付を指定してください。',
    'between' => [
        'array' => ':attributeの項目は、:min個から:max個にしてください。',
        'file' => ':attributeには、:min KBから:max KBまでのサイズのファイルを指定してください。',
        'numeric' => ':attributeには、:minから、:maxまでの数字を指定してください。',
        'string' => ':attributeは、:min文字から:max文字にしてください。',
    ],
    'boolean' => ":attributeには、'true'か'false'を指定してください。",
    'can' => ':attributeに権限のない値が含まれています。',
    'confirmed' => ':attributeと:attribute確認が一致しません。',
    'contains' => ':attributeに必須項目が含まれていません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeは、正しい日付ではありません。',
    'date_equals' => ':attributeは:dateと同じ日付を入力してください。',
    'date_format' => ":attributeの形式が':format'と一致しません。",
    'decimal' => ':attributeは、小数点以下が:decimalである必要があります。',
    'declined' => ':attributeを拒否する必要があります。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否する必要があります。',
    'different' => ':attributeと:otherには、異なるものを指定してください。',
    'digits' => ':attributeは、:digits桁にしてください。',
    'digits_between' => ':attributeは、:min桁から:max桁にしてください。',
    'dimensions' => ':attributeの画像サイズが無効です',
    'distinct' => ':attributeの値が重複しています。',
    'doesnt_contain' => ':attributeには、次のいずれも含めてはなりません: :values。',
    'doesnt_end_with' => ':attributeの終わりは「:values」以外である必要があります。',
    'doesnt_start_with' => ':attributeの始まりは「:values」以外である必要があります。',
    'email' => ':attributeは、有効なメールアドレス形式で指定してください。',
    'encoding' => ':attributeは、:encodingでエンコードされている必要があります。',
    'ends_with' => ':attributeの終わりは「:values」である必要があります。',
    'enum' => '選択した :attributeは 無効です。',
    'exists' => '選択された:attributeは、有効ではありません。',
    'extensions' => ':attribute には、次のいずれかの拡張子が必要です: :values',
    'file' => ':attributeには、ファイル形式を指定してください。',
    'filled' => ':attributeは必須です。',
    'gt' => [
        'array' => ':attributeの項目数は、:value個より多い必要があります。',
        'file' => ':attributeは、:value KBより大きい必要があります。',
        'numeric' => ':attributeは、:valueより大きい必要があります。',
        'string' => ':attributeは、:value文字を超える必要があります。',
    ],
    'gte' => [
        'array' => ':attributeの項目数は、:value個以上である必要があります。',
        'file' => ':attributeは、:value KB以上である必要があります。',
        'numeric' => ':attributeは、:value以上である必要があります。',
        'string' => ':attributeは、:value文字以上である必要があります。',
    ],
    'hex_color' => ':attributeは、有効な16進数カラーコードを指定してください。',
    'image' => ':attributeには、画像を指定してください。',
    'in' => '選択された:attributeは、有効ではありません。',
    'in_array' => ':attributeが:otherに存在しません。',
    'in_array_keys' => ':attributeには、次のいずれかのキーを少なくとも1つ含める必要があります: :values。',
    'integer' => ':attributeには、整数を指定してください。',
    'ip' => ':attributeには、有効なIPアドレスを指定してください。',
    'ipv4' => ':attributeはIPv4アドレスを指定してください。',
    'ipv6' => ':attributeはIPv6アドレスを指定してください。',
    'json' => ':attributeには、有効なJSON文字列を指定してください。',
    'list' => ':attribute フィールドはリストでなければなりません。',
    'lowercase' => ':attributeは、小文字で入力してください。',
    'lt' => [
        'array' => ':attributeの項目数は、:value個より少ない必要があります。',
        'file' => ':attributeは、:value KBより小さい必要があります。',
        'numeric' => ':attributeは、:valueより小さい必要があります。',
        'string' => ':attributeは、:value文字より小さい必要があります。',
    ],
    'lte' => [
        'array' => ':attributeの項目数は、:value個以下である必要があります。',
        'file' => ':attributeは、:value KB以下である必要があります。',
        'numeric' => ':attributeは、:value以下である必要があります。',
        'string' => ':attributeは、:value文字以下である必要があります。',
    ],
    'mac_address' => ':attributeは有効なMACアドレスである必要があります。',
    'max' => [
        'array' => ':attributeの項目数は、:max個以下である必要があります。',
        'file' => ':attributeは、:max KB以下のファイルである必要があります。',
        'numeric' => ':attributeは、:max以下の数値である必要があります。',
        'string' => ':attributeの文字数は、:max文字以下である必要があります。',
    ],
    'max_digits' => ':attributeは、:max桁以下の数字である必要があります。',
    'mimes' => ':attributeには、以下のファイルタイプを指定してください。:values',
    'mimetypes' => ':attributeには、以下のファイルタイプを指定してください。:values',
    'min' => [
        'array' => ':attributeの項目数は、:min個以上にしてください。',
        'file' => ':attributeには、:min KB以上のファイルを指定してください。',
        'numeric' => ':attributeには、:min以上の数値を指定してください。',
        'string' => ':attributeの文字数は、:min文字以上である必要があります。',
    ],
    'min_digits' => ':attributeは、:min桁以上の数字である必要があります。',
    'missing' => ':attribute を入力する必要はありません。',
    'missing_if' => ':other が :value の場合、:attribute を入力する必要はありません。',
    'missing_unless' => ':other が :value でない限り、:attribute を入力する必要はありません。',
    'missing_with' => ':values が存在する場合、:attribute を入力する必要はありません。',
    'missing_with_all' => ':values が存在する場合、:attribute を入力する必要はありません。',
    'multiple_of' => ':attributeは:valueの倍数である必要があります',
    'not_in' => '選択された:attributeは、有効ではありません。',
    'not_regex' => ':attributeの形式が正しくありません。',
    'numeric' => ':attributeには、数値を指定してください。',
    'password' => [
        'letters' => ':attributeは文字を1文字以上含める必要があります。',
        'mixed' => ':attributeは大文字と小文字をそれぞれ1文字以上含める必要があります。',
        'numbers' => ':attributeは数字を1文字以上含める必要があります。',
        'symbols' => ':attributeは記号を1文字以上含める必要があります。',
        'uncompromised' => ':attributeは情報漏洩した可能性があります。他の:attributeを選択してください。',
    ],
    'present' => ':attributeが存在している必要があります。',
    'present_if' => ':other が :value の場合、:attributeが存在する必要があります。',
    'present_unless' => ':other が :value でない限り、:attributeが存在する必要があります。',
    'present_with' => ':values が存在する場合は、:attributeも存在する必要があります。',
    'present_with_all' => ':values が存在する場合は、:attributeが存在する必要があります。',
    'prohibited' => ':attributeの入力は禁止されています。',
    'prohibited_if' => ':otherが:valueの場合は、:attributeの入力が禁止されています。',
    'prohibited_if_accepted' => ':otherが承認された場合、:attributeの入力は禁止されています。',
    'prohibited_if_declined' => ':otherが拒否された場合、:attributeの入力は禁止されています。',
    'prohibited_unless' => ':otherが:valuesでない限り、:attributeの入力は禁止されています。',
    'prohibits' => ':otherが存在している場合、:attributeの入力は禁止されています。',
    'regex' => ':attributeには、正しい形式を指定してください。',
    'required' => ':attributeは必須項目です。',
    'required_array_keys' => ':attributeには、:valuesのエントリを含める必要があります。',
    'required_if' => ':otherが:valueの場合、:attributeを指定してください。',
    'required_if_accepted' => ':otherを承認した場合、:attributeは必須項目です。',
    'required_if_declined' => ':otherを拒否した場合、:attributeは必須項目です。',
    'required_unless' => ':otherが:values以外の場合、:attributeは必須項目です。',
    'required_with' => ':valuesが入力されている場合、:attributeは必須項目です。',
    'required_with_all' => ':valuesが全て指定されている場合、:attributeは必須項目です。',
    'required_without' => ':valuesが入力されていない場合、:attributeは必須項目です。',
    'required_without_all' => ':valuesが全て指定されていない場合、:attributeを指定してください。',
    'same' => ':attributeと:otherが一致しません。',
    'size' => [
        'array' => ':attributeの項目数は、:size個にしてください。',
        'file' => ':attributeには、:size KBのファイルを指定してください。',
        'numeric' => ':attributeには、:sizeを指定してください。',
        'string' => ':attributeの文字数は、:size文字にしてください。',
    ],
    'starts_with' => ':attributeは、次のいずれかで始まる必要があります。:values',
    'string' => ':attributeには、文字列を指定してください。',
    'timezone' => ':attributeには、有効なタイムゾーンを指定してください。',
    'unique' => '指定の:attributeは既に使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは、大文字で入力してください。',
    'url' => ':attributeは、有効なURL形式で指定してください。',
    'ulid' => ':attributeは、有効なULIDである必要があります。',
    'uuid' => ':attributeは、有効なUUIDである必要があります。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'カスタムメッセージ',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'username' => 'ユーザー名',
        'remember' => 'ログイン状態の保持',
        'work_name' => '日本語タイトル',
        'work_name_english' => '英語タイトル',
        'series' => 'シリーズ',
        'product_code_list' => '作品コードまたはリンク',
        'product_codes' => '作品コード',
        'product_codes.*' => '作品コード',
        'product_format' => '作品形式',
        'product_format.*' => '作品形式',
        'circle' => 'サークル',
        'publisher' => '出版社',
        'brand' => 'ブランド',
        'maker_id' => 'メーカーID',
        'scenario' => 'シナリオ担当',
        'voice_actor' => '声優',
        'illustration' => 'イラスト担当',
        'author' => '作者',
        'genre_custom' => 'カスタムタグ',
        'description' => '日本語の説明',
        'end_date' => '終了日',
        'add.start_date.year' => '開始年',
        'add.start_date.month' => '開始月',
        'add.start_date.day' => '開始日',
        'add.finish_date.year' => '終了年',
        'add.finish_date.month' => '終了月',
        'add.finish_date.day' => '終了日',
        'num_re_listen_times' => '再聴回数',
        'currentPassword' => '現在のパスワード',
        'newPassword' => '新しいパスワード',
        'newPasswordConfirmation' => '新しいパスワードの確認',
        'followFetchedTagsLanguage' => '表示言語に自動で合わせる設定',
        'enabled' => '有効状態',
        'customValue' => 'カスタム値',
        'newTagTitle' => '新しいタグ名',
        'editingTagTitle' => 'タグ名',
        'id' => '作品コード',
        'product_code' => '作品コード',
        'product_ids' => '作品',
        'product_ids.*' => '作品',
        'title' => 'タイトル',
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード（確認用）',
        'current_password' => '現在のパスワード',
        'language' => '表示言語',
        'fetchedTagLanguage' => '取得タグの言語',
        'progress' => '進捗',
        'score' => '評価',
        'age_category' => '年齢区分',
        'work_image' => 'カバー画像',
        'sample_images' => 'サンプル画像',
        'sample_images.*' => 'サンプル画像',
        'sample_image_order' => 'サンプル画像の並び順',
        'description_japanese' => '日本語の説明',
        'description_english' => '英語の説明',
        'tags' => 'タグ',
        'notes' => 'メモ',
        'start_date' => '開始日',
        'finish_date' => '終了日',
        're_listen_value' => '再聴度',
        'priority' => '優先度',
        'newGroupTitle' => '新しいグループ名',
        'mode' => 'モード',
        'theme' => 'テーマ',
        'sizeChoice' => 'ZIPファイルごとの最大サイズ',
        'customMib' => 'ZIPファイルごとの最大サイズ（MiB）',
        'tagOrder' => 'タグ候補の並び順',
        'seriesOrder' => 'シリーズ候補の並び順',
        'focus' => 'UIの表現',
        'archive' => 'ZIPファイル',
    ],

];
