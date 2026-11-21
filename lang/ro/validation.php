<?php

// Laravel validation messages are based on the Romanian translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/ro).
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

    'accepted' => 'Câmpul :attribute trebuie să fie acceptat.',
    'accepted_if' => 'Câmpul :attribute trebuie să fie acceptat când :other este :value.',
    'active_url' => 'Câmpul :attribute nu este un URL valid.',
    'after' => 'Câmpul :attribute trebuie să fie o dată după :date.',
    'after_or_equal' => 'Câmpul :attribute trebuie să fie o dată ulterioară sau egală cu :date.',
    'alpha' => 'Câmpul :attribute poate conține doar litere.',
    'alpha_dash' => 'Câmpul :attribute poate conține doar litere, numere, cratime și liniuțe de subliniere.',
    'alpha_num' => 'Câmpul :attribute poate conține doar litere și numere.',
    'any_of' => 'Câmpul :attribute este nevalid.',
    'array' => 'Câmpul :attribute trebuie să fie un array.',
    'ascii' => 'Câmpul :attribute trebuie să conțină doar caractere și simboluri alfanumerice pe un singur octet.',
    'before' => 'Câmpul :attribute trebuie să fie o dată înainte de :date.',
    'before_or_equal' => 'Câmpul :attribute trebuie să fie o dată înainte sau egală cu :date.',
    'between' => [
        'array' => 'Câmpul :attribute trebuie să aibă între :min și :max elemente.',
        'file' => 'Câmpul :attribute trebuie să fie între :min și :max kilobyți.',
        'numeric' => 'Câmpul :attribute trebuie să fie între :min și :max.',
        'string' => 'Câmpul :attribute trebuie să fie între :min și :max caractere.',
    ],
    'boolean' => 'Câmpul :attribute trebuie să fie adevărat sau fals.',
    'can' => 'Câmpul :attribute conține o valoare neautorizată.',
    'confirmed' => 'Confirmarea :attribute nu se potrivește.',
    'contains' => 'Câmpului :attribute îi lipsește o valoare obligatorie.',
    'current_password' => 'Parola este incorectă.',
    'date' => 'Câmpul :attribute nu este o dată validă.',
    'date_equals' => 'Câmpul :attribute trebuie să fie o dată egală cu :date.',
    'date_format' => 'Câmpul :attribute trebuie să fie în formatul :format.',
    'decimal' => 'Câmpul :attribute trebuie să aibă :decimal zecimale.',
    'declined' => 'Câmpul :attribute trebuie să fie refuzat.',
    'declined_if' => 'Câmpul :attribute trebuie să fie refuzat când :other este :value.',
    'different' => 'Câmpurile :attribute și :other trebuie să fie diferite.',
    'digits' => 'Câmpul :attribute trebuie să aibă :digits cifre.',
    'digits_between' => 'Câmpul :attribute trebuie să aibă între :min și :max cifre.',
    'dimensions' => 'Câmpul :attribute are dimensiuni de imagine nevalide.',
    'distinct' => 'Câmpul :attribute are o valoare duplicată.',
    'doesnt_contain' => 'Câmpul :attribute nu trebuie să conțină niciuna dintre următoarele valori: :values.',
    'doesnt_end_with' => 'Câmpul :attribute nu trebuie să se termine cu una dintre următoarele valori: :values.',
    'doesnt_start_with' => 'Câmpul :attribute nu trebuie să înceapă cu una dintre următoarele valori: :values.',
    'email' => 'Câmpul :attribute trebuie să fie o adresă de e-mail validă.',
    'encoding' => 'Câmpul :attribute trebuie să fie codificat în :encoding.',
    'ends_with' => 'Câmpul :attribute trebuie să se încheie cu una din următoarele valori: :values.',
    'enum' => 'Câmpul :attribute selectat nu este valid.',
    'exists' => 'Câmpul :attribute selectat nu este valid.',
    'extensions' => 'Câmpul :attribute trebuie să aibă una dintre următoarele extensii: :values.',
    'file' => 'Câmpul :attribute trebuie să fie un fișier.',
    'filled' => 'Câmpul :attribute trebuie să aibă o valoare.',
    'gt' => [
        'array' => 'Câmpul :attribute trebuie să aibă mai mult de :value elemente.',
        'file' => 'Câmpul :attribute trebuie să fie mai mare de :value kilobyți.',
        'numeric' => 'Câmpul :attribute trebuie să fie mai mare de :value.',
        'string' => 'Câmpul :attribute trebuie să aibă mai mult de :value caractere.',
    ],
    'gte' => [
        'array' => 'Câmpul :attribute trebuie să aibă :value sau mai multe elemente.',
        'file' => 'Câmpul :attribute trebuie să fie mai mare sau egal cu :value kilobyți.',
        'numeric' => 'Câmpul :attribute trebuie să fie mai mare sau egal cu :value.',
        'string' => 'Câmpul :attribute trebuie să aibă cel puțin :value caractere.',
    ],
    'hex_color' => 'Câmpul :attribute trebuie să fie o culoare hexazecimală validă.',
    'image' => 'Câmpul :attribute trebuie să fie o imagine.',
    'in' => 'Câmpul :attribute selectat nu este valid.',
    'in_array' => 'Câmpul :attribute trebuie să existe în :other.',
    'in_array_keys' => 'Câmpul :attribute trebuie să conțină cel puțin una dintre următoarele chei: :values.',
    'integer' => 'Câmpul :attribute trebuie să fie un număr întreg.',
    'ip' => 'Câmpul :attribute trebuie să fie o adresă IP validă.',
    'ipv4' => 'Câmpul :attribute trebuie să fie o adresă IPv4 validă.',
    'ipv6' => 'Câmpul :attribute trebuie să fie o adresă IPv6 validă.',
    'json' => 'Câmpul :attribute trebuie să fie un șir JSON valid.',
    'list' => 'Câmpul :attribute trebuie să fie o listă.',
    'lowercase' => 'Câmpul :attribute trebuie să fie scris cu litere mici.',
    'lt' => [
        'array' => 'Câmpul :attribute trebuie să aibă mai puțin de :value elemente.',
        'file' => 'Câmpul :attribute trebuie să fie mai mic de :value kilobyți.',
        'numeric' => 'Câmpul :attribute trebuie să fie mai mic de :value.',
        'string' => 'Câmpul :attribute trebuie să aibă mai puțin de :value caractere.',
    ],
    'lte' => [
        'array' => 'Câmpul :attribute trebuie să aibă cel mult :value elemente.',
        'file' => 'Câmpul :attribute trebuie să fie mai mic sau egal cu :value kilobyți.',
        'numeric' => 'Câmpul :attribute trebuie să fie mai mic sau egal cu :value.',
        'string' => 'Câmpul :attribute trebuie să aibă cel mult :value caractere.',
    ],
    'mac_address' => 'Câmpul :attribute trebuie să fie o adresă MAC validă.',
    'max' => [
        'array' => 'Câmpul :attribute nu poate avea mai mult de :max elemente.',
        'file' => 'Câmpul :attribute nu poate fi mai mare de :max kilobyți.',
        'numeric' => 'Câmpul :attribute nu poate fi mai mare de :max.',
        'string' => 'Câmpul :attribute nu poate avea mai mult de :max caractere.',
    ],
    'max_digits' => 'Câmpul :attribute nu poate avea mai mult de :max cifre.',
    'mimes' => 'Câmpul :attribute trebuie să fie un fișier de tipul: :values.',
    'mimetypes' => 'Câmpul :attribute trebuie să fie un fișier de tipul: :values.',
    'min' => [
        'array' => 'Câmpul :attribute trebuie să aibă cel puțin :min elemente.',
        'file' => 'Câmpul :attribute trebuie să aibă cel puțin :min kilobyți.',
        'numeric' => 'Câmpul :attribute trebuie să fie cel puțin :min.',
        'string' => 'Câmpul :attribute trebuie să aibă cel puțin :min caractere.',
    ],
    'min_digits' => 'Câmpul :attribute trebuie să aibă cel puțin :min cifre.',
    'missing' => 'Câmpul :attribute trebuie să lipsească.',
    'missing_if' => 'Câmpul :attribute trebuie să lipsească atunci când :other este :value.',
    'missing_unless' => 'Câmpul :attribute trebuie să lipsească dacă :other nu este :value.',
    'missing_with' => 'Câmpul :attribute trebuie să lipsească atunci când :values este prezent.',
    'missing_with_all' => 'Câmpul :attribute trebuie să lipsească atunci când toate valorile :values sunt prezente.',
    'multiple_of' => 'Câmpul :attribute trebuie să fie multiplu de :value.',
    'not_in' => 'Câmpul :attribute selectat nu este valid.',
    'not_regex' => 'Formatul câmpului :attribute nu este valid.',
    'numeric' => 'Câmpul :attribute trebuie să fie un număr.',
    'password' => [
        'letters' => 'Câmpul :attribute trebuie să conțină cel puțin o literă.',
        'mixed' => 'Câmpul :attribute trebuie să conțină cel puțin o literă mare și una mică.',
        'numbers' => 'Câmpul :attribute trebuie să conțină cel puțin o cifră.',
        'symbols' => 'Câmpul :attribute trebuie să conțină cel puțin un simbol.',
        'uncompromised' => 'Câmpul :attribute a apărut într-o scurgere de date. Alegeți o altă valoare pentru :attribute.',
    ],
    'present' => 'Câmpul :attribute trebuie să fie prezent.',
    'present_if' => 'Câmpul :attribute trebuie să fie prezent atunci când :other este :value.',
    'present_unless' => 'Câmpul :attribute trebuie să fie prezent dacă :other nu este :value.',
    'present_with' => 'Câmpul :attribute trebuie să fie prezent atunci când :values este prezent.',
    'present_with_all' => 'Câmpul :attribute trebuie să fie prezent atunci când toate valorile :values sunt prezente.',
    'prohibited' => 'Câmpul :attribute este interzis.',
    'prohibited_if' => 'Câmpul :attribute este interzis atunci când :other este :value.',
    'prohibited_if_accepted' => 'Câmpul :attribute este interzis atunci când :other este acceptat.',
    'prohibited_if_declined' => 'Câmpul :attribute este interzis atunci când :other este refuzat.',
    'prohibited_unless' => 'Câmpul :attribute este interzis dacă :other nu se află în :values.',
    'prohibits' => 'Câmpul :attribute interzice prezența câmpului :other.',
    'regex' => 'Formatul câmpului :attribute nu este valid.',
    'required' => 'Câmpul :attribute este obligatoriu.',
    'required_array_keys' => 'Câmpul :attribute trebuie să conțină cheile: :values.',
    'required_if' => 'Câmpul :attribute este obligatoriu atunci când :other este :value.',
    'required_if_accepted' => 'Câmpul :attribute este obligatoriu atunci când :other este acceptat.',
    'required_if_declined' => 'Câmpul :attribute este obligatoriu atunci când :other este refuzat.',
    'required_unless' => 'Câmpul :attribute este obligatoriu dacă :other nu se află în :values.',
    'required_with' => 'Câmpul :attribute este obligatoriu atunci când :values este prezent.',
    'required_with_all' => 'Câmpul :attribute este obligatoriu atunci când toate valorile :values sunt prezente.',
    'required_without' => 'Câmpul :attribute este obligatoriu atunci când :values nu este prezent.',
    'required_without_all' => 'Câmpul :attribute este obligatoriu atunci când niciuna dintre valorile :values nu este prezentă.',
    'same' => 'Câmpurile :attribute și :other trebuie să fie identice.',
    'size' => [
        'array' => 'Câmpul :attribute trebuie să conțină :size elemente.',
        'file' => 'Câmpul :attribute trebuie să aibă :size kilobyți.',
        'numeric' => 'Câmpul :attribute trebuie să fie :size.',
        'string' => 'Câmpul :attribute trebuie să aibă :size caractere.',
    ],
    'starts_with' => 'Câmpul :attribute trebuie să înceapă cu una dintre următoarele valori: :values.',
    'string' => 'Câmpul :attribute trebuie să fie un șir de caractere.',
    'timezone' => 'Câmpul :attribute trebuie să fie un fus orar valid.',
    'unique' => 'Valoarea câmpului :attribute este deja folosită.',
    'uploaded' => 'Încărcarea câmpului :attribute a eșuat.',
    'uppercase' => 'Câmpul :attribute trebuie să fie scris cu majuscule.',
    'url' => 'Câmpul :attribute trebuie să fie un URL valid.',
    'ulid' => 'Câmpul :attribute trebuie să fie un ULID valid.',
    'uuid' => 'Câmpul :attribute trebuie să fie un UUID valid.',

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
            'required' => 'Lățimea personalizată a coloanei este obligatorie.',
            'regex' => 'Format invalid pentru lățimea coloanei. Folosiți px, rem, em, %, sau vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'Modul lățimii coloanei este obligatoriu.',
            'in' => 'Modul selectat pentru lățimea coloanei nu este valid.',
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
        'username' => 'Nume de utilizator',
        'remember' => 'Ține-mă autentificat',
        'work_name' => 'Titlu japonez',
        'metadata_language' => 'Limba metadatelor DLsite',
        'localized_title' => 'Titlu localizat',
        'series' => 'Serie',
        'product_code_list' => 'Cod produs sau link',
        'product_codes' => 'Coduri produse',
        'product_codes.*' => 'Cod produs',
        'product_format' => 'Format produs',
        'product_format.*' => 'Format produs',
        'circle' => 'Circle',
        'publisher' => 'Editor',
        'brand' => 'Marcă',
        'maker_id' => 'ID creator',
        'scenario' => 'Autor scenariu',
        'voice_actor' => 'Actor vocal',
        'illustration' => 'Autor ilustrații',
        'author' => 'Autor',
        'genre_custom' => 'Etichete personalizate',
        'description' => 'Descriere japoneză',
        'end_date' => 'Data de finalizare',
        'add.start_date.year' => 'An început',
        'add.start_date.month' => 'Lună început',
        'add.start_date.day' => 'Zi început',
        'add.finish_date.year' => 'Anul finalizării',
        'add.finish_date.month' => 'Luna finalizării',
        'add.finish_date.day' => 'Ziua finalizării',
        'num_re_listen_times' => 'Număr de reascultări',
        'currentPassword' => 'Parola curentă',
        'newPassword' => 'Parolă nouă',
        'newPasswordConfirmation' => 'Confirmarea parolei noi',
        'followFetchedTagsLanguage' => 'Urmează automat limba interfeței',
        'enabled' => 'Stare activată',
        'customValue' => 'Valoare personalizată',
        'newTagTitle' => 'Titlu etichetă nouă',
        'editingTagTitle' => 'Titlu etichetă',
        'id' => 'Cod produs',
        'product_code' => 'Cod produs',
        'product_ids' => 'Lucrări',
        'product_ids.*' => 'Lucrare',
        'title' => 'Titlu',
        'name' => 'Nume',
        'email' => 'Adresă de e-mail',
        'password' => 'Parolă',
        'password_confirmation' => 'Confirmarea parolei',
        'current_password' => 'Parola curentă',
        'language' => 'Limba interfeței',
        'progress' => 'Progres',
        'score' => 'Scor',
        'age_category' => 'Categorie de vârstă',
        'work_image' => 'Imagine copertă',
        'sample_images' => 'Imagini mostră',
        'sample_images.*' => 'Imagine mostră',
        'sample_image_order' => 'Ordinea imaginilor mostră',
        'description_japanese' => 'Descriere japoneză',
        'description_localized' => 'Descriere localizată',
        'tags' => 'Etichete',
        'notes' => 'Notițe',
        'start_date' => 'Data de început',
        'finish_date' => 'Data de finalizare',
        're_listen_value' => 'Valoare de reascultare',
        'priority' => 'Prioritate',
        'newGroupTitle' => 'Titlu grup nou',
        'mode' => 'Mod',
        'theme' => 'Temă',
        'sizeChoice' => 'Dimensiune maximă per ZIP',
        'customMib' => 'Dimensiune maximă per ZIP (MiB)',
        'tagOrder' => 'Ordinea sugestiilor de etichete',
        'seriesOrder' => 'Ordinea sugestiilor de serii',
        'focus' => 'Formularea interfeței',
        'archive' => 'Fișier ZIP',
    ],
];
