<?php

// Laravel validation messages are based on the Korean translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/ko).
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

    'accepted' => ':attribute을(를) 동의해야 합니다.',
    'accepted_if' => ':attribute을(를) :other이(가) :value이면 동의해야 합니다.',
    'active_url' => ':attribute은(는) 유효한 URL이 아닙니다.',
    'after' => ':attribute은(는) :date 이후 날짜여야 합니다.',
    'after_or_equal' => ':attribute은(는) :date 이후 날짜이거나 같은 날짜여야 합니다.',
    'alpha' => ':attribute은(는) 문자만 포함할 수 있습니다.',
    'alpha_dash' => ':attribute은(는) 문자, 숫자, 대쉬(-), 밑줄(_)만 포함할 수 있습니다.',
    'alpha_num' => ':attribute은(는) 문자와 숫자만 포함할 수 있습니다.',
    'any_of' => ':attribute은(는) 유효하지 않습니다.',
    'array' => ':attribute은(는) 배열이어야 합니다.',
    'ascii' => ':attribute은 1바이트 영숫자 문자와 기호만 포함해야 합니다.',
    'before' => ':attribute은(는) :date 이전 날짜여야 합니다.',
    'before_or_equal' => ':attribute은(는) :date 이전 날짜이거나 같은 날짜여야 합니다.',
    'between' => [
        'array' => ':attribute의 항목 수는 :min에서 :max 개의 항목이 있어야 합니다.',
        'file' => ':attribute의 용량은 :min에서 :max 킬로바이트 사이여야 합니다.',
        'numeric' => ':attribute의 값은 :min에서 :max 사이여야 합니다.',
        'string' => ':attribute의 길이는 :min에서 :max 문자 사이여야 합니다.',
    ],
    'boolean' => ':attribute은(는) true 또는 false 이어야 합니다.',
    'can' => ':attribute 필드에 승인되지 않은 값이 포함되어 있습니다.',
    'confirmed' => ':attribute 확인 항목이 일치하지 않습니다.',
    'contains' => ':attribute 필드에 필수 값이 누락되었습니다.',
    'current_password' => '패스워드가 일치하지 않습니다.',
    'date' => ':attribute은(는) 유효한 날짜가 아닙니다.',
    'date_equals' => ':attribute은(는) :date과(와) 같은날짜여야합니다.',
    'date_format' => ':attribute이(가) :format 형식과 일치하지 않습니다.',
    'decimal' => ':attribute은 소수점 이하 :decimal자리여야 합니다.',
    'declined' => ':attribute은(는) 거부되어야 합니다.',
    'declined_if' => ':other이(가) :value일때 :attribute은(는) 거부되어야 합니다.',
    'different' => ':attribute와(과) :other은(는) 서로 달라야 합니다.',
    'digits' => ':attribute은(는) :digits 자리 숫자여야 합니다.',
    'digits_between' => ':attribute은(는) :min에서 :max 자리 사이여야 합니다.',
    'dimensions' => ':attribute은(는) 올바르지 않는 이미지 크기입니다.',
    'distinct' => ':attribute 필드에 중복된 값이 있습니다.',
    'doesnt_contain' => ':attribute 필드에는 다음 값들이 포함될 수 없습니다: :values.',
    'doesnt_end_with' => ':attribute은 다음 중 하나로 끝날 수 없습니다: :values.',
    'doesnt_start_with' => ':attribute은 다음 중 하나로 시작할 수 없습니다: :values.',
    'email' => ':attribute은(는) 유효한 이메일 주소여야 합니다.',
    'encoding' => ':attribute은(는) :encoding (으)로 인코딩 되어야 합니다.',
    'ends_with' => ':attribute은(는) 다음 중 하나로 끝나야 합니다: :values.',
    'enum' => ':attribute의 값이 잘못되었습니다.',
    'exists' => ':attribute이(가) 존재하지 않습니다.',
    'extensions' => ':attribute 필드에는 다음 확장자 중 하나가 있어야 합니다. :values.',
    'file' => ':attribute은(는) 파일이어야 합니다.',
    'filled' => ':attribute 필드는 값이 있어야 합니다.',
    'gt' => [
        'array' => ':attribute의 항목 수는 :value개 보다 많아야 합니다.',
        'file' => ':attribute의 용량은 :value킬로바이트보다 커야 합니다.',
        'numeric' => ':attribute의 값은 :value보다 커야 합니다.',
        'string' => ':attribute의 길이는 :value보다 길어야 합니다.',
    ],
    'gte' => [
        'array' => ':attribute의 항목 수는 :value개 보다 같거나 많아야 합니다.',
        'file' => ':attribute의 용량은 :value킬로바이트보다 같거나 커야 합니다.',
        'numeric' => ':attribute의 값은 :value보다 같거나 커야 합니다.',
        'string' => ':attribute의 길이는 :value보다 같거나 길어야 합니다.',
    ],
    'hex_color' => ':attribute 필드는 유효한 16진수 색상이어야 합니다.',
    'image' => ':attribute은(는) 이미지여야 합니다.',
    'in' => '선택된 :attribute은(는) 올바르지 않습니다.',
    'in_array' => ':attribute 필드는 :other에 존재하지 않습니다.',
    'in_array_keys' => ':attribute 필드는 다음 중 하나 이상의 키를 포함해야 합니다: :values.',
    'integer' => ':attribute은(는) 정수여야 합니다.',
    'ip' => ':attribute은(는) 유효한 IP 주소여야 합니다.',
    'ipv4' => ':attribute은(는) 유효한 IPv4 주소여야 합니다.',
    'ipv6' => ':attribute은(는) 유효한 IPv6 주소여야 합니다.',
    'json' => ':attribute은(는) JSON 문자열이어야 합니다.',
    'list' => ':attribute 필드는 목록이어야 합니다.',
    'lowercase' => ':attribute은 소문자여야 합니다.',
    'lt' => [
        'array' => ':attribute의 항목 수는 :value개 보다 작아야 합니다.',
        'file' => ':attribute의 용량은 :value킬로바이트보다 작아야 합니다.',
        'numeric' => ':attribute의 값은 :value보다 작아야 합니다.',
        'string' => ':attribute의 길이는 :value보다 짧아야 합니다.',
    ],
    'lte' => [
        'array' => ':attribute의 항목 수는 :value개 보다 같거나 작아야 합니다.',
        'file' => ':attribute의 용량은 :value킬로바이트보다 같거나 작아야 합니다.',
        'numeric' => ':attribute의 값은 :value보다 같거나 작아야 합니다.',
        'string' => ':attribute의 길이는 :value보다 같거나 짧아야 합니다.',
    ],
    'mac_address' => ':attribute은(는) 올바른 MAC 주소가 아닙니다.',
    'max' => [
        'array' => ':attribute은(는) :max개보다 많을 수 없습니다.',
        'file' => ':attribute은(는) :max킬로바이트보다 클 수 없습니다.',
        'numeric' => ':attribute은(는) :max보다 클 수 없습니다.',
        'string' => ':attribute은(는) :max자보다 클 수 없습니다.',
    ],
    'max_digits' => ':attribute은 :max자리를 넘지 않아야 합니다.',
    'mimes' => ':attribute은(는) 다음의 파일 형식이어야 합니다: :values.',
    'mimetypes' => ':attribute은(는) 다음의 파일 형식이어야 합니다: :values.',
    'min' => [
        'array' => ':attribute은(는) 최소한 :min개의 항목이 있어야 합니다.',
        'file' => ':attribute은(는) 최소한 :min킬로바이트이어야 합니다.',
        'numeric' => ':attribute은(는) 최소한 :min이어야 합니다.',
        'string' => ':attribute은(는) 최소한 :min자이어야 합니다.',
    ],
    'min_digits' => ':attribute은 :min자리 이상이어야 합니다.',
    'missing' => ':attribute 필드가 없어야 합니다.',
    'missing_if' => ':other이 :value일 때 :attribute 필드는 누락되어야 합니다.',
    'missing_unless' => ':other이 :value이 아닌 경우 :attribute 필드는 누락되어야 합니다.',
    'missing_with' => ':values이 있는 경우 :attribute 필드는 누락되어야 합니다.',
    'missing_with_all' => ':values이 있는 경우 :attribute 필드는 누락되어야 합니다.',
    'multiple_of' => ':attribute 는 :value 의 배수여야 합니다.',
    'not_in' => '선택된 :attribute이(가) 올바르지 않습니다.',
    'not_regex' => ':attribute의 형식이 올바르지 않습니다.',
    'numeric' => ':attribute은(는) 숫자여야 합니다.',
    'password' => [
        'letters' => ':attribute은 적어도 하나의 문자를 포함해야 합니다.',
        'mixed' => ':attribute은 적어도 하나의 대문자와 하나의 소문자를 포함해야 합니다.',
        'numbers' => ':attribute은 적어도 하나의 숫자를 포함해야 합니다.',
        'symbols' => ':attribute은 적어도 하나의 기호를 포함해야 합니다.',
        'uncompromised' => '주어진 :attribute이 데이터 유출로 나타났습니다. 다른 :attribute개를 선택하세요.',
    ],
    'present' => ':attribute 필드가 있어야 합니다.',
    'present_if' => ':other이 :value인 경우 :attribute 필드가 있어야 합니다.',
    'present_unless' => ':other이 :value이 아닌 이상 :attribute 필드가 있어야 합니다.',
    'present_with' => ':values이 있으면 :attribute 필드도 있어야 합니다.',
    'present_with_all' => ':values이 있을 때 :attribute 필드도 있어야 합니다.',
    'prohibited' => ':attribute (은)는 금지되어 있습니다.',
    'prohibited_if' => ':attribute (은)는 :other 이(가) :value 인 경우 금지되어 있습니다.',
    'prohibited_if_accepted' => ':attribute 필드는 :other이 허용되면 금지됩니다.',
    'prohibited_if_declined' => ':other이 감소 할 때 :attribute 필드는 금지됩니다.',
    'prohibited_unless' => ':attribute 값이 올바르지 않습니다 (:other, :values).',
    'prohibits' => ':attribute (은)는 :other 을(를) 금지합니다.',
    'regex' => ':attribute 형식이 올바르지 않습니다.',
    'required' => ':attribute 필드는 필수입니다.',
    'required_array_keys' => ':attribute 필드는 :values에 대한 항목을 포함해야 합니다.',
    'required_if' => ':other이(가) :value 일 때 :attribute 필드는 필수입니다.',
    'required_if_accepted' => ':other이 승인되면 :attribute 필드가 필요합니다.',
    'required_if_declined' => ':other이 거부되는 경우 :attribute 필드가 필요합니다.',
    'required_unless' => ':other이(가) :values에 없다면 :attribute 필드는 필수입니다.',
    'required_with' => ':values이(가) 있는 경우 :attribute 필드는 필수입니다.',
    'required_with_all' => ':values이(가) 모두 있는 경우 :attribute 필드는 필수입니다.',
    'required_without' => ':values이(가) 없는 경우 :attribute 필드는 필수입니다.',
    'required_without_all' => ':values이(가) 모두 없는 경우 :attribute 필드는 필수입니다.',
    'same' => ':attribute와(과) :other은(는) 일치해야 합니다.',
    'size' => [
        'array' => ':attribute은(는) :size개의 항목을 포함해야 합니다.',
        'file' => ':attribute은(는) :size킬로바이트여야 합니다.',
        'numeric' => ':attribute은(는) :size (이)여야 합니다.',
        'string' => ':attribute은(는) :size자여야 합니다.',
    ],
    'starts_with' => ':attribute은(는) :values 중 하나로 시작해야 합니다.',
    'string' => ':attribute은(는) 문자열이어야 합니다.',
    'timezone' => ':attribute은(는) 올바른 시간대 이어야 합니다.',
    'unique' => ':attribute은(는) 이미 사용 중입니다.',
    'uploaded' => ':attribute을(를) 업로드하지 못했습니다.',
    'uppercase' => ':attribute은 대문자여야 합니다.',
    'url' => ':attribute은(는) 형식은 올바르지 않습니다.',
    'ulid' => ':attribute은 유효한 ULID여야 합니다.',
    'uuid' => ':attribute은(는) 유효한UUID여야합니다.',

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
        'indexFields.*.width' => [
            'required' => '사용자 지정 열 너비는 필수입니다.',
            'regex' => '열 너비 형식이 올바르지 않습니다. px, rem, em, %, vw 중 하나를 사용하세요.',
        ],
        'indexFields.*.width_mode' => [
            'required' => '열 너비 모드는 필수입니다.',
            'in' => '선택한 열 너비 모드가 올바르지 않습니다.',
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
        'username' => '사용자 이름',
        'remember' => '로그인 유지',
        'work_name' => '일본어 제목',
        'metadata_language' => '기본 메타데이터 언어',
        'localized_title' => '현지화 제목',
        'series' => '시리즈',
        'product_code_list' => '작품 코드 또는 링크',
        'product_codes' => '작품 코드',
        'product_codes.*' => '작품 코드',
        'product_format' => '작품 형식',
        'product_format.*' => '작품 형식',
        'circle' => '서클',
        'publisher' => '출판사',
        'brand' => '브랜드',
        'maker_id' => '제작자 ID',
        'scenario' => '시나리오 작가',
        'voice_actor' => '성우',
        'illustration' => '일러스트레이터',
        'author' => '작가',
        'genre_custom' => '사용자 지정 태그',
        'description' => '일본어 설명',
        'end_date' => '완료일',
        'add.start_date.year' => '시작 연도',
        'add.start_date.month' => '시작 월',
        'add.start_date.day' => '시작 일',
        'add.finish_date.year' => '완료 연도',
        'add.finish_date.month' => '완료 월',
        'add.finish_date.day' => '완료 일',
        'num_re_listen_times' => '재청취 총 횟수',
        'currentPassword' => '현재 비밀번호',
        'newPassword' => '새 비밀번호',
        'newPasswordConfirmation' => '새 비밀번호 확인',
        'followFetchedTagsLanguage' => 'UI 언어 자동으로 따르기',
        'enabled' => '활성화 설정',
        'customValue' => '사용자 지정 값',
        'newTagTitle' => '새 태그 제목',
        'editingTagTitle' => '태그 제목',
        'id' => '작품 코드',
        'product_code' => '작품 코드',
        'product_ids' => '작품',
        'product_ids.*' => '작품',
        'title' => '제목',
        'name' => '이름',
        'email' => '이메일 주소',
        'password' => '비밀번호',
        'password_confirmation' => '비밀번호 확인',
        'current_password' => '현재 비밀번호',
        'language' => 'UI 언어',
        'progress' => '진행 상황',
        'score' => '점수',
        'age_category' => '연령',
        'work_image' => '표지 이미지',
        'sample_images' => '샘플 이미지',
        'sample_images.*' => '샘플 이미지',
        'sample_image_order' => '샘플 이미지 순서',
        'description_japanese' => '일본어 설명',
        'description_localized' => '현지화 설명',
        'tags' => '태그',
        'notes' => '메모',
        'start_date' => '시작일',
        'finish_date' => '완료일',
        're_listen_value' => '재청취 값',
        'priority' => '우선순위',
        'newGroupTitle' => '새 그룹 제목',
        'mode' => '표시 모드',
        'theme' => '테마',
        'sizeChoice' => 'ZIP 분할 크기',
        'customMib' => 'ZIP당 최대 크기 (MiB)',
        'tagOrder' => '태그 순서',
        'seriesOrder' => '시리즈 순서',
        'focus' => '콘텐츠 유형',
        'archive' => 'ZIP 파일',
    ],
];
