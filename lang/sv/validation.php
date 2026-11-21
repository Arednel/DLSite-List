<?php

// Laravel validation messages are based on the Swedish translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/sv).
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

    'accepted' => ':attribute måste accepteras.',
    'accepted_if' => ':attribute måste accepteras när :other är :value.',
    'active_url' => ':attribute är inte en giltig webbadress.',
    'after' => ':attribute måste vara ett datum efter :date.',
    'after_or_equal' => ':attribute måste vara ett datum senare eller samma dag som :date.',
    'alpha' => ':attribute får endast innehålla bokstäver.',
    'alpha_dash' => ':attribute får endast innehålla bokstäver, siffror och bindestreck.',
    'alpha_num' => ':attribute får endast innehålla bokstäver och siffror.',
    'any_of' => 'Det :attribute fältet är ogiltigt.',
    'array' => ':attribute måste vara en array.',
    'ascii' => ':attribute måste innehålla endast alfanumeriska enkelbytetecken och symboler.',
    'before' => ':attribute måste vara ett datum innan :date.',
    'before_or_equal' => ':attribute måste vara ett datum före eller samma dag som :date.',
    'between' => [
        'array' => ':attribute måste innehålla mellan :min - :max objekt.',
        'file' => ':attribute måste vara mellan :min till :max kilobyte stor.',
        'numeric' => ':attribute måste vara en siffra mellan :min och :max.',
        'string' => ':attribute måste innehålla :min till :max tecken.',
    ],
    'boolean' => ':attribute måste vara sant eller falskt.',
    'can' => 'Fältet :attribute innehåller ett obehörigt värde.',
    'confirmed' => ':attribute bekräftelsen matchar inte.',
    'contains' => 'Fältet :attribute saknar ett obligatoriskt värde.',
    'current_password' => 'Lösenordet är felaktigt.',
    'date' => ':attribute är inte ett giltigt datum.',
    'date_equals' => ':attribute måste vara ett datum lika med :date.',
    'date_format' => ':attribute matchar inte formatet :format.',
    'decimal' => 'De :attribute måste ha :decimal decimaler.',
    'declined' => ':attribute måste vara avaktiverat.',
    'declined_if' => ':attribute måste vara avaktiverat när :other är :value.',
    'different' => ':attribute och :other får inte vara lika.',
    'digits' => ':attribute måste vara :digits tecken.',
    'digits_between' => ':attribute måste vara mellan :min och :max tecken.',
    'dimensions' => ':attribute har felaktiga bilddimensioner.',
    'distinct' => ':attribute innehåller fler än en repetition av samma element.',
    'doesnt_contain' => ':attribute får inte innehålla :values.',
    'doesnt_end_with' => ':attribute får inte sluta med det följande värden: :values.',
    'doesnt_start_with' => ':attribute får inte börja med följande värden: :values.',
    'email' => ':attribute måste innehålla en korrekt e-postadress.',
    'encoding' => 'Fältet :attribute måste kodas i :encoding.',
    'ends_with' => ':attribute måste sluta med en av följande: :values.',
    'enum' => ':attribute är ogiltigt.',
    'exists' => ':attribute existerar ej i databasen och är därför ogiltigt.',
    'extensions' => 'Fältet :attribute måste ha en av följande tillägg: :values.',
    'file' => ':attribute måste vara en fil.',
    'filled' => ':attribute är obligatoriskt.',
    'gt' => [
        'array' => ':attribute måste innehålla fler än :value objekt.',
        'file' => ':attribute måste vara större än :value kilobyte stor.',
        'numeric' => ':attribute måste vara större än :value.',
        'string' => ':attribute måste vara längre än :value tecken.',
    ],
    'gte' => [
        'array' => ':attribute måste innehålla lika många eller fler än :value objekt.',
        'file' => ':attribute måste vara lika med eller större än :value kilobyte stor.',
        'numeric' => ':attribute måste vara lika med eller större än :value.',
        'string' => ':attribute måste vara lika med eller längre än :value tecken.',
    ],
    'hex_color' => 'Fältet :attribute måste vara en giltig hexadecimal färg.',
    'image' => ':attribute måste vara en bild.',
    'in' => ':attribute är ogiltigt.',
    'in_array' => ':attribute finns inte i :other.',
    'in_array_keys' => ':attribute måste innehålla :values.',
    'integer' => ':attribute måste vara en siffra.',
    'ip' => ':attribute måste vara en giltig IP-adress.',
    'ipv4' => ':attribute måste vara en giltig IPv4-adress.',
    'ipv6' => ':attribute måste vara en giltig IPv6-adress.',
    'json' => ':attribute måste vara en giltig JSON-sträng.',
    'list' => 'Fältet :attribute måste vara en lista.',
    'lowercase' => ':attribute måste vara i små bokstäver.',
    'lt' => [
        'array' => ':attribute måste innehålla färre än :value objekt.',
        'file' => ':attribute måste vara mindre än :value kilobyte stor.',
        'numeric' => ':attribute måste vara mindre än :value.',
        'string' => ':attribute måste vara kortare än :value tecken.',
    ],
    'lte' => [
        'array' => ':attribute måste innehålla lika många eller färre än :value objekt.',
        'file' => ':attribute måste vara lika med eller mindre än :value kilobyte stor.',
        'numeric' => ':attribute måste vara lika med eller mindre än :value.',
        'string' => ':attribute måste vara lika med eller kortare än :value tecken.',
    ],
    'mac_address' => ':attribute måste vara en giltig MAC adress.',
    'max' => [
        'array' => ':attribute får inte innehålla mer än :max objekt.',
        'file' => ':attribute får max vara :max kilobyte stor.',
        'numeric' => ':attribute får inte vara större än :max.',
        'string' => ':attribute får max innehålla :max tecken.',
    ],
    'max_digits' => ':attribute får inte innehålla mer än :max siffror.',
    'mimes' => ':attribute måste vara en fil av typen: :values.',
    'mimetypes' => ':attribute måste vara en fil av typen: :values.',
    'min' => [
        'array' => ':attribute måste innehålla minst :min objekt.',
        'file' => ':attribute måste vara minst :min kilobyte stor.',
        'numeric' => ':attribute måste vara större än :min.',
        'string' => ':attribute måste innehålla minst :min tecken.',
    ],
    'min_digits' => ':attribute måste innehålla ett minimum av :min siffror.',
    'missing' => ':attribute-fältet måste saknas.',
    'missing_if' => ':attribute-fältet måste saknas när :other är :value.',
    'missing_unless' => ':attribute-fältet måste saknas om inte :other är :value.',
    'missing_with' => ':attribute-fältet måste saknas när :values finns.',
    'missing_with_all' => ':attribute-fältet måste saknas när :values finns.',
    'multiple_of' => ':attribute måste vara en multipel av :value',
    'not_in' => ':attribute är ogiltigt.',
    'not_regex' => 'Formatet för :attribute är ogiltigt.',
    'numeric' => ':attribute måste vara en siffra.',
    'password' => [
        'letters' => ':attribute måste innehålla minst en bokstav.',
        'mixed' => ':attribute måste innehålla minst en lite och en stor bokstav.',
        'numbers' => ':attribute måste innehålla minst en siffra.',
        'symbols' => ':attribute måste innehålla minst en symbol.',
        'uncompromised' => 'Det angivna :attribute återfinns i läkta källor på internet. Byt :attribute så fort som möjligt.',
    ],
    'present' => ':attribute måste finnas med.',
    'present_if' => 'Fältet :attribute måste finnas när :other är :value.',
    'present_unless' => 'Fältet :attribute måste finnas om inte :other är :value.',
    'present_with' => 'Fältet :attribute måste finnas när :values är närvarande.',
    'present_with_all' => 'Fältet :attribute måste finnas när :values är närvarande.',
    'prohibited' => 'Fältet :attribute är förbjudet.',
    'prohibited_if' => ':attribute är förbjudet när :other är :value.',
    'prohibited_if_accepted' => 'Det :attribute fältet är förbjudet när :other accepteras.',
    'prohibited_if_declined' => 'Det :attribute fältet är förbjudet när :other avvisas.',
    'prohibited_unless' => ':attribute är förbjudet om inte :other är :values.',
    'prohibits' => ':attribute fältet förhindrar :other att ha ett värde.',
    'regex' => ':attribute har ogiltigt format.',
    'required' => ':attribute är obligatoriskt.',
    'required_array_keys' => ':attribute måste innehålla listnamn för :values.',
    'required_if' => ':attribute är obligatoriskt när :other är :value.',
    'required_if_accepted' => 'Fältet :attribute är ett krav när fält :other är accepterat.',
    'required_if_declined' => ':attribute-fältet krävs när :other avvisas.',
    'required_unless' => ':attribute är obligatoriskt när inte :other finns bland :values.',
    'required_with' => ':attribute är obligatoriskt när :values är ifyllt.',
    'required_with_all' => ':attribute är obligatoriskt när :values är ifyllt.',
    'required_without' => ':attribute är obligatoriskt när :values ej är ifyllt.',
    'required_without_all' => ':attribute är obligatoriskt när ingen av :values är ifyllt.',
    'same' => ':attribute och :other måste vara lika.',
    'size' => [
        'array' => ':attribute måste innehålla :size objekt.',
        'file' => ':attribute får endast vara :size kilobyte stor.',
        'numeric' => ':attribute måste vara :size.',
        'string' => ':attribute måste innehålla :size tecken.',
    ],
    'starts_with' => ':attribute måste börja med en av följande: :values',
    'string' => ':attribute måste vara en sträng.',
    'timezone' => ':attribute måste vara en giltig tidszon.',
    'unique' => ':attribute används redan.',
    'uploaded' => ':attribute kunde inte laddas upp.',
    'uppercase' => ':attribute måste vara versaler.',
    'url' => ':attribute har ett ogiltigt format.',
    'ulid' => ':attribute måste vara en giltig ULID.',
    'uuid' => ':attribute måste vara ett giltigt UUID.',

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
            'required' => 'En anpassad kolumnbredd krävs.',
            'regex' => 'Kolumnbreddens format är ogiltigt. Använd px, rem, em, % eller vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'Ett kolumnbreddsläge krävs.',
            'in' => 'Det valda kolumnbreddsläget är ogiltigt.',
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
        'username' => 'Användarnamn',
        'remember' => 'Kom ihåg mig',
        'work_name' => 'Japansk titel',
        'metadata_language' => 'Föredraget metadataspråk',
        'localized_title' => 'Lokaliserad titel',
        'series' => 'Serie',
        'product_code_list' => 'Produktkod eller länk',
        'product_codes' => 'Produktkod',
        'product_codes.*' => 'Produktkod',
        'product_format' => 'Produktformat',
        'product_format.*' => 'Produktformat',
        'circle' => 'Cirkel',
        'publisher' => 'Utgivare',
        'brand' => 'Varumärke',
        'maker_id' => 'Skapar-ID',
        'scenario' => 'Manusförfattare',
        'voice_actor' => 'Röstskådespelare',
        'illustration' => 'Illustratör',
        'author' => 'Författare',
        'genre_custom' => 'Anpassade taggar',
        'description' => 'Japansk beskrivning',
        'end_date' => 'Slutdatum',
        'add.start_date.year' => 'Startår',
        'add.start_date.month' => 'Startmånad',
        'add.start_date.day' => 'Startdag',
        'add.finish_date.year' => 'Slutår',
        'add.finish_date.month' => 'Slutmånad',
        'add.finish_date.day' => 'Slutdag',
        'num_re_listen_times' => 'Totalt antal återlyssningar',
        'currentPassword' => 'Nuvarande lösenord',
        'newPassword' => 'Nytt lösenord',
        'newPasswordConfirmation' => 'Bekräfta nytt lösenord',
        'followFetchedTagsLanguage' => 'Följ gränssnittsspråket automatiskt',
        'enabled' => 'Aktiverad inställning',
        'customValue' => 'Anpassat värde',
        'newTagTitle' => 'Ny taggtitel',
        'editingTagTitle' => 'Taggtitel',
        'id' => 'Produktkod',
        'product_code' => 'Produktkod',
        'product_ids' => 'Verk',
        'product_ids.*' => 'Verk',
        'title' => 'Titel',
        'name' => 'Namn',
        'email' => 'E-postadress',
        'password' => 'Lösenord',
        'password_confirmation' => 'Bekräfta lösenord',
        'current_password' => 'Nuvarande lösenord',
        'language' => 'Gränssnittsspråk',
        'progress' => 'Förlopp',
        'score' => 'Poäng',
        'age_category' => 'Ålder',
        'work_image' => 'Omslagsbild',
        'sample_images' => 'Exempelbilder',
        'sample_images.*' => 'Exempelbilder',
        'sample_image_order' => 'Ordning för exempelbilder',
        'description_japanese' => 'Japansk beskrivning',
        'description_localized' => 'Lokaliserad beskrivning',
        'tags' => 'Taggar',
        'notes' => 'Anteckningar',
        'start_date' => 'Startdatum',
        'finish_date' => 'Slutdatum',
        're_listen_value' => 'Återlyssningsvärde',
        'priority' => 'Prioritet',
        'newGroupTitle' => 'Ny grupptitel',
        'mode' => 'Visningsläge',
        'theme' => 'Tema',
        'sizeChoice' => 'ZIP-delstorlek',
        'customMib' => 'Maximal storlek per ZIP (MiB)',
        'tagOrder' => 'Taggordning',
        'seriesOrder' => 'Serieordning',
        'focus' => 'Innehållstyp',
        'archive' => 'ZIP-fil',
    ],
];
