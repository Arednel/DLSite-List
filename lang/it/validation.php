<?php

// Laravel validation messages are based on the Italian translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/it).
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

    'accepted' => ':attribute deve essere accettato.',
    'accepted_if' => ':attribute deve essere accettato quando :other è :value.',
    'active_url' => ':attribute non è un URL valido.',
    'after' => ':attribute deve essere una data successiva al :date.',
    'after_or_equal' => ':attribute deve essere una data successiva o uguale al :date.',
    'alpha' => ':attribute può contenere solo lettere.',
    'alpha_dash' => ':attribute può contenere solo lettere, numeri e trattini.',
    'alpha_num' => ':attribute può contenere solo lettere e numeri.',
    'any_of' => 'Il campo :attribute non è valido.',
    'array' => ':attribute deve essere un array.',
    'ascii' => ':attribute deve contenere solo caratteri alfanumerici single-byte e simboli.',
    'before' => ':attribute deve essere una data precedente al :date.',
    'before_or_equal' => ':attribute deve essere una data precedente o uguale al :date.',
    'between' => [
        'array' => ':attribute deve avere tra :min - :max elementi.',
        'file' => ':attribute deve trovarsi tra :min - :max kilobyte.',
        'numeric' => ':attribute deve trovarsi tra :min - :max.',
        'string' => ':attribute deve trovarsi tra :min - :max caratteri.',
    ],
    'boolean' => 'Il campo :attribute deve essere vero o falso.',
    'can' => 'Il campo :attribute contiene un valore non autorizzato.',
    'confirmed' => 'Il campo di conferma per :attribute non coincide.',
    'contains' => 'Il campo :attribute non contiene un valore richiesto.',
    'current_password' => 'Password non valida.',
    'date' => ':attribute non è una data valida.',
    'date_equals' => ':attribute deve essere una data e uguale a :date.',
    'date_format' => ':attribute non coincide con il formato :format.',
    'decimal' => ':attribute deve avere :decimal cifre decimali.',
    'declined' => ':attribute deve essere rifiutato.',
    'declined_if' => ':attribute deve essere rifiutato quando :other è :value.',
    'different' => ':attribute e :other devono essere differenti.',
    'digits' => ':attribute deve essere di :digits cifre.',
    'digits_between' => ':attribute deve essere tra :min e :max cifre.',
    'dimensions' => 'Le dimensioni dell\'immagine di :attribute non sono valide.',
    'distinct' => ':attribute contiene un valore duplicato.',
    'doesnt_contain' => 'Il campo :attribute non deve contenere alcuno dei seguenti: :values.',
    'doesnt_end_with' => ':attribute non può terminare con uno dei seguenti valori: :values.',
    'doesnt_start_with' => ':attribute non può iniziare con uno dei seguenti valori: :values.',
    'email' => ':attribute non è valido.',
    'encoding' => 'Il campo :attribute deve essere codificato in :encoding.',
    'ends_with' => ':attribute deve finire con uno dei seguenti valori: :values',
    'enum' => 'Il campo :attribute non è valido.',
    'exists' => ':attribute selezionato non è valido.',
    'extensions' => 'Il campo :attribute deve avere una delle seguenti estensioni: :values.',
    'file' => ':attribute deve essere un file.',
    'filled' => 'Il campo :attribute deve contenere un valore.',
    'gt' => [
        'array' => ':attribute deve contenere più di :value elementi.',
        'file' => ':attribute deve essere maggiore di :value kilobyte.',
        'numeric' => ':attribute deve essere maggiore di :value.',
        'string' => ':attribute deve contenere più di :value caratteri.',
    ],
    'gte' => [
        'array' => ':attribute deve contenere un numero di elementi uguale o maggiore di :value.',
        'file' => ':attribute deve essere uguale o maggiore di :value kilobyte.',
        'numeric' => ':attribute deve essere uguale o maggiore di :value.',
        'string' => ':attribute deve contenere un numero di caratteri uguale o maggiore di :value.',
    ],
    'hex_color' => 'Il campo :attribute deve essere un colore esadecimale valido.',
    'image' => ':attribute deve essere un\'immagine.',
    'in' => ':attribute selezionato non è valido.',
    'in_array' => 'Il valore del campo :attribute non esiste in :other.',
    'in_array_keys' => 'Il campo :attribute deve contenere almeno una delle seguenti chiavi: :values.',
    'integer' => ':attribute deve essere un numero intero.',
    'ip' => ':attribute deve essere un indirizzo IP valido.',
    'ipv4' => ':attribute deve essere un indirizzo IPv4 valido.',
    'ipv6' => ':attribute deve essere un indirizzo IPv6 valido.',
    'json' => ':attribute deve essere una stringa JSON valida.',
    'list' => 'Il campo :attribute deve essere un elenco.',
    'lowercase' => ':attribute deve contenere solo caratteri minuscoli.',
    'lt' => [
        'array' => ':attribute deve contenere meno di :value elementi.',
        'file' => ':attribute deve essere minore di :value kilobyte.',
        'numeric' => ':attribute deve essere minore di :value.',
        'string' => ':attribute deve contenere meno di :value caratteri.',
    ],
    'lte' => [
        'array' => ':attribute deve contenere un numero di elementi minore o uguale a :value.',
        'file' => ':attribute deve essere minore o uguale a :value kilobyte.',
        'numeric' => ':attribute deve essere minore o uguale a :value.',
        'string' => ':attribute deve contenere un numero di caratteri minore o uguale a :value.',
    ],
    'mac_address' => 'Il campo :attribute deve essere un indirizzo MAC valido .',
    'max' => [
        'array' => ':attribute non può avere più di :max elementi.',
        'file' => ':attribute non può essere superiore a :max kilobyte.',
        'numeric' => ':attribute non può essere superiore a :max.',
        'string' => ':attribute non può contenere più di :max caratteri.',
    ],
    'max_digits' => ':attribute non può contenere più di :max cifre.',
    'mimes' => ':attribute deve essere del tipo: :values.',
    'mimetypes' => ':attribute deve essere del tipo: :values.',
    'min' => [
        'array' => ':attribute deve avere almeno :min elementi.',
        'file' => ':attribute deve essere almeno di :min kilobyte.',
        'numeric' => ':attribute deve essere almeno :min.',
        'string' => ':attribute deve contenere almeno :min caratteri.',
    ],
    'min_digits' => ':attribute deve contenere almeno :min cifre.',
    'missing' => 'Il campo :attribute deve mancare.',
    'missing_if' => 'Il campo :attribute deve mancare quando :other è :value.',
    'missing_unless' => 'Il campo :attribute deve mancare a meno che :other non sia :value.',
    'missing_with' => 'Il campo :attribute deve mancare quando è presente :values.',
    'missing_with_all' => 'Il campo :attribute deve mancare quando sono presenti :values.',
    'multiple_of' => ':attribute deve essere un multiplo di :value',
    'not_in' => 'Il valore selezionato per :attribute non è valido.',
    'not_regex' => 'Il formato di :attribute non è valido.',
    'numeric' => ':attribute deve essere un numero.',
    'password' => [
        'letters' => ':attribute deve contenere almeno un carattere.',
        'mixed' => ':attribute deve contenere almeno un carattere maiuscolo ed un carattere minuscolo.',
        'numbers' => ':attribute deve contenere almeno un numero.',
        'symbols' => ':attribute deve contenere almeno un simbolo.',
        'uncompromised' => ':attribute è presente negli archivi dei dati trafugati. Per piacere scegli un valore differente per :attribute.',
    ],
    'present' => 'Il campo :attribute deve essere presente.',
    'present_if' => 'Il campo :attribute deve essere presente quando :other è :value.',
    'present_unless' => 'Il campo :attribute deve essere presente a meno che :other non sia :value.',
    'present_with' => 'Il campo :attribute deve essere presente quando è presente :values.',
    'present_with_all' => 'Il campo :attribute deve essere presente quando sono presenti :values.',
    'prohibited' => ':attribute non consentito.',
    'prohibited_if' => ':attribute non consentito quando :other è :value.',
    'prohibited_if_accepted' => 'Il campo :attribute è vietato quando :other è accettato.',
    'prohibited_if_declined' => 'Il campo :attribute è vietato quando :other è rifiutato.',
    'prohibited_unless' => ':attribute non consentito a meno che :other sia contenuto in :values.',
    'prohibits' => ':attribute impedisce a :other di essere presente.',
    'regex' => 'Il formato del campo :attribute non è valido.',
    'required' => 'Il campo :attribute è richiesto.',
    'required_array_keys' => 'Il campo :attribute deve contenere voci per: :values.',
    'required_if' => 'Il campo :attribute è richiesto quando :other è :value.',
    'required_if_accepted' => ':attribute è richiesto quando :other è accettato.',
    'required_if_declined' => 'Il campo :attribute è richiesto quando :other è rifiutato.',
    'required_unless' => 'Il campo :attribute è richiesto a meno che :other sia in :values.',
    'required_with' => 'Il campo :attribute è richiesto quando :values è presente.',
    'required_with_all' => 'Il campo :attribute è richiesto quando :values sono presenti.',
    'required_without' => 'Il campo :attribute è richiesto quando :values non è presente.',
    'required_without_all' => 'Il campo :attribute è richiesto quando nessuno di :values è presente.',
    'same' => ':attribute e :other devono coincidere.',
    'size' => [
        'array' => ':attribute deve contenere :size elementi.',
        'file' => ':attribute deve essere :size kilobyte.',
        'numeric' => ':attribute deve essere :size.',
        'string' => ':attribute deve contenere :size caratteri.',
    ],
    'starts_with' => ':attribute deve iniziare con uno dei seguenti: :values',
    'string' => ':attribute deve essere una stringa.',
    'timezone' => ':attribute deve essere una zona valida.',
    'unique' => ':attribute è stato già utilizzato.',
    'uploaded' => ':attribute non è stato caricato.',
    'uppercase' => ':attribute deve contenere solo caratteri maiuscoli.',
    'url' => 'Il formato del campo :attribute non è valido.',
    'ulid' => ':attribute deve essere un ULID valido.',
    'uuid' => ':attribute deve essere un UUID valido.',

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
            'required' => 'È richiesta una larghezza di colonna personalizzata.',
            'regex' => 'Il formato della larghezza di colonna non è valido. Usa px, rem, em, % o vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'È richiesta una modalità di larghezza colonna.',
            'in' => 'La modalità di larghezza colonna selezionata non è valida.',
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
        'username' => 'Nome utente',
        'remember' => 'Ricordami',
        'work_name' => 'Titolo giapponese',
        'metadata_language' => 'Lingua metadati preferita',
        'localized_title' => 'Titolo localizzato',
        'series' => 'Serie',
        'product_code_list' => 'Codice prodotto o link',
        'product_codes' => 'Codice prodotto',
        'product_codes.*' => 'Codice prodotto',
        'product_format' => 'Formato dell\'opera',
        'product_format.*' => 'Formato dell\'opera',
        'circle' => 'Circolo',
        'publisher' => 'Editore',
        'brand' => 'Marchio',
        'maker_id' => 'ID creatore',
        'scenario' => 'Autore scenario',
        'voice_actor' => 'Doppiatore/Doppiatrice',
        'illustration' => 'Illustratore',
        'author' => 'Autore',
        'genre_custom' => 'Tag personalizzati',
        'description' => 'Descrizione giapponese',
        'end_date' => 'Data di fine',
        'add.start_date.year' => 'Anno di inizio',
        'add.start_date.month' => 'Mese di inizio',
        'add.start_date.day' => 'Giorno di inizio',
        'add.finish_date.year' => 'Anno di completamento',
        'add.finish_date.month' => 'Mese di completamento',
        'add.finish_date.day' => 'Giorno di completamento',
        'num_re_listen_times' => 'Totale riascolti',
        'currentPassword' => 'Password attuale',
        'newPassword' => 'Nuova password',
        'newPasswordConfirmation' => 'Conferma nuova password',
        'followFetchedTagsLanguage' => 'Segui automaticamente la lingua dell’interfaccia',
        'enabled' => 'Impostazione abilitata',
        'customValue' => 'Valore personalizzato',
        'newTagTitle' => 'Titolo nuovo tag',
        'editingTagTitle' => 'Titolo tag',
        'id' => 'Codice prodotto',
        'product_code' => 'Codice prodotto',
        'product_ids' => 'Opere',
        'product_ids.*' => 'Opere',
        'title' => 'Titolo',
        'name' => 'Nome',
        'email' => 'Indirizzo e-mail',
        'password' => 'Password',
        'password_confirmation' => 'Conferma password',
        'current_password' => 'Password attuale',
        'language' => 'Lingua dell’interfaccia',
        'progress' => 'Avanzamento',
        'score' => 'Punteggio',
        'age_category' => 'Età',
        'work_image' => 'Immagine di copertina',
        'sample_images' => 'Immagini di esempio',
        'sample_images.*' => 'Immagini di esempio',
        'sample_image_order' => 'Ordine immagini di esempio',
        'description_japanese' => 'Descrizione giapponese',
        'description_localized' => 'Descrizione localizzata',
        'tags' => 'Tag',
        'notes' => 'Note',
        'start_date' => 'Data di inizio',
        'finish_date' => 'Data di fine',
        're_listen_value' => 'Valore riascolto',
        'priority' => 'Priorità',
        'newGroupTitle' => 'Titolo nuovo gruppo',
        'mode' => 'Modalità di visualizzazione',
        'theme' => 'Tema',
        'sizeChoice' => 'Dimensione parti ZIP',
        'customMib' => 'Dimensione massima per ZIP (MiB)',
        'tagOrder' => 'Ordine tag',
        'seriesOrder' => 'Ordine serie',
        'focus' => 'Tipo di contenuto',
        'archive' => 'File ZIP',
    ],
];
