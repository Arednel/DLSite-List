<?php

// Laravel validation messages are based on the Simplified Chinese translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/zh_CN).
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

    'accepted' => '您必须接受 :attribute。',
    'accepted_if' => '当 :other 为 :value 时，必须接受 :attribute。',
    'active_url' => ':attribute 不是一个有效的网址。',
    'after' => ':attribute 必须要晚于 :date。',
    'after_or_equal' => ':attribute 必须要等于 :date 或更晚。',
    'alpha' => ':attribute 只能由字母组成。',
    'alpha_dash' => ':attribute 只能由字母、数字、短划线(-)和下划线(_)组成。',
    'alpha_num' => ':attribute 只能由字母和数字组成。',
    'any_of' => ':attribute 字段无效。',
    'array' => ':attribute 必须是一个数组。',
    'ascii' => ':attribute 必须仅包含单字节字母数字字符和符号。',
    'before' => ':attribute 必须要早于 :date。',
    'before_or_equal' => ':attribute 必须要等于 :date 或更早。',
    'between' => [
        'array' => ':attribute 必须只有 :min - :max 个单元。',
        'file' => ':attribute 必须介于 :min - :max KB 之间。',
        'numeric' => ':attribute 必须介于 :min - :max 之间。',
        'string' => ':attribute 必须介于 :min - :max 个字符之间。',
    ],
    'boolean' => ':attribute 必须为布尔值。',
    'can' => ':attribute 字段包含未经授权的值。',
    'confirmed' => ':attribute 两次输入不一致。',
    'contains' => ':attribute 字段缺少必填值。',
    'current_password' => '密码错误。',
    'date' => ':attribute 不是一个有效的日期。',
    'date_equals' => ':attribute 必须要等于 :date。',
    'date_format' => ':attribute 的格式必须为 :format。',
    'decimal' => ':attribute 必须有 :decimal 位小数。',
    'declined' => ':attribute 必须是拒绝的。',
    'declined_if' => '当 :other 为 :value 时字段 :attribute 必须是拒绝的。',
    'different' => ':attribute 和 :other 必须不同。',
    'digits' => ':attribute 必须是 :digits 位数字。',
    'digits_between' => ':attribute 必须是介于 :min 和 :max 位的数字。',
    'dimensions' => ':attribute 图片尺寸不正确。',
    'distinct' => ':attribute 已经存在。',
    'doesnt_contain' => ':attribute 不能包含以下任意内容：:values。',
    'doesnt_end_with' => ':attribute 不能以以下之一结尾: :values。',
    'doesnt_start_with' => ':attribute 不能以下列之一开头: :values。',
    'email' => ':attribute 不是一个合法的邮箱。',
    'encoding' => ':attribute 字段必须使用 :encoding 编码。',
    'ends_with' => ':attribute 必须以 :values 为结尾。',
    'enum' => ':attribute 值不正确。',
    'exists' => ':attribute 不存在。',
    'extensions' => ':attribute 字段必须具有以下扩展名之一：:values。',
    'file' => ':attribute 必须是文件。',
    'filled' => ':attribute 不能为空。',
    'gt' => [
        'array' => ':attribute 必须多于 :value 个元素。',
        'file' => ':attribute 必须大于 :value KB。',
        'numeric' => ':attribute 必须大于 :value。',
        'string' => ':attribute 必须多于 :value 个字符。',
    ],
    'gte' => [
        'array' => ':attribute 必须多于或等于 :value 个元素。',
        'file' => ':attribute 必须大于或等于 :value KB。',
        'numeric' => ':attribute 必须大于或等于 :value。',
        'string' => ':attribute 必须多于或等于 :value 个字符。',
    ],
    'hex_color' => ':attribute 字段必须是有效的十六进制颜色。',
    'image' => ':attribute 必须是图片。',
    'in' => '已选的属性 :attribute 无效。',
    'in_array' => ':attribute 必须在 :other 中。',
    'in_array_keys' => ':attribute 必须至少包含以下任意一个键：:values。',
    'integer' => ':attribute 必须是整数。',
    'ip' => ':attribute 必须是有效的 IP 地址。',
    'ipv4' => ':attribute 必须是有效的 IPv4 地址。',
    'ipv6' => ':attribute 必须是有效的 IPv6 地址。',
    'json' => ':attribute 必须是正确的 JSON 格式。',
    'list' => ':attribute 字段必须是一个列表。',
    'lowercase' => ':attribute 必须小写。',
    'lt' => [
        'array' => ':attribute 必须少于 :value 个元素。',
        'file' => ':attribute 必须小于 :value KB。',
        'numeric' => ':attribute 必须小于 :value。',
        'string' => ':attribute 必须少于 :value 个字符。',
    ],
    'lte' => [
        'array' => ':attribute 必须少于或等于 :value 个元素。',
        'file' => ':attribute 必须小于或等于 :value KB。',
        'numeric' => ':attribute 必须小于或等于 :value。',
        'string' => ':attribute 必须少于或等于 :value 个字符。',
    ],
    'mac_address' => ':attribute 必须是一个有效的 MAC 地址。',
    'max' => [
        'array' => ':attribute 最多只有 :max 个单元。',
        'file' => ':attribute 不能大于 :max KB。',
        'numeric' => ':attribute 不能大于 :max。',
        'string' => ':attribute 不能大于 :max 个字符。',
    ],
    'max_digits' => ':attribute 不能超过 :max 位数。',
    'mimes' => ':attribute 必须是一个 :values 类型的文件。',
    'mimetypes' => ':attribute 必须是一个 :values 类型的文件。',
    'min' => [
        'array' => ':attribute 至少有 :min 个单元。',
        'file' => ':attribute 大小不能小于 :min KB。',
        'numeric' => ':attribute 必须大于等于 :min。',
        'string' => ':attribute 至少为 :min 个字符。',
    ],
    'min_digits' => ':attribute 必须至少有 :min 位数。',
    'missing' => '必须缺少 :attribute 字段。',
    'missing_if' => '当 :other 为 :value 时，必须缺少 :attribute 字段。',
    'missing_unless' => '必须缺少 :attribute 字段，除非 :other 是 :value。',
    'missing_with' => '存在 :values 时，必须缺少 :attribute 字段。',
    'missing_with_all' => '存在 :values 时，必须缺少 :attribute 字段。',
    'multiple_of' => ':attribute 必须是 :value 中的多个值。',
    'not_in' => '已选的属性 :attribute 非法。',
    'not_regex' => ':attribute 的格式错误。',
    'numeric' => ':attribute 必须是一个数字。',
    'password' => [
        'letters' => ':attribute 必须至少包含一个字母。',
        'mixed' => ':attribute 必须至少包含一个大写字母和一个小写字母。',
        'numbers' => ':attribute 必须至少包含一个数字。',
        'symbols' => ':attribute 必须至少包含一个符号。',
        'uncompromised' => '给定的 :attribute 出现在已经泄漏的密码中。请选择不同的 :attribute。',
    ],
    'present' => ':attribute 必须存在。',
    'present_if' => '当 :other 等于 :value 时，必须存在 :attribute 字段。',
    'present_unless' => '除非 :other 等于 :value，否则 :attribute 字段必须存在。',
    'present_with' => '当 :values 存在时，:attribute 字段必须存在。',
    'present_with_all' => '当存在 :values 时，必须存在 :attribute 字段。',
    'prohibited' => ':attribute 字段被禁止。',
    'prohibited_if' => '当 :other 为 :value 时，禁止 :attribute 字段。',
    'prohibited_if_accepted' => ':attribute 字段在 :other 被接受时禁止。',
    'prohibited_if_declined' => ':attribute 字段在 :other 被拒绝时禁止。',
    'prohibited_unless' => ':attribute 字段被禁止，除非 :other 位于 :values 中。',
    'prohibits' => ':attribute 字段禁止出现 :other。',
    'regex' => ':attribute 格式不正确。',
    'required' => ':attribute 不能为空。',
    'required_array_keys' => ':attribute 至少包含指定的键：:values.',
    'required_if' => '当 :other 为 :value 时 :attribute 不能为空。',
    'required_if_accepted' => '当 :other 存在时，:attribute 不能为空。',
    'required_if_declined' => '当 :other 不存在时，:attribute 不能为空。',
    'required_unless' => '当 :other 不为 :values 时 :attribute 不能为空。',
    'required_with' => '当 :values 存在时 :attribute 不能为空。',
    'required_with_all' => '当 :values 存在时 :attribute 不能为空。',
    'required_without' => '当 :values 不存在时 :attribute 不能为空。',
    'required_without_all' => '当 :values 都不存在时 :attribute 不能为空。',
    'same' => ':attribute 和 :other 必须相同。',
    'size' => [
        'array' => ':attribute 必须为 :size 个单元。',
        'file' => ':attribute 大小必须为 :size KB。',
        'numeric' => ':attribute 大小必须为 :size。',
        'string' => ':attribute 必须是 :size 个字符。',
    ],
    'starts_with' => ':attribute 必须以 :values 为开头。',
    'string' => ':attribute 必须是一个字符串。',
    'timezone' => ':attribute 必须是一个合法的时区值。',
    'unique' => ':attribute 已经存在。',
    'uploaded' => ':attribute 上传失败。',
    'uppercase' => ':attribute 必须大写',
    'url' => ':attribute 格式不正确。',
    'ulid' => ':attribute 必须是有效的 ULID。',
    'uuid' => ':attribute 必须是有效的 UUID。',

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
            'required' => '必须填写自定义列宽。',
            'regex' => '列宽格式无效。请使用 px、rem、em、% 或 vw。',
        ],
        'indexFields.*.width_mode' => [
            'required' => '必须选择列宽模式。',
            'in' => '所选的列宽模式无效。',
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
        'username' => '用户名',
        'remember' => '记住我',
        'work_name' => '日文标题',
        'metadata_language' => 'DLsite 元数据语言',
        'localized_title' => '本地化标题',
        'series' => '系列',
        'product_code_list' => '作品编号或链接',
        'product_codes' => '作品编号',
        'product_codes.*' => '作品编号',
        'product_format' => '作品形式',
        'product_format.*' => '作品形式',
        'circle' => '社团',
        'publisher' => '出版社',
        'brand' => '品牌',
        'maker_id' => '创作者 ID',
        'scenario' => '剧本作者',
        'voice_actor' => '声优',
        'illustration' => '插画作者',
        'author' => '作者',
        'genre_custom' => '自定义标签',
        'description' => '日文说明',
        'end_date' => '完成日期',
        'add.start_date.year' => '开始年份',
        'add.start_date.month' => '开始月份',
        'add.start_date.day' => '开始日期',
        'add.finish_date.year' => '完成年份',
        'add.finish_date.month' => '完成月份',
        'add.finish_date.day' => '完成日期',
        'num_re_listen_times' => '重复体验次数',
        'currentPassword' => '当前密码',
        'newPassword' => '新密码',
        'newPasswordConfirmation' => '新密码确认',
        'followFetchedTagsLanguage' => '自动跟随界面语言',
        'enabled' => '启用设置',
        'customValue' => '自定义值',
        'newTagTitle' => '新标签名称',
        'editingTagTitle' => '标签名称',
        'id' => '作品编号',
        'product_code' => '作品编号',
        'product_ids' => '作品',
        'product_ids.*' => '作品',
        'title' => '标题',
        'name' => '名称',
        'email' => '电子邮件地址',
        'password' => '密码',
        'password_confirmation' => '确认密码',
        'current_password' => '当前密码',
        'language' => '界面语言',
        'progress' => '进度',
        'score' => '评分',
        'age_category' => '年龄分级',
        'work_image' => '封面图片',
        'sample_images' => '样本图片',
        'sample_images.*' => '样本图片',
        'sample_image_order' => '样本图片顺序',
        'description_japanese' => '日文说明',
        'description_localized' => '本地化说明',
        'tags' => '标签',
        'notes' => '备注',
        'start_date' => '开始日期',
        'finish_date' => '完成日期',
        're_listen_value' => '重复值',
        'priority' => '优先级',
        'newGroupTitle' => '新组标题',
        'mode' => '显示模式',
        'theme' => '主题',
        'sizeChoice' => 'ZIP 分卷大小',
        'customMib' => '每个 ZIP 的最大大小（MiB）',
        'tagOrder' => '标签排序',
        'seriesOrder' => '系列排序',
        'focus' => '内容类型',
        'archive' => 'ZIP 文件',
    ],
];
