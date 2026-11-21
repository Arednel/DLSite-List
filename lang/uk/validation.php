<?php

// Laravel validation messages are based on the Ukrainian translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/uk).
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

    'accepted' => 'Поле :attribute має бути прийняте.',
    'accepted_if' => 'Поле :attribute має бути прийняте, коли :other має значення :value.',
    'active_url' => 'Поле :attribute має бути дійсною URL-адресою.',
    'after' => 'Поле :attribute має містити дату після :date.',
    'after_or_equal' => 'Поле :attribute має містити дату після або рівну :date.',
    'alpha' => 'Поле :attribute може містити лише літери.',
    'alpha_dash' => 'Поле :attribute може містити лише літери, цифри, дефіси та підкреслення.',
    'alpha_num' => 'Поле :attribute може містити лише літери та цифри.',
    'any_of' => 'Поле :attribute має недійсне значення.',
    'array' => 'Поле :attribute має бути масивом.',
    'ascii' => 'Поле :attribute може містити лише однобайтові алфавітно-цифрові символи та знаки.',
    'before' => 'Поле :attribute має містити дату до :date.',
    'before_or_equal' => 'Поле :attribute має містити дату до або рівну :date.',
    'between' => [
        'array' => 'Поле :attribute має містити від :min до :max елементів.',
        'file' => 'Розмір файлу :attribute має бути від :min до :max кілобайтів.',
        'numeric' => 'Значення :attribute має бути між :min та :max.',
        'string' => 'Кількість символів у полі :attribute має бути від :min до :max.',
    ],
    'boolean' => 'Поле :attribute має бути логічного типу.',
    'can' => 'Поле :attribute містить неавторизоване значення.',
    'confirmed' => 'Підтвердження поля :attribute не збігається.',
    'contains' => 'Поле :attribute не містить необхідного значення.',
    'current_password' => 'Пароль неправильний.',
    'date' => 'Поле :attribute має бути коректною датою.',
    'date_equals' => 'Поле :attribute має бути датою, рівною :date.',
    'date_format' => 'Поле :attribute не відповідає формату :format.',
    'decimal' => 'Поле :attribute має містити :decimal знаків після коми.',
    'declined' => 'Поле :attribute має бути відхилене.',
    'declined_if' => 'Поле :attribute має бути відхилене, коли :other має значення :value.',
    'different' => 'Поля :attribute та :other мають відрізнятися.',
    'digits' => 'Поле :attribute має містити :digits цифр.',
    'digits_between' => 'Поле :attribute має містити від :min до :max цифр.',
    'dimensions' => 'Поле :attribute має неприпустимі розміри зображення.',
    'distinct' => 'Поле :attribute містить повторюване значення.',
    'doesnt_contain' => 'Поле :attribute не повинно містити жодного з таких значень: :values.',
    'doesnt_end_with' => 'Поле :attribute не повинно закінчуватися одним із таких значень: :values.',
    'doesnt_start_with' => 'Поле :attribute не повинно починатися з одного з таких значень: :values.',
    'email' => 'Поле :attribute має бути дійсною електронною адресою.',
    'encoding' => 'Поле :attribute має бути закодоване як :encoding.',
    'ends_with' => 'Поле :attribute має закінчуватися одним із таких значень: :values.',
    'enum' => 'Вибране значення поля :attribute недійсне.',
    'exists' => 'Вибране значення поля :attribute недійсне.',
    'extensions' => 'Файл :attribute має мати одне з таких розширень: :values.',
    'file' => 'Поле :attribute має містити файл.',
    'filled' => 'Поле :attribute має містити значення.',
    'gt' => [
        'array' => 'Поле :attribute має містити більше ніж :value елементів.',
        'file' => 'Розмір файлу :attribute має бути більше :value кілобайтів.',
        'numeric' => 'Значення :attribute має бути більше :value.',
        'string' => 'Поле :attribute має містити більше :value символів.',
    ],
    'gte' => [
        'array' => 'Поле :attribute має містити :value або більше елементів.',
        'file' => 'Розмір файлу :attribute має бути не менше :value кілобайтів.',
        'numeric' => 'Значення :attribute має бути не менше :value.',
        'string' => 'Поле :attribute має містити не менше :value символів.',
    ],
    'hex_color' => 'Поле :attribute має містити коректний шістнадцятковий колір.',
    'image' => 'Поле :attribute має містити зображення.',
    'in' => 'Вибране значення поля :attribute недійсне.',
    'in_array' => 'Значення поля :attribute має бути присутнім у :other.',
    'in_array_keys' => 'Поле :attribute має містити щонайменше один із таких ключів: :values.',
    'integer' => 'Поле :attribute має бути цілим числом.',
    'ip' => 'Поле :attribute має бути дійсною IP-адресою.',
    'ipv4' => 'Поле :attribute має бути дійсною IPv4-адресою.',
    'ipv6' => 'Поле :attribute має бути дійсною IPv6-адресою.',
    'json' => 'Поле :attribute має бути коректним JSON-рядком.',
    'list' => 'Поле :attribute має бути списком.',
    'lowercase' => 'Поле :attribute має бути в нижньому регістрі.',
    'lt' => [
        'array' => 'Поле :attribute має містити менше ніж :value елементів.',
        'file' => 'Розмір файлу :attribute має бути менше :value кілобайтів.',
        'numeric' => 'Значення :attribute має бути менше :value.',
        'string' => 'Поле :attribute має містити менше :value символів.',
    ],
    'lte' => [
        'array' => 'Поле :attribute має містити не більше :value елементів.',
        'file' => 'Розмір файлу :attribute має бути не більше :value кілобайтів.',
        'numeric' => 'Значення :attribute має бути не більше :value.',
        'string' => 'Поле :attribute має містити не більше :value символів.',
    ],
    'mac_address' => 'Поле :attribute має бути дійсною MAC-адресою.',
    'max' => [
        'array' => 'Поле :attribute не може містити більше :max елементів.',
        'file' => 'Розмір файлу :attribute не може перевищувати :max кілобайтів.',
        'numeric' => 'Значення :attribute не може бути більше :max.',
        'string' => 'Поле :attribute не може містити більше :max символів.',
    ],
    'max_digits' => 'Поле :attribute не може містити більше :max цифр.',
    'mimes' => 'Файл :attribute має бути одного з таких типів: :values.',
    'mimetypes' => 'Файл :attribute має бути одного з таких типів: :values.',
    'min' => [
        'array' => 'Поле :attribute має містити щонайменше :min елементів.',
        'file' => 'Розмір файлу :attribute має бути щонайменше :min кілобайтів.',
        'numeric' => 'Значення :attribute має бути щонайменше :min.',
        'string' => 'Поле :attribute має містити щонайменше :min символів.',
    ],
    'min_digits' => 'Поле :attribute має містити щонайменше :min цифр.',
    'missing' => 'Поле :attribute має бути відсутнім.',
    'missing_if' => 'Поле :attribute має бути відсутнім, коли :other має значення :value.',
    'missing_unless' => 'Поле :attribute має бути відсутнім, якщо :other не має значення :value.',
    'missing_with' => 'Поле :attribute має бути відсутнім, коли присутнє :values.',
    'missing_with_all' => 'Поле :attribute має бути відсутнім, коли присутні всі значення :values.',
    'multiple_of' => 'Значення :attribute має бути кратним :value.',
    'not_in' => 'Вибране значення поля :attribute недійсне.',
    'not_regex' => 'Формат поля :attribute недійсний.',
    'numeric' => 'Поле :attribute має бути числом.',
    'password' => [
        'letters' => 'Поле :attribute має містити щонайменше одну літеру.',
        'mixed' => 'Поле :attribute має містити щонайменше одну велику та одну малу літеру.',
        'numbers' => 'Поле :attribute має містити щонайменше одну цифру.',
        'symbols' => 'Поле :attribute має містити щонайменше один символ.',
        'uncompromised' => 'Значення :attribute було знайдено у витоку даних. Виберіть інше значення для :attribute.',
    ],
    'present' => 'Поле :attribute має бути присутнім.',
    'present_if' => 'Поле :attribute має бути присутнім, коли :other має значення :value.',
    'present_unless' => 'Поле :attribute має бути присутнім, якщо :other не має значення :value.',
    'present_with' => 'Поле :attribute має бути присутнім, коли присутнє :values.',
    'present_with_all' => 'Поле :attribute має бути присутнім, коли присутні всі значення :values.',
    'prohibited' => 'Поле :attribute заборонене.',
    'prohibited_if' => 'Поле :attribute заборонене, коли :other має значення :value.',
    'prohibited_if_accepted' => 'Поле :attribute заборонене, якщо :other прийнято.',
    'prohibited_if_declined' => 'Поле :attribute заборонене, якщо :other відхилено.',
    'prohibited_unless' => 'Поле :attribute заборонене, якщо :other не міститься в :values.',
    'prohibits' => 'Поле :attribute забороняє присутність :other.',
    'regex' => 'Формат поля :attribute недійсний.',
    'required' => 'Поле :attribute обов’язкове.',
    'required_array_keys' => 'Поле :attribute має містити такі ключі: :values.',
    'required_if' => 'Поле :attribute обов’язкове, коли :other має значення :value.',
    'required_if_accepted' => 'Поле :attribute обов’язкове, коли :other прийнято.',
    'required_if_declined' => 'Поле :attribute обов’язкове, коли :other відхилено.',
    'required_unless' => 'Поле :attribute обов’язкове, якщо :other не міститься в :values.',
    'required_with' => 'Поле :attribute обов’язкове, коли присутнє :values.',
    'required_with_all' => 'Поле :attribute обов’язкове, коли присутні всі значення :values.',
    'required_without' => 'Поле :attribute обов’язкове, коли :values відсутнє.',
    'required_without_all' => 'Поле :attribute обов’язкове, коли всі значення :values відсутні.',
    'same' => 'Поля :attribute та :other мають збігатися.',
    'size' => [
        'array' => 'Поле :attribute має містити :size елементів.',
        'file' => 'Розмір файлу :attribute має дорівнювати :size кілобайтів.',
        'numeric' => 'Значення :attribute має дорівнювати :size.',
        'string' => 'Поле :attribute має містити :size символів.',
    ],
    'starts_with' => 'Поле :attribute має починатися з одного з таких значень: :values.',
    'string' => 'Поле :attribute має бути рядком.',
    'timezone' => 'Поле :attribute має бути дійсним часовим поясом.',
    'unique' => 'Таке значення поля :attribute вже існує.',
    'uploaded' => 'Не вдалося завантажити файл :attribute.',
    'uppercase' => 'Поле :attribute має бути у верхньому регістрі.',
    'url' => 'Поле :attribute має бути дійсною URL-адресою.',
    'ulid' => 'Поле :attribute має бути коректним ULID.',
    'uuid' => 'Поле :attribute має бути коректним UUID.',

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
            'required' => 'Потрібно вказати користувацьку ширину стовпця.',
            'regex' => 'Неприпустимий формат ширини стовпця. Використовуйте px, rem, em, %, або vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'Потрібно вказати режим ширини стовпця.',
            'in' => 'Вибраний режим ширини стовпця недійсний.',
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
        'username' => 'Ім’я користувача',
        'remember' => 'Запам’ятати мене',
        'work_name' => 'Назва японською',
        'metadata_language' => 'Мова метаданих DLsite',
        'localized_title' => 'Локалізована назва',
        'series' => 'Серія',
        'product_code_list' => 'Код твору або посилання',
        'product_codes' => 'Коди творів',
        'product_codes.*' => 'Код твору',
        'product_format' => 'Формат твору',
        'product_format.*' => 'Формат твору',
        'circle' => 'Circle',
        'publisher' => 'Видавець',
        'brand' => 'Бренд',
        'maker_id' => 'ID творця',
        'scenario' => 'Автор сценарію',
        'voice_actor' => 'Актор озвучення',
        'illustration' => 'Автор ілюстрацій',
        'author' => 'Автор',
        'genre_custom' => 'Користувацькі теги',
        'description' => 'Опис японською',
        'end_date' => 'Дата завершення',
        'add.start_date.year' => 'Рік початку',
        'add.start_date.month' => 'Місяць початку',
        'add.start_date.day' => 'День початку',
        'add.finish_date.year' => 'Рік завершення',
        'add.finish_date.month' => 'Місяць завершення',
        'add.finish_date.day' => 'День завершення',
        'num_re_listen_times' => 'Кількість повторних прослуховувань',
        'currentPassword' => 'Поточний пароль',
        'newPassword' => 'Новий пароль',
        'newPasswordConfirmation' => 'Підтвердження нового пароля',
        'followFetchedTagsLanguage' => 'Автоматично слідувати за мовою інтерфейсу',
        'enabled' => 'Стан увімкнення',
        'customValue' => 'Користувацьке значення',
        'newTagTitle' => 'Назва нового тегу',
        'editingTagTitle' => 'Назва тегу',
        'id' => 'Код твору',
        'product_code' => 'Код твору',
        'product_ids' => 'Твори',
        'product_ids.*' => 'Твір',
        'title' => 'Назва',
        'name' => 'Ім’я',
        'email' => 'Електронна адреса',
        'password' => 'Пароль',
        'password_confirmation' => 'Підтвердження пароля',
        'current_password' => 'Поточний пароль',
        'language' => 'Мова інтерфейсу',
        'progress' => 'Прогрес',
        'score' => 'Оцінка',
        'age_category' => 'Вікова категорія',
        'work_image' => 'Обкладинка',
        'sample_images' => 'Зразки зображень',
        'sample_images.*' => 'Зразок зображення',
        'sample_image_order' => 'Порядок зразків зображень',
        'description_japanese' => 'Опис японською',
        'description_localized' => 'Локалізований опис',
        'tags' => 'Теги',
        'notes' => 'Нотатки',
        'start_date' => 'Дата початку',
        'finish_date' => 'Дата завершення',
        're_listen_value' => 'Значення повторного прослуховування',
        'priority' => 'Пріоритет',
        'newGroupTitle' => 'Назва нової групи',
        'mode' => 'Режим',
        'theme' => 'Тема',
        'sizeChoice' => 'Максимальний розмір на ZIP',
        'customMib' => 'Максимальний розмір на ZIP (MiB)',
        'tagOrder' => 'Порядок підказок тегів',
        'seriesOrder' => 'Порядок підказок серій',
        'focus' => 'Формулювання інтерфейсу',
        'archive' => 'ZIP-файл',
    ],
];
