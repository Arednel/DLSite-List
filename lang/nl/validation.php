<?php

// Laravel validation messages are based on the Dutch translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/nl).
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

    'accepted' => ':attribute moet geaccepteerd worden.',
    'accepted_if' => ':attribute moet geaccepteerd worden wanneer :other :value is.',
    'active_url' => ':attribute is geen geldige URL.',
    'after' => ':attribute moet een datum na :date zijn.',
    'after_or_equal' => ':attribute moet een datum na of gelijk aan :date zijn.',
    'alpha' => ':attribute mag alleen letters bevatten.',
    'alpha_dash' => ':attribute mag alleen letters, cijfers, streepjes en underscores bevatten.',
    'alpha_num' => ':attribute mag alleen letters en cijfers bevatten.',
    'any_of' => ':attribute is ongeldig.',
    'array' => ':attribute moet een array zijn.',
    'ascii' => ':attribute mag alleen alfanumerieke single-byte-tekens en symbolen bevatten.',
    'before' => ':attribute moet een datum vóór :date zijn.',
    'before_or_equal' => ':attribute moet een datum vóór of gelijk aan :date zijn.',
    'between' => [
        'array' => ':attribute moet tussen :min en :max items bevatten.',
        'file' => ':attribute moet tussen :min en :max kilobytes groot zijn.',
        'numeric' => ':attribute moet tussen :min en :max liggen.',
        'string' => ':attribute moet tussen :min en :max tekens lang zijn.',
    ],
    'boolean' => ':attribute moet waar of onwaar zijn.',
    'can' => ':attribute bevat een niet-toegestane waarde.',
    'confirmed' => 'De bevestiging van :attribute komt niet overeen.',
    'contains' => ':attribute bevat niet de vereiste waarde.',
    'current_password' => 'Het wachtwoord is onjuist.',
    'date' => ':attribute is geen geldige datum.',
    'date_equals' => ':attribute moet een datum gelijk aan :date zijn.',
    'date_format' => ':attribute komt niet overeen met het formaat :format.',
    'decimal' => ':attribute moet :decimal decimalen bevatten.',
    'declined' => ':attribute moet worden afgewezen.',
    'declined_if' => ':attribute moet worden afgewezen wanneer :other :value is.',
    'different' => ':attribute en :other moeten verschillend zijn.',
    'digits' => ':attribute moet uit :digits cijfers bestaan.',
    'digits_between' => ':attribute moet tussen :min en :max cijfers bevatten.',
    'dimensions' => ':attribute heeft ongeldige afbeeldingsafmetingen.',
    'distinct' => ':attribute bevat een dubbele waarde.',
    'doesnt_contain' => ':attribute mag geen van de volgende waarden bevatten: :values.',
    'doesnt_end_with' => ':attribute mag niet eindigen op een van de volgende waarden: :values.',
    'doesnt_start_with' => ':attribute mag niet beginnen met een van de volgende waarden: :values.',
    'email' => ':attribute moet een geldig e-mailadres zijn.',
    'encoding' => ':attribute moet gecodeerd zijn als :encoding.',
    'ends_with' => ':attribute moet eindigen op een van de volgende waarden: :values.',
    'enum' => 'De geselecteerde waarde voor :attribute is ongeldig.',
    'exists' => 'De geselecteerde waarde voor :attribute is ongeldig.',
    'extensions' => ':attribute moet een van de volgende extensies hebben: :values.',
    'file' => ':attribute moet een bestand zijn.',
    'filled' => ':attribute moet een waarde bevatten.',
    'gt' => [
        'array' => ':attribute moet meer dan :value items bevatten.',
        'file' => ':attribute moet groter zijn dan :value kilobytes.',
        'numeric' => ':attribute moet groter zijn dan :value.',
        'string' => ':attribute moet langer zijn dan :value tekens.',
    ],
    'gte' => [
        'array' => ':attribute moet :value of meer items bevatten.',
        'file' => ':attribute moet groter dan of gelijk aan :value kilobytes zijn.',
        'numeric' => ':attribute moet groter dan of gelijk aan :value zijn.',
        'string' => ':attribute moet :value of meer tekens bevatten.',
    ],
    'hex_color' => ':attribute moet een geldige hexadecimale kleur zijn.',
    'image' => ':attribute moet een afbeelding zijn.',
    'in' => 'De geselecteerde waarde voor :attribute is ongeldig.',
    'in_array' => ':attribute komt niet voor in :other.',
    'in_array_keys' => ':attribute moet ten minste een van de volgende sleutels bevatten: :values.',
    'integer' => ':attribute moet een geheel getal zijn.',
    'ip' => ':attribute moet een geldig IP-adres zijn.',
    'ipv4' => ':attribute moet een geldig IPv4-adres zijn.',
    'ipv6' => ':attribute moet een geldig IPv6-adres zijn.',
    'json' => ':attribute moet een geldige JSON-string zijn.',
    'list' => ':attribute moet een lijst zijn.',
    'lowercase' => ':attribute moet in kleine letters zijn.',
    'lt' => [
        'array' => ':attribute moet minder dan :value items bevatten.',
        'file' => ':attribute moet kleiner zijn dan :value kilobytes.',
        'numeric' => ':attribute moet kleiner zijn dan :value.',
        'string' => ':attribute moet korter zijn dan :value tekens.',
    ],
    'lte' => [
        'array' => ':attribute mag maximaal :value items bevatten.',
        'file' => ':attribute moet kleiner dan of gelijk aan :value kilobytes zijn.',
        'numeric' => ':attribute moet kleiner dan of gelijk aan :value zijn.',
        'string' => ':attribute mag maximaal :value tekens lang zijn.',
    ],
    'mac_address' => ':attribute moet een geldig MAC-adres zijn.',
    'max' => [
        'array' => ':attribute mag niet meer dan :max items bevatten.',
        'file' => ':attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => ':attribute mag niet groter zijn dan :max.',
        'string' => ':attribute mag niet langer zijn dan :max tekens.',
    ],
    'max_digits' => ':attribute mag niet meer dan :max cijfers bevatten.',
    'mimes' => ':attribute moet een bestand van het type :values zijn.',
    'mimetypes' => ':attribute moet een bestand van het type :values zijn.',
    'min' => [
        'array' => ':attribute moet minimaal :min items bevatten.',
        'file' => ':attribute moet minimaal :min kilobytes groot zijn.',
        'numeric' => ':attribute moet minimaal :min zijn.',
        'string' => ':attribute moet minimaal :min tekens lang zijn.',
    ],
    'min_digits' => ':attribute moet minimaal :min cijfers bevatten.',
    'missing' => ':attribute moet ontbreken.',
    'missing_if' => ':attribute moet ontbreken wanneer :other :value is.',
    'missing_unless' => ':attribute moet ontbreken tenzij :other :value is.',
    'missing_with' => ':attribute moet ontbreken wanneer :values aanwezig is.',
    'missing_with_all' => ':attribute moet ontbreken wanneer :values aanwezig zijn.',
    'multiple_of' => ':attribute moet een veelvoud van :value zijn.',
    'not_in' => 'De geselecteerde waarde voor :attribute is ongeldig.',
    'not_regex' => 'Het formaat van :attribute is ongeldig.',
    'numeric' => ':attribute moet een getal zijn.',
    'password' => [
        'letters' => ':attribute moet minimaal één letter bevatten.',
        'mixed' => ':attribute moet minimaal één hoofdletter en één kleine letter bevatten.',
        'numbers' => ':attribute moet minimaal één cijfer bevatten.',
        'symbols' => ':attribute moet minimaal één symbool bevatten.',
        'uncompromised' => ':attribute is aangetroffen in een datalek. Kies een andere waarde voor :attribute.',
    ],
    'present' => ':attribute moet aanwezig zijn.',
    'present_if' => ':attribute moet aanwezig zijn wanneer :other :value is.',
    'present_unless' => ':attribute moet aanwezig zijn tenzij :other :value is.',
    'present_with' => ':attribute moet aanwezig zijn wanneer :values aanwezig is.',
    'present_with_all' => ':attribute moet aanwezig zijn wanneer :values aanwezig zijn.',
    'prohibited' => ':attribute is niet toegestaan.',
    'prohibited_if' => ':attribute is niet toegestaan wanneer :other :value is.',
    'prohibited_if_accepted' => ':attribute is niet toegestaan wanneer :other is geaccepteerd.',
    'prohibited_if_declined' => ':attribute is niet toegestaan wanneer :other is afgewezen.',
    'prohibited_unless' => ':attribute is niet toegestaan tenzij :other in :values voorkomt.',
    'prohibits' => ':attribute verbiedt de aanwezigheid van :other.',
    'regex' => 'Het formaat van :attribute is ongeldig.',
    'required' => ':attribute is verplicht.',
    'required_array_keys' => ':attribute moet de volgende sleutels bevatten: :values.',
    'required_if' => ':attribute is verplicht wanneer :other :value is.',
    'required_if_accepted' => ':attribute is verplicht wanneer :other is geaccepteerd.',
    'required_if_declined' => ':attribute is verplicht wanneer :other is afgewezen.',
    'required_unless' => ':attribute is verplicht tenzij :other in :values voorkomt.',
    'required_with' => ':attribute is verplicht wanneer :values aanwezig is.',
    'required_with_all' => ':attribute is verplicht wanneer :values aanwezig zijn.',
    'required_without' => ':attribute is verplicht wanneer :values niet aanwezig is.',
    'required_without_all' => ':attribute is verplicht wanneer geen van :values aanwezig is.',
    'same' => ':attribute en :other moeten overeenkomen.',
    'size' => [
        'array' => ':attribute moet :size items bevatten.',
        'file' => ':attribute moet :size kilobytes groot zijn.',
        'numeric' => ':attribute moet :size zijn.',
        'string' => ':attribute moet :size tekens lang zijn.',
    ],
    'starts_with' => ':attribute moet beginnen met een van de volgende waarden: :values.',
    'string' => ':attribute moet een tekenreeks zijn.',
    'timezone' => ':attribute moet een geldige tijdzone zijn.',
    'unique' => ':attribute is al in gebruik.',
    'uploaded' => 'Het uploaden van :attribute is mislukt.',
    'uppercase' => ':attribute moet in hoofdletters zijn.',
    'url' => ':attribute moet een geldige URL zijn.',
    'ulid' => ':attribute moet een geldige ULID zijn.',
    'uuid' => ':attribute moet een geldige UUID zijn.',

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
            'required' => 'Aangepaste kolombreedte is verplicht.',
            'regex' => 'Ongeldig formaat voor kolombreedte. Gebruik px, rem, em, %, of vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'Kolombreedtemodus is verplicht.',
            'in' => 'De geselecteerde kolombreedtemodus is ongeldig.',
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
        'username' => 'Gebruikersnaam',
        'remember' => 'Onthouden',
        'work_name' => 'Japanse titel',
        'metadata_language' => 'DLsite-metadatataal',
        'localized_title' => 'Gelokaliseerde titel',
        'series' => 'Serie',
        'product_code_list' => 'Productcode of link',
        'product_codes' => 'Productcodes',
        'product_codes.*' => 'Productcode',
        'product_format' => 'Productformaat',
        'product_format.*' => 'Productformaat',
        'circle' => 'Circle',
        'publisher' => 'Uitgever',
        'brand' => 'Merk',
        'maker_id' => 'Maker-ID',
        'scenario' => 'Scenarioschrijver',
        'voice_actor' => 'Stemacteur',
        'illustration' => 'Illustrator',
        'author' => 'Auteur',
        'genre_custom' => 'Aangepaste tags',
        'description' => 'Japanse beschrijving',
        'end_date' => 'Einddatum',
        'add.start_date.year' => 'Startjaar',
        'add.start_date.month' => 'Startmaand',
        'add.start_date.day' => 'Startdag',
        'add.finish_date.year' => 'Eindjaar',
        'add.finish_date.month' => 'Eindmaand',
        'add.finish_date.day' => 'Einddag',
        'num_re_listen_times' => 'Aantal keren opnieuw beluisterd',
        'currentPassword' => 'Huidig wachtwoord',
        'newPassword' => 'Nieuw wachtwoord',
        'newPasswordConfirmation' => 'Bevestiging nieuw wachtwoord',
        'followFetchedTagsLanguage' => 'Automatisch UI-taal volgen',
        'enabled' => 'Ingeschakelde status',
        'customValue' => 'Aangepaste waarde',
        'newTagTitle' => 'Nieuwe tagnaam',
        'editingTagTitle' => 'Tagnaam',
        'id' => 'Productcode',
        'product_code' => 'Productcode',
        'product_ids' => 'Werken',
        'product_ids.*' => 'Werk',
        'title' => 'Titel',
        'name' => 'Naam',
        'email' => 'E-mailadres',
        'password' => 'Wachtwoord',
        'password_confirmation' => 'Wachtwoordbevestiging',
        'current_password' => 'Huidig wachtwoord',
        'language' => 'UI-taal',
        'progress' => 'Voortgang',
        'score' => 'Score',
        'age_category' => 'Leeftijdscategorie',
        'work_image' => 'Omslagafbeelding',
        'sample_images' => 'Voorbeeldafbeeldingen',
        'sample_images.*' => 'Voorbeeldafbeelding',
        'sample_image_order' => 'Volgorde voorbeeldafbeeldingen',
        'description_japanese' => 'Japanse beschrijving',
        'description_localized' => 'Gelokaliseerde beschrijving',
        'tags' => 'Tags',
        'notes' => 'Notities',
        'start_date' => 'Startdatum',
        'finish_date' => 'Einddatum',
        're_listen_value' => 'Waarde voor opnieuw beluisteren',
        'priority' => 'Prioriteit',
        'newGroupTitle' => 'Nieuwe groepsnaam',
        'mode' => 'Modus',
        'theme' => 'Thema',
        'sizeChoice' => 'Maximale grootte per ZIP',
        'customMib' => 'Maximale grootte per ZIP (MiB)',
        'tagOrder' => 'Volgorde tagsuggesties',
        'seriesOrder' => 'Volgorde seriesuggesties',
        'focus' => 'UI-woordgebruik',
        'archive' => 'ZIP-bestand',
    ],
];
