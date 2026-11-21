<?php

// Laravel validation messages are based on the Polish translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/pl).
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

    'accepted' => 'Pole :attribute musi zostać zaakceptowane.',
    'accepted_if' => 'Pole :attribute musi zostać zaakceptowane, gdy :other ma wartość :value.',
    'active_url' => 'Pole :attribute jest nieprawidłowym adresem URL.',
    'after' => 'Pole :attribute musi być datą późniejszą od :date.',
    'after_or_equal' => 'Pole :attribute musi być datą nie wcześniejszą niż :date.',
    'alpha' => 'Pole :attribute może zawierać jedynie litery.',
    'alpha_dash' => 'Pole :attribute może zawierać jedynie litery, cyfry, myślniki i podkreślenia.',
    'alpha_num' => 'Pole :attribute może zawierać jedynie litery i cyfry.',
    'any_of' => 'Pole :attribute ma niepoprawną wartość.',
    'array' => 'Pole :attribute musi być tablicą.',
    'ascii' => 'Pole :attribute może zawierać tylko jednobajtowe znaki alfanumeryczne i symbole.',
    'before' => 'Pole :attribute musi być datą wcześniejszą od :date.',
    'before_or_equal' => 'Pole :attribute musi być datą nie późniejszą niż :date.',
    'between' => [
        'array' => 'Pole :attribute musi składać się z :min - :max elementów.',
        'file' => 'Pole :attribute musi zawierać się w granicach :min - :max kilobajtów.',
        'numeric' => 'Pole :attribute musi zawierać się w granicach :min - :max.',
        'string' => 'Pole :attribute musi zawierać się w granicach :min - :max znaków.',
    ],
    'boolean' => 'Pole :attribute musi mieć wartość logiczną prawda albo fałsz.',
    'can' => 'Pole :attribute zawiera nieautoryzowaną wartość.',
    'confirmed' => 'Potwierdzenie pola :attribute nie zgadza się.',
    'contains' => 'Pole :attribute nie zawiera wymaganej wartości.',
    'current_password' => 'Hasło jest nieprawidłowe.',
    'date' => 'Pole :attribute nie jest prawidłową datą.',
    'date_equals' => 'Pole :attribute musi być datą równą :date.',
    'date_format' => 'Pole :attribute nie jest w formacie :format.',
    'decimal' => 'Pole :attribute musi mieć :decimal miejsc po przecinku.',
    'declined' => 'Pole :attribute musi zostać odrzucone.',
    'declined_if' => 'Pole :attribute musi zostać odrzucone, gdy :other ma wartość :value.',
    'different' => 'Pole :attribute oraz :other muszą się różnić.',
    'digits' => 'Pole :attribute musi składać się z :digits cyfr.',
    'digits_between' => 'Pole :attribute musi mieć od :min do :max cyfr.',
    'dimensions' => 'Pole :attribute ma niepoprawne wymiary obrazu.',
    'distinct' => 'Pole :attribute ma zduplikowaną wartość.',
    'doesnt_contain' => 'Pole :attribute nie może zawierać żadnej z następujących wartości: :values.',
    'doesnt_end_with' => 'Pole :attribute nie może kończyć się jedną z następujących wartości: :values.',
    'doesnt_start_with' => 'Pole :attribute nie może zaczynać się od jednej z następujących wartości: :values.',
    'email' => 'Pole :attribute nie jest poprawnym adresem e-mail.',
    'encoding' => 'Pole :attribute musi być zakodowane jako :encoding.',
    'ends_with' => 'Pole :attribute musi kończyć się jedną z następujących wartości: :values.',
    'enum' => 'Pole :attribute ma niepoprawną wartość.',
    'exists' => 'Zaznaczone pole :attribute jest nieprawidłowe.',
    'extensions' => 'Pole :attribute musi mieć jedno z następujących rozszerzeń: :values.',
    'file' => 'Pole :attribute musi być plikiem.',
    'filled' => 'Pole :attribute musi być wypełnione.',
    'gt' => [
        'array' => 'Pole :attribute musi mieć więcej niż :value elementów.',
        'file' => 'Pole :attribute musi być większe niż :value kilobajtów.',
        'numeric' => 'Pole :attribute musi być większe niż :value.',
        'string' => 'Pole :attribute musi być dłuższe niż :value znaków.',
    ],
    'gte' => [
        'array' => 'Pole :attribute musi mieć :value lub więcej elementów.',
        'file' => 'Pole :attribute musi być większe lub równe :value kilobajtów.',
        'numeric' => 'Pole :attribute musi być większe lub równe :value.',
        'string' => 'Pole :attribute musi być dłuższe lub równe :value znaków.',
    ],
    'hex_color' => 'Pole :attribute musi zawierać prawidłowy kolor szesnastkowy.',
    'image' => 'Pole :attribute musi być obrazem.',
    'in' => 'Zaznaczony element :attribute jest nieprawidłowy.',
    'in_array' => 'Pole :attribute nie znajduje się w :other.',
    'in_array_keys' => 'Pole :attribute musi zawierać co najmniej jeden z następujących kluczy: :values.',
    'integer' => 'Pole :attribute musi być liczbą całkowitą.',
    'ip' => 'Pole :attribute musi być prawidłowym adresem IP.',
    'ipv4' => 'Pole :attribute musi być prawidłowym adresem IPv4.',
    'ipv6' => 'Pole :attribute musi być prawidłowym adresem IPv6.',
    'json' => 'Pole :attribute musi być poprawnym ciągiem znaków JSON.',
    'list' => 'Pole :attribute musi być listą.',
    'lowercase' => 'Pole :attribute musi być zapisane małymi literami.',
    'lt' => [
        'array' => 'Pole :attribute musi mieć mniej niż :value elementów.',
        'file' => 'Pole :attribute musi być mniejsze niż :value kilobajtów.',
        'numeric' => 'Pole :attribute musi być mniejsze niż :value.',
        'string' => 'Pole :attribute musi być krótsze niż :value znaków.',
    ],
    'lte' => [
        'array' => 'Pole :attribute musi mieć :value lub mniej elementów.',
        'file' => 'Pole :attribute musi być mniejsze lub równe :value kilobajtów.',
        'numeric' => 'Pole :attribute musi być mniejsze lub równe :value.',
        'string' => 'Pole :attribute musi być krótsze lub równe :value znaków.',
    ],
    'mac_address' => 'Pole :attribute musi być prawidłowym adresem MAC.',
    'max' => [
        'array' => 'Pole :attribute nie może mieć więcej niż :max elementów.',
        'file' => 'Pole :attribute nie może być większe niż :max kilobajtów.',
        'numeric' => 'Pole :attribute nie może być większe niż :max.',
        'string' => 'Pole :attribute nie może być dłuższe niż :max znaków.',
    ],
    'max_digits' => 'Pole :attribute nie może mieć więcej niż :max cyfr.',
    'mimes' => 'Pole :attribute musi być plikiem typu :values.',
    'mimetypes' => 'Pole :attribute musi być plikiem typu :values.',
    'min' => [
        'array' => 'Pole :attribute musi mieć przynajmniej :min elementów.',
        'file' => 'Pole :attribute musi mieć przynajmniej :min kilobajtów.',
        'numeric' => 'Pole :attribute musi być nie mniejsze od :min.',
        'string' => 'Pole :attribute musi mieć przynajmniej :min znaków.',
    ],
    'min_digits' => 'Pole :attribute musi mieć co najmniej :min cyfr.',
    'missing' => 'Pole :attribute musi być nieobecne.',
    'missing_if' => 'Pole :attribute musi być nieobecne, gdy :other ma wartość :value.',
    'missing_unless' => 'Pole :attribute musi być nieobecne, chyba że :other ma wartość :value.',
    'missing_with' => 'Pole :attribute musi być nieobecne, gdy występuje :values.',
    'missing_with_all' => 'Pole :attribute musi być nieobecne, gdy występują wszystkie wartości :values.',
    'multiple_of' => 'Pole :attribute musi być wielokrotnością wartości :value.',
    'not_in' => 'Zaznaczony :attribute jest nieprawidłowy.',
    'not_regex' => 'Format pola :attribute jest nieprawidłowy.',
    'numeric' => 'Pole :attribute musi być liczbą.',
    'password' => [
        'letters' => 'Pole :attribute musi zawierać przynajmniej jedną literę.',
        'mixed' => 'Pole :attribute musi zawierać przynajmniej jedną wielką i jedną małą literę.',
        'numbers' => 'Pole :attribute musi zawierać przynajmniej jedną cyfrę.',
        'symbols' => 'Pole :attribute musi zawierać przynajmniej jeden symbol.',
        'uncompromised' => 'Podany :attribute pojawił się w wycieku danych. Proszę wybrać inną wartość :attribute.',
    ],
    'present' => 'Pole :attribute musi być obecne.',
    'present_if' => 'Pole :attribute musi być obecne, gdy :other ma wartość :value.',
    'present_unless' => 'Pole :attribute musi być obecne, chyba że :other ma wartość :value.',
    'present_with' => 'Pole :attribute musi być obecne, gdy :values jest podane.',
    'present_with_all' => 'Pole :attribute musi być obecne, gdy wszystkie wartości :values są podane.',
    'prohibited' => 'Pole :attribute jest zabronione.',
    'prohibited_if' => 'Pole :attribute jest zabronione, gdy :other ma wartość :value.',
    'prohibited_if_accepted' => 'Pole :attribute jest zabronione, gdy :other jest zaakceptowane.',
    'prohibited_if_declined' => 'Pole :attribute jest zabronione, gdy :other jest odrzucone.',
    'prohibited_unless' => 'Pole :attribute jest zabronione, chyba że :other znajduje się w :values.',
    'prohibits' => 'Pole :attribute wyklucza obecność :other.',
    'regex' => 'Format pola :attribute jest nieprawidłowy.',
    'required' => 'Pole :attribute jest wymagane.',
    'required_array_keys' => 'Pole :attribute musi zawierać klucze: :values.',
    'required_if' => 'Pole :attribute jest wymagane, gdy :other ma wartość :value.',
    'required_if_accepted' => 'Pole :attribute jest wymagane, gdy :other jest zaakceptowane.',
    'required_if_declined' => 'Pole :attribute jest wymagane, gdy :other jest odrzucone.',
    'required_unless' => 'Pole :attribute jest wymagane, jeżeli :other nie znajduje się w :values.',
    'required_with' => 'Pole :attribute jest wymagane, gdy wartość :values jest obecna.',
    'required_with_all' => 'Pole :attribute jest wymagane, gdy wszystkie wartości :values są obecne.',
    'required_without' => 'Pole :attribute jest wymagane, gdy wartość :values nie jest obecna.',
    'required_without_all' => 'Pole :attribute jest wymagane, gdy żadna z wartości :values nie jest obecna.',
    'same' => 'Pole :attribute i :other muszą być takie same.',
    'size' => [
        'array' => 'Pole :attribute musi zawierać :size elementów.',
        'file' => 'Pole :attribute musi mieć :size kilobajtów.',
        'numeric' => 'Pole :attribute musi mieć wartość :size.',
        'string' => 'Pole :attribute musi mieć :size znaków.',
    ],
    'starts_with' => 'Pole :attribute musi zaczynać się jedną z następujących wartości: :values.',
    'string' => 'Pole :attribute musi być ciągiem znaków.',
    'timezone' => 'Pole :attribute musi być prawidłową strefą czasową.',
    'unique' => 'Taki :attribute już występuje.',
    'uploaded' => 'Nie udało się wgrać pliku :attribute.',
    'uppercase' => 'Pole :attribute musi być zapisane wielkimi literami.',
    'url' => 'Format pola :attribute jest nieprawidłowy.',
    'ulid' => 'Pole :attribute musi być prawidłowym identyfikatorem ULID.',
    'uuid' => 'Pole :attribute musi być poprawnym identyfikatorem UUID.',

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
            'required' => 'Niestandardowa szerokość kolumny jest wymagana.',
            'regex' => 'Nieprawidłowy format szerokości kolumny. Użyj px, rem, em, %, lub vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'Tryb szerokości kolumny jest wymagany.',
            'in' => 'Wybrany tryb szerokości kolumny jest nieprawidłowy.',
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
        'username' => 'Nazwa użytkownika',
        'remember' => 'Zapamiętaj mnie',
        'work_name' => 'Tytuł japoński',
        'metadata_language' => 'Język metadanych DLsite',
        'localized_title' => 'Zlokalizowany tytuł',
        'series' => 'Seria',
        'product_code_list' => 'Kod produktu lub link',
        'product_codes' => 'Kody produktów',
        'product_codes.*' => 'Kod produktu',
        'product_format' => 'Format produktu',
        'product_format.*' => 'Format produktu',
        'circle' => 'Circle',
        'publisher' => 'Wydawca',
        'brand' => 'Marka',
        'maker_id' => 'ID twórcy',
        'scenario' => 'Autor scenariusza',
        'voice_actor' => 'Aktor głosowy',
        'illustration' => 'Autor ilustracji',
        'author' => 'Autor',
        'genre_custom' => 'Niestandardowe tagi',
        'description' => 'Japoński opis',
        'end_date' => 'Data zakończenia',
        'add.start_date.year' => 'Rok rozpoczęcia',
        'add.start_date.month' => 'Miesiąc rozpoczęcia',
        'add.start_date.day' => 'Dzień rozpoczęcia',
        'add.finish_date.year' => 'Rok zakończenia',
        'add.finish_date.month' => 'Miesiąc zakończenia',
        'add.finish_date.day' => 'Dzień zakończenia',
        'num_re_listen_times' => 'Liczba ponownych odsłuchań',
        'currentPassword' => 'Obecne hasło',
        'newPassword' => 'Nowe hasło',
        'newPasswordConfirmation' => 'Potwierdzenie nowego hasła',
        'followFetchedTagsLanguage' => 'Automatycznie podążaj za językiem interfejsu',
        'enabled' => 'Stan włączenia',
        'customValue' => 'Wartość niestandardowa',
        'newTagTitle' => 'Nowy tytuł tagu',
        'editingTagTitle' => 'Tytuł tagu',
        'id' => 'Kod produktu',
        'product_code' => 'Kod produktu',
        'product_ids' => 'Dzieła',
        'product_ids.*' => 'Dzieło',
        'title' => 'Tytuł',
        'name' => 'Nazwa',
        'email' => 'Adres e-mail',
        'password' => 'Hasło',
        'password_confirmation' => 'Potwierdzenie hasła',
        'current_password' => 'Obecne hasło',
        'language' => 'Język interfejsu',
        'progress' => 'Postęp',
        'score' => 'Ocena',
        'age_category' => 'Kategoria wiekowa',
        'work_image' => 'Okładka',
        'sample_images' => 'Przykładowe obrazy',
        'sample_images.*' => 'Przykładowy obraz',
        'sample_image_order' => 'Kolejność przykładowych obrazów',
        'description_japanese' => 'Opis japoński',
        'description_localized' => 'Zlokalizowany opis',
        'tags' => 'Tagi',
        'notes' => 'Notatki',
        'start_date' => 'Data rozpoczęcia',
        'finish_date' => 'Data zakończenia',
        're_listen_value' => 'Wartość ponownego odsłuchania',
        'priority' => 'Priorytet',
        'newGroupTitle' => 'Nowy tytuł grupy',
        'mode' => 'Tryb',
        'theme' => 'Motyw',
        'sizeChoice' => 'Maksymalny rozmiar na ZIP',
        'customMib' => 'Maksymalny rozmiar na ZIP (MiB)',
        'tagOrder' => 'Kolejność podpowiedzi tagów',
        'seriesOrder' => 'Kolejność podpowiedzi serii',
        'focus' => 'Sformułowania interfejsu',
        'archive' => 'Plik ZIP',
    ],
];
