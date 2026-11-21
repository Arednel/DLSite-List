<?php

// Laravel validation messages are based on the Belarusian translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/be).
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

    'accepted' => 'Поле :attribute павінна быць прынята.',
    'accepted_if' => 'Поле :attribute павінна быць прынята, калі :other мае значэнне :value.',
    'active_url' => 'Поле :attribute павінна быць сапраўдным URL-адрасам.',
    'after' => 'Поле :attribute павінна змяшчаць дату пасля :date.',
    'after_or_equal' => 'Поле :attribute павінна змяшчаць дату пасля або роўную :date.',
    'alpha' => 'Поле :attribute можа змяшчаць толькі літары.',
    'alpha_dash' => 'Поле :attribute можа змяшчаць толькі літары, лічбы, злучкі і падкрэсліванні.',
    'alpha_num' => 'Поле :attribute можа змяшчаць толькі літары і лічбы.',
    'any_of' => 'Поле :attribute мае няправільнае значэнне.',
    'array' => 'Поле :attribute павінна быць масівам.',
    'ascii' => 'Поле :attribute можа змяшчаць толькі аднабайтавыя літарна-лічбавыя сімвалы і знакі.',
    'before' => 'Поле :attribute павінна змяшчаць дату да :date.',
    'before_or_equal' => 'Поле :attribute павінна змяшчаць дату да або роўную :date.',
    'between' => [
        'array' => 'Поле :attribute павінна змяшчаць ад :min да :max элементаў.',
        'file' => 'Памер файла :attribute павінен быць ад :min да :max кілабайт.',
        'numeric' => 'Значэнне :attribute павінна быць паміж :min і :max.',
        'string' => 'Колькасць сімвалаў у полі :attribute павінна быць ад :min да :max.',
    ],
    'boolean' => 'Поле :attribute павінна мець лагічнае значэнне.',
    'can' => 'Поле :attribute змяшчае неаўтарызаванае значэнне.',
    'confirmed' => 'Пацвярджэнне поля :attribute не супадае.',
    'contains' => 'Поле :attribute не змяшчае неабходнага значэння.',
    'current_password' => 'Пароль няправільны.',
    'date' => 'Поле :attribute павінна быць карэктнай датай.',
    'date_equals' => 'Поле :attribute павінна быць датай, роўнай :date.',
    'date_format' => 'Поле :attribute не адпавядае фармату :format.',
    'decimal' => 'Поле :attribute павінна мець :decimal знакаў пасля коскі.',
    'declined' => 'Поле :attribute павінна быць адхілена.',
    'declined_if' => 'Поле :attribute павінна быць адхілена, калі :other мае значэнне :value.',
    'different' => 'Палі :attribute і :other павінны адрознівацца.',
    'digits' => 'Поле :attribute павінна змяшчаць :digits лічбаў.',
    'digits_between' => 'Поле :attribute павінна змяшчаць ад :min да :max лічбаў.',
    'dimensions' => 'Поле :attribute мае недапушчальныя памеры выявы.',
    'distinct' => 'Поле :attribute змяшчае паўторнае значэнне.',
    'doesnt_contain' => 'Поле :attribute не павінна змяшчаць ніводнага з наступных значэнняў: :values.',
    'doesnt_end_with' => 'Поле :attribute не павінна заканчвацца адным з наступных значэнняў: :values.',
    'doesnt_start_with' => 'Поле :attribute не павінна пачынацца з аднаго з наступных значэнняў: :values.',
    'email' => 'Поле :attribute павінна быць сапраўдным адрасам электроннай пошты.',
    'encoding' => 'Поле :attribute павінна быць закадавана як :encoding.',
    'ends_with' => 'Поле :attribute павінна заканчвацца адным з наступных значэнняў: :values.',
    'enum' => 'Выбранае значэнне поля :attribute няправільнае.',
    'exists' => 'Выбранае значэнне поля :attribute няправільнае.',
    'extensions' => 'Файл :attribute павінен мець адно з наступных пашырэнняў: :values.',
    'file' => 'Поле :attribute павінна змяшчаць файл.',
    'filled' => 'Поле :attribute павінна мець значэнне.',
    'gt' => [
        'array' => 'Поле :attribute павінна змяшчаць больш за :value элементаў.',
        'file' => 'Памер файла :attribute павінен быць больш за :value кілабайт.',
        'numeric' => 'Значэнне :attribute павінна быць больш за :value.',
        'string' => 'Поле :attribute павінна змяшчаць больш за :value сімвалаў.',
    ],
    'gte' => [
        'array' => 'Поле :attribute павінна змяшчаць :value або больш элементаў.',
        'file' => 'Памер файла :attribute павінен быць не менш за :value кілабайт.',
        'numeric' => 'Значэнне :attribute павінна быць не менш за :value.',
        'string' => 'Поле :attribute павінна змяшчаць не менш за :value сімвалаў.',
    ],
    'hex_color' => 'Поле :attribute павінна змяшчаць карэктны шаснаццатковы колер.',
    'image' => 'Поле :attribute павінна змяшчаць выяву.',
    'in' => 'Выбранае значэнне поля :attribute няправільнае.',
    'in_array' => 'Значэнне поля :attribute павінна прысутнічаць у :other.',
    'in_array_keys' => 'Поле :attribute павінна змяшчаць прынамсі адзін з наступных ключоў: :values.',
    'integer' => 'Поле :attribute павінна быць цэлым лікам.',
    'ip' => 'Поле :attribute павінна быць сапраўдным IP-адрасам.',
    'ipv4' => 'Поле :attribute павінна быць сапраўдным IPv4-адрасам.',
    'ipv6' => 'Поле :attribute павінна быць сапраўдным IPv6-адрасам.',
    'json' => 'Поле :attribute павінна быць карэктным JSON-радком.',
    'list' => 'Поле :attribute павінна быць спісам.',
    'lowercase' => 'Поле :attribute павінна быць у ніжнім рэгістры.',
    'lt' => [
        'array' => 'Поле :attribute павінна змяшчаць менш за :value элементаў.',
        'file' => 'Памер файла :attribute павінен быць менш за :value кілабайт.',
        'numeric' => 'Значэнне :attribute павінна быць менш за :value.',
        'string' => 'Поле :attribute павінна змяшчаць менш за :value сімвалаў.',
    ],
    'lte' => [
        'array' => 'Поле :attribute павінна змяшчаць не больш за :value элементаў.',
        'file' => 'Памер файла :attribute павінен быць не больш за :value кілабайт.',
        'numeric' => 'Значэнне :attribute павінна быць не больш за :value.',
        'string' => 'Поле :attribute павінна змяшчаць не больш за :value сімвалаў.',
    ],
    'mac_address' => 'Поле :attribute павінна быць сапраўдным MAC-адрасам.',
    'max' => [
        'array' => 'Поле :attribute не можа змяшчаць больш за :max элементаў.',
        'file' => 'Памер файла :attribute не можа перавышаць :max кілабайт.',
        'numeric' => 'Значэнне :attribute не можа быць больш за :max.',
        'string' => 'Поле :attribute не можа змяшчаць больш за :max сімвалаў.',
    ],
    'max_digits' => 'Поле :attribute не можа змяшчаць больш за :max лічбаў.',
    'mimes' => 'Файл :attribute павінен быць аднаго з наступных тыпаў: :values.',
    'mimetypes' => 'Файл :attribute павінен быць аднаго з наступных тыпаў: :values.',
    'min' => [
        'array' => 'Поле :attribute павінна змяшчаць прынамсі :min элементаў.',
        'file' => 'Памер файла :attribute павінен быць прынамсі :min кілабайт.',
        'numeric' => 'Значэнне :attribute павінна быць прынамсі :min.',
        'string' => 'Поле :attribute павінна змяшчаць прынамсі :min сімвалаў.',
    ],
    'min_digits' => 'Поле :attribute павінна змяшчаць прынамсі :min лічбаў.',
    'missing' => 'Поле :attribute павінна адсутнічаць.',
    'missing_if' => 'Поле :attribute павінна адсутнічаць, калі :other мае значэнне :value.',
    'missing_unless' => 'Поле :attribute павінна адсутнічаць, калі :other не мае значэння :value.',
    'missing_with' => 'Поле :attribute павінна адсутнічаць, калі прысутнічае :values.',
    'missing_with_all' => 'Поле :attribute павінна адсутнічаць, калі прысутнічаюць усе значэнні :values.',
    'multiple_of' => 'Значэнне :attribute павінна быць кратным :value.',
    'not_in' => 'Выбранае значэнне поля :attribute няправільнае.',
    'not_regex' => 'Фармат поля :attribute няправільны.',
    'numeric' => 'Поле :attribute павінна быць лікам.',
    'password' => [
        'letters' => 'Поле :attribute павінна змяшчаць прынамсі адну літару.',
        'mixed' => 'Поле :attribute павінна змяшчаць прынамсі адну вялікую і адну малую літару.',
        'numbers' => 'Поле :attribute павінна змяшчаць прынамсі адну лічбу.',
        'symbols' => 'Поле :attribute павінна змяшчаць прынамсі адзін сімвал.',
        'uncompromised' => 'Значэнне :attribute было знойдзена ва ўцечцы даных. Выберыце іншае значэнне для :attribute.',
    ],
    'present' => 'Поле :attribute павінна прысутнічаць.',
    'present_if' => 'Поле :attribute павінна прысутнічаць, калі :other мае значэнне :value.',
    'present_unless' => 'Поле :attribute павінна прысутнічаць, калі :other не мае значэння :value.',
    'present_with' => 'Поле :attribute павінна прысутнічаць, калі прысутнічае :values.',
    'present_with_all' => 'Поле :attribute павінна прысутнічаць, калі прысутнічаюць усе значэнні :values.',
    'prohibited' => 'Поле :attribute забаронена.',
    'prohibited_if' => 'Поле :attribute забаронена, калі :other мае значэнне :value.',
    'prohibited_if_accepted' => 'Поле :attribute забаронена, калі :other прынята.',
    'prohibited_if_declined' => 'Поле :attribute забаронена, калі :other адхілена.',
    'prohibited_unless' => 'Поле :attribute забаронена, калі :other не ўваходзіць у :values.',
    'prohibits' => 'Поле :attribute забараняе прысутнасць :other.',
    'regex' => 'Фармат поля :attribute няправільны.',
    'required' => 'Поле :attribute абавязковае.',
    'required_array_keys' => 'Поле :attribute павінна змяшчаць наступныя ключы: :values.',
    'required_if' => 'Поле :attribute абавязковае, калі :other мае значэнне :value.',
    'required_if_accepted' => 'Поле :attribute абавязковае, калі :other прынята.',
    'required_if_declined' => 'Поле :attribute абавязковае, калі :other адхілена.',
    'required_unless' => 'Поле :attribute абавязковае, калі :other не ўваходзіць у :values.',
    'required_with' => 'Поле :attribute абавязковае, калі прысутнічае :values.',
    'required_with_all' => 'Поле :attribute абавязковае, калі прысутнічаюць усе значэнні :values.',
    'required_without' => 'Поле :attribute абавязковае, калі :values адсутнічае.',
    'required_without_all' => 'Поле :attribute абавязковае, калі ўсе значэнні :values адсутнічаюць.',
    'same' => 'Палі :attribute і :other павінны супадаць.',
    'size' => [
        'array' => 'Поле :attribute павінна змяшчаць :size элементаў.',
        'file' => 'Памер файла :attribute павінен быць роўны :size кілабайт.',
        'numeric' => 'Значэнне :attribute павінна быць роўна :size.',
        'string' => 'Поле :attribute павінна змяшчаць :size сімвалаў.',
    ],
    'starts_with' => 'Поле :attribute павінна пачынацца з аднаго з наступных значэнняў: :values.',
    'string' => 'Поле :attribute павінна быць радком.',
    'timezone' => 'Поле :attribute павінна быць сапраўдным часавым поясам.',
    'unique' => 'Такое значэнне поля :attribute ужо існуе.',
    'uploaded' => 'Не ўдалося загрузіць файл :attribute.',
    'uppercase' => 'Поле :attribute павінна быць у верхнім рэгістры.',
    'url' => 'Поле :attribute павінна быць сапраўдным URL-адрасам.',
    'ulid' => 'Поле :attribute павінна быць карэктным ULID.',
    'uuid' => 'Поле :attribute павінна быць карэктным UUID.',

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
            'required' => 'Патрабуецца задаць карыстальніцкую шырыню слупка.',
            'regex' => 'Недапушчальны фармат шырыні слупка. Выкарыстоўвайце px, rem, em, %, або vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'Патрабуецца задаць рэжым шырыні слупка.',
            'in' => 'Выбраны рэжым шырыні слупка няправільны.',
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
        'username' => 'Імя карыстальніка',
        'remember' => 'Запомніць мяне',
        'work_name' => 'Назва па-японску',
        'metadata_language' => 'Мова метаданых DLsite',
        'localized_title' => 'Лакалізаваная назва',
        'series' => 'Серыя',
        'product_code_list' => 'Код працы або спасылка',
        'product_codes' => 'Коды прац',
        'product_codes.*' => 'Код працы',
        'product_format' => 'Фармат працы',
        'product_format.*' => 'Фармат працы',
        'circle' => 'Circle',
        'publisher' => 'Выдавец',
        'brand' => 'Брэнд',
        'maker_id' => 'ID стваральніка',
        'scenario' => 'Аўтар сцэнарыя',
        'voice_actor' => 'Актор агучвання',
        'illustration' => 'Аўтар ілюстрацый',
        'author' => 'Аўтар',
        'genre_custom' => 'Карыстальніцкія тэгі',
        'description' => 'Апісанне па-японску',
        'end_date' => 'Дата завяршэння',
        'add.start_date.year' => 'Год пачатку',
        'add.start_date.month' => 'Месяц пачатку',
        'add.start_date.day' => 'Дзень пачатку',
        'add.finish_date.year' => 'Год завяршэння',
        'add.finish_date.month' => 'Месяц завяршэння',
        'add.finish_date.day' => 'Дзень завяршэння',
        'num_re_listen_times' => 'Колькасць паўторных праслухоўванняў',
        'currentPassword' => 'Бягучы пароль',
        'newPassword' => 'Новы пароль',
        'newPasswordConfirmation' => 'Пацвярджэнне новага пароля',
        'followFetchedTagsLanguage' => 'Аўтаматычна прытрымлівацца мовы інтэрфейсу',
        'enabled' => 'Стан уключэння',
        'customValue' => 'Карыстальніцкае значэнне',
        'newTagTitle' => 'Назва новага тэга',
        'editingTagTitle' => 'Назва тэга',
        'id' => 'Код працы',
        'product_code' => 'Код працы',
        'product_ids' => 'Працы',
        'product_ids.*' => 'Праца',
        'title' => 'Назва',
        'name' => 'Імя',
        'email' => 'Адрас электроннай пошты',
        'password' => 'Пароль',
        'password_confirmation' => 'Пацвярджэнне пароля',
        'current_password' => 'Бягучы пароль',
        'language' => 'Мова інтэрфейсу',
        'progress' => 'Прагрэс',
        'score' => 'Ацэнка',
        'age_category' => 'Узроставая катэгорыя',
        'work_image' => 'Вокладка',
        'sample_images' => 'Прыклады выяў',
        'sample_images.*' => 'Узор выявы',
        'sample_image_order' => 'Парадак узораў выяў',
        'description_japanese' => 'Апісанне па-японску',
        'description_localized' => 'Лакалізаванае апісанне',
        'tags' => 'Тэгі',
        'notes' => 'Нататкі',
        'start_date' => 'Дата пачатку',
        'finish_date' => 'Дата завяршэння',
        're_listen_value' => 'Значэнне паўторнага праслухоўвання',
        'priority' => 'Прыярытэт',
        'newGroupTitle' => 'Назва новай групы',
        'mode' => 'Рэжым',
        'theme' => 'Тэма',
        'sizeChoice' => 'Максімальны памер на ZIP',
        'customMib' => 'Максімальны памер на ZIP (MiB)',
        'tagOrder' => 'Парадак прапаноў тэгаў',
        'seriesOrder' => 'Парадак прапаноў серый',
        'focus' => 'Фармулёўкі інтэрфейсу',
        'archive' => 'ZIP-файл',
    ],
];
