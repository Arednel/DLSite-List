<?php

// Laravel validation messages are based on the Russian translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/ru).
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

    'accepted' => 'Вы должны принять :attribute.',
    'accepted_if' => 'Вы должны принять :attribute, когда :other содержит :value.',
    'active_url' => 'Значение поля :attribute должно быть действительным URL адресом.',
    'after' => 'Значение поля :attribute должно быть датой после :date.',
    'after_or_equal' => 'Значение поля :attribute должно быть датой после или равной :date.',
    'alpha' => 'Значение поля :attribute может содержать только буквы.',
    'alpha_dash' => 'Значение поля :attribute может содержать только буквы, цифры, дефис и нижнее подчеркивание.',
    'alpha_num' => 'Значение поля :attribute может содержать только буквы и цифры.',
    'any_of' => 'Значение поля :attribute не найдено в списке разрешённых.',
    'array' => 'Значение поля :attribute должно быть массивом.',
    'ascii' => 'Значение поля :attribute должно содержать только однобайтовые цифро-буквенные символы.',
    'before' => 'Значение поля :attribute должно быть датой до :date.',
    'before_or_equal' => 'Значение поля :attribute должно быть датой до или равной :date.',
    'between' => [
        'array' => 'Количество элементов в поле :attribute должно быть от :min до :max.',
        'file' => 'Размер файла в поле :attribute должен быть от :min до :max Кб.',
        'numeric' => 'Значение поля :attribute должно быть от :min до :max.',
        'string' => 'Количество символов в поле :attribute должно быть от :min до :max.',
    ],
    'boolean' => 'Значение поля :attribute должно быть логического типа.',
    'can' => 'Значение поля :attribute должно быть авторизованным.',
    'confirmed' => 'Значение поля :attribute не совпадает с подтверждаемым.',
    'contains' => 'В поле :attribute отсутствует необходимое значение.',
    'current_password' => 'Неверный пароль.',
    'date' => 'Значение поля :attribute должно быть корректной датой.',
    'date_equals' => 'Значение поля :attribute должно быть датой равной :date.',
    'date_format' => 'Значение поля :attribute должно соответствовать формату даты: :format.',
    'decimal' => 'Значение поля :attribute должно содержать :decimal цифр десятичных разрядов.',
    'declined' => 'Значение поля :attribute должно быть отклонено.',
    'declined_if' => 'Значение поля :attribute должно быть отклонено, когда :other содержит :value.',
    'different' => 'Значения полей :attribute и :other должны различаться.',
    'digits' => 'Количество символов в поле :attribute должно быть равным :digits.',
    'digits_between' => 'Количество символов в поле :attribute должно быть от :min до :max.',
    'dimensions' => 'Изображение, указанное в поле :attribute, имеет недопустимые размеры.',
    'distinct' => 'Элементы в значении поля :attribute не должны повторяться.',
    'doesnt_contain' => 'Значение поля :attribute не должно содержать ни одного из следующих элементов: :values.',
    'doesnt_end_with' => 'Значение поля :attribute не должно заканчиваться одним из следующих: :values.',
    'doesnt_start_with' => 'Значение поля :attribute не должно начинаться с одного из следующих: :values.',
    'email' => 'Значение поля :attribute должно быть действительным электронным адресом.',
    'encoding' => 'Значение поля :attribute должно быть в кодировке :encoding.',
    'ends_with' => 'Значение поля :attribute должно заканчиваться одним из следующих: :values',
    'enum' => 'Значение поля :attribute отсутствует в списке разрешённых.',
    'exists' => 'Значение поля :attribute не существует.',
    'extensions' => 'Файл в поле :attribute должен иметь одно из следующих расширений: :values.',
    'file' => 'В поле :attribute должен быть указан файл.',
    'filled' => 'Значение поля :attribute обязательно для заполнения.',
    'gt' => [
        'array' => 'Количество элементов в поле :attribute должно быть больше :value.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть больше :value Кб.',
        'numeric' => 'Значение поля :attribute должно быть больше :value.',
        'string' => 'Количество символов в поле :attribute должно быть больше :value.',
    ],
    'gte' => [
        'array' => 'Количество элементов в поле :attribute должно быть :value или больше.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть :value Кб или больше.',
        'numeric' => 'Значение поля :attribute должно быть :value или больше.',
        'string' => 'Количество символов в поле :attribute должно быть :value или больше.',
    ],
    'hex_color' => 'Значение поля :attribute должно быть корректным цветом в HEX формате.',
    'image' => 'Файл, указанный в поле :attribute, должен быть изображением.',
    'in' => 'Значение поля :attribute отсутствует в списке разрешённых.',
    'in_array' => 'Значение поля :attribute должно быть указано в поле :other.',
    'in_array_keys' => 'Массив в значении поля :attribute должен иметь как минимум один из следующих ключей: :values.',
    'integer' => 'Значение поля :attribute должно быть целым числом.',
    'ip' => 'Значение поля :attribute должно быть действительным IP-адресом.',
    'ipv4' => 'Значение поля :attribute должно быть действительным IPv4-адресом.',
    'ipv6' => 'Значение поля :attribute должно быть действительным IPv6-адресом.',
    'json' => 'Значение поля :attribute должно быть JSON строкой.',
    'list' => 'Значение поля :attribute должно быть списком.',
    'lowercase' => 'Значение поля :attribute должно быть в нижнем регистре.',
    'lt' => [
        'array' => 'Количество элементов в поле :attribute должно быть меньше :value.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть меньше :value Кб.',
        'numeric' => 'Значение поля :attribute должно быть меньше :value.',
        'string' => 'Количество символов в поле :attribute должно быть меньше :value.',
    ],
    'lte' => [
        'array' => 'Количество элементов в поле :attribute должно быть :value или меньше.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть :value Кб или меньше.',
        'numeric' => 'Значение поля :attribute должно быть равным или меньше :value.',
        'string' => 'Количество символов в поле :attribute должно быть :value или меньше.',
    ],
    'mac_address' => 'Значение поля :attribute должно быть корректным MAC-адресом.',
    'max' => [
        'array' => 'Количество элементов в поле :attribute не может превышать :max.',
        'file' => 'Размер файла в поле :attribute не может быть больше :max Кб.',
        'numeric' => 'Значение поля :attribute не может быть больше :max.',
        'string' => 'Количество символов в значении поля :attribute не может превышать :max.',
    ],
    'max_digits' => 'Значение поля :attribute не должно содержать больше :max цифр.',
    'mimes' => 'Файл, указанный в поле :attribute, должен быть одного из следующих типов: :values.',
    'mimetypes' => 'Файл, указанный в поле :attribute, должен быть одного из следующих типов: :values.',
    'min' => [
        'array' => 'Количество элементов в поле :attribute должно быть не меньше :min.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть не меньше :min Кб.',
        'numeric' => 'Значение поля :attribute должно быть не меньше :min.',
        'string' => 'Количество символов в поле :attribute должно быть не меньше :min.',
    ],
    'min_digits' => 'Значение поля :attribute должно содержать не меньше :min цифр.',
    'missing' => 'Значение поля :attribute должно отсутствовать.',
    'missing_if' => 'Значение поля :attribute должно отсутствовать, когда :other содержит :value.',
    'missing_unless' => 'Значение поля :attribute должно отсутствовать, когда :other не содержит :value.',
    'missing_with' => 'Значение поля :attribute должно отсутствовать, если :values указано.',
    'missing_with_all' => 'Значение поля :attribute должно отсутствовать, когда указаны все :values.',
    'multiple_of' => 'Значение поля :attribute должно быть кратным :value.',
    'not_in' => 'Значение поля :attribute находится в списке запрета.',
    'not_regex' => 'Значение поля :attribute имеет некорректный формат.',
    'numeric' => 'Значение поля :attribute должно быть числом.',
    'password' => [
        'letters' => 'Значение поля :attribute должно содержать хотя бы одну букву.',
        'mixed' => 'Значение поля :attribute должно содержать хотя бы одну прописную и одну строчную буквы.',
        'numbers' => 'Значение поля :attribute должно содержать хотя бы одну цифру.',
        'symbols' => 'Значение поля :attribute должно содержать хотя бы один символ.',
        'uncompromised' => 'Значение поля :attribute обнаружено в утёкших данных. Пожалуйста, выберите другое значение для :attribute.',
    ],
    'present' => 'Значение поля :attribute должно присутствовать.',
    'present_if' => 'Значение поля :attribute должно присутствовать, когда :other содержит :value.',
    'present_unless' => 'Значение поля :attribute должно присутствовать, если только :other не содержит :value.',
    'present_with' => 'Значение поля :attribute должно присутствовать, когда указано :values.',
    'present_with_all' => 'Значение поля :attribute должно присутствовать, когда указаны все значения: :values.',
    'prohibited' => 'Значение поля :attribute запрещено.',
    'prohibited_if' => 'Значение поля :attribute запрещено, когда :other содержит :value.',
    'prohibited_if_accepted' => 'Значение поля :attribute запрещено, если принято :other.',
    'prohibited_if_declined' => 'Значение поля :attribute запрещено при отказе от :other.',
    'prohibited_unless' => 'Значение поля :attribute запрещено, если :other не состоит в :values.',
    'prohibits' => 'Значение поля :attribute запрещает присутствие :other.',
    'regex' => 'Значение поля :attribute имеет некорректный формат.',
    'required' => 'Поле :attribute обязательно.',
    'required_array_keys' => 'Массив, указанный в поле :attribute, обязательно должен иметь ключи: :values.',
    'required_if' => 'Поле :attribute обязательно для заполнения, когда :other содержит :value.',
    'required_if_accepted' => 'Поле :attribute обязательно, когда :other принято.',
    'required_if_declined' => 'Поле :attribute обязательно, когда :other отклонено.',
    'required_unless' => 'Поле :attribute обязательно для заполнения, когда :other не содержит :values.',
    'required_with' => 'Поле :attribute обязательно для заполнения, когда :values указано.',
    'required_with_all' => 'Поле :attribute обязательно для заполнения, когда :values указано.',
    'required_without' => 'Поле :attribute обязательно для заполнения, когда :values не указано.',
    'required_without_all' => 'Поле :attribute обязательно для заполнения, когда ни одно из :values не указано.',
    'same' => 'Значения полей :attribute и :other должны совпадать.',
    'size' => [
        'array' => 'Количество элементов в поле :attribute должно быть равным :size.',
        'file' => 'Размер файла, указанный в поле :attribute, должен быть равен :size Кб.',
        'numeric' => 'Значение поля :attribute должно быть равным :size.',
        'string' => 'Количество символов в поле :attribute должно быть равным :size.',
    ],
    'starts_with' => 'Поле :attribute должно начинаться с одного из следующих значений: :values.',
    'string' => 'Значение поля :attribute должно быть строкой.',
    'timezone' => 'Значение поля :attribute должно быть действительным часовым поясом.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'uploaded' => 'Загрузка файла из поля :attribute не удалась.',
    'uppercase' => 'Значение поля :attribute должно быть в верхнем регистре.',
    'url' => 'Значение поля :attribute не является ссылкой или имеет некорректный формат.',
    'ulid' => 'Значение поля :attribute должно быть корректным ULID.',
    'uuid' => 'Значение поля :attribute должно быть корректным UUID.',

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
            'required' => 'Укажите ширину столбца.',
            'regex' => 'Укажите ширину столбца в px, rem, em, % или vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'Необходимо указать режим ширины столбца.',
            'in' => 'Выбранный режим ширины столбца недопустим.',
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
        'username' => 'Имя пользователя',
        'remember' => 'Запомнить меня',
        'work_name' => 'Название на японском',
        'metadata_language' => 'Язык метаданных DLsite',
        'localized_title' => 'Локализованное название',
        'series' => 'Серия',
        'product_code_list' => 'Код произведения или ссылка',
        'product_codes' => 'Коды произведений',
        'product_codes.*' => 'Код произведения',
        'product_format' => 'Формат произведения',
        'product_format.*' => 'Формат произведения',
        'circle' => 'Кружок',
        'publisher' => 'Издатель',
        'brand' => 'Бренд',
        'maker_id' => 'ID создателя',
        'scenario' => 'Автор сценария',
        'voice_actor' => 'Актёр озвучки',
        'illustration' => 'Автор иллюстраций',
        'author' => 'Автор',
        'genre_custom' => 'Пользовательские теги',
        'description' => 'Описание на японском',
        'end_date' => 'Дата окончания',
        'add.start_date.year' => 'Год начала',
        'add.start_date.month' => 'Месяц начала',
        'add.start_date.day' => 'День начала',
        'add.finish_date.year' => 'Год окончания',
        'add.finish_date.month' => 'Месяц окончания',
        'add.finish_date.day' => 'День окончания',
        'num_re_listen_times' => 'Количество повторных прослушиваний',
        'currentPassword' => 'Текущий пароль',
        'newPassword' => 'Новый пароль',
        'newPasswordConfirmation' => 'Подтверждение нового пароля',
        'followFetchedTagsLanguage' => 'Выбирать язык метаданных по языку интерфейса',
        'enabled' => 'Состояние включения',
        'customValue' => 'Пользовательское значение',
        'newTagTitle' => 'Название нового тега',
        'editingTagTitle' => 'Название тега',
        'id' => 'Код произведения',
        'product_code' => 'Код произведения',
        'product_ids' => 'Произведения',
        'product_ids.*' => 'Произведение',
        'title' => 'Название',
        'name' => 'Имя',
        'email' => 'Адрес электронной почты',
        'password' => 'Пароль',
        'password_confirmation' => 'Подтверждение пароля',
        'current_password' => 'Текущий пароль',
        'language' => 'Язык интерфейса',
        'progress' => 'Прогресс',
        'score' => 'Оценка',
        'age_category' => 'Возрастная категория',
        'work_image' => 'Обложка',
        'sample_images' => 'Примеры изображений',
        'sample_images.*' => 'Пример изображения',
        'sample_image_order' => 'Порядок примеров изображений',
        'description_japanese' => 'Описание на японском',
        'description_localized' => 'Локализованное описание',
        'tags' => 'Теги',
        'notes' => 'Заметки',
        'start_date' => 'Дата начала',
        'finish_date' => 'Дата окончания',
        're_listen_value' => 'Желание переслушать',
        'priority' => 'Приоритет',
        'newGroupTitle' => 'Название новой группы',
        'mode' => 'Режим',
        'theme' => 'Тема',
        'sizeChoice' => 'Максимальный размер одного ZIP',
        'customMib' => 'Максимальный размер одного ZIP (MiB)',
        'tagOrder' => 'Порядок подсказок тегов',
        'seriesOrder' => 'Порядок подсказок серий',
        'focus' => 'Формулировки интерфейса',
        'archive' => 'ZIP-файл',
    ],
];
