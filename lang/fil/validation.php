<?php

// Laravel validation messages are based on the Filipino translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/fil).
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

    'accepted' => 'Dapat tanggapin ang :attribute.',
    'accepted_if' => 'Dapat tanggapin ang :attribute kapag :other ay :value.',
    'active_url' => 'Dapat valid na URL ang :attribute.',
    'after' => 'Dapat petsang pagkatapos ng :date ang :attribute.',
    'after_or_equal' => 'Dapat petsang pagkatapos o kapareho ng :date ang :attribute.',
    'alpha' => 'Mga letra lamang ang maaaring lamanin ng :attribute.',
    'alpha_dash' => 'Mga letra, numero, gitling, at underscore lamang ang maaaring lamanin ng :attribute.',
    'alpha_num' => 'Mga letra at numero lamang ang maaaring lamanin ng :attribute.',
    'any_of' => 'Hindi valid ang :attribute.',
    'array' => 'Dapat array ang :attribute.',
    'ascii' => 'Single-byte alphanumeric characters at symbols lamang ang maaaring lamanin ng :attribute.',
    'before' => 'Dapat petsang bago ang :date ang :attribute.',
    'before_or_equal' => 'Dapat petsang bago o kapareho ng :date ang :attribute.',
    'between' => [
        'array' => 'Dapat may :min hanggang :max item ang :attribute.',
        'file' => 'Dapat nasa pagitan ng :min at :max kilobytes ang :attribute.',
        'numeric' => 'Dapat nasa pagitan ng :min at :max ang :attribute.',
        'string' => 'Dapat nasa pagitan ng :min at :max character ang :attribute.',
    ],
    'boolean' => 'Dapat true o false ang :attribute.',
    'can' => 'May hindi awtorisadong value ang :attribute.',
    'confirmed' => 'Hindi tugma ang confirmation ng :attribute.',
    'contains' => 'Walang kinakailangang value ang :attribute.',
    'current_password' => 'Mali ang password.',
    'date' => 'Dapat valid na petsa ang :attribute.',
    'date_equals' => 'Dapat petsang kapareho ng :date ang :attribute.',
    'date_format' => 'Hindi tumutugma ang :attribute sa format na :format.',
    'decimal' => 'Dapat may :decimal decimal place ang :attribute.',
    'declined' => 'Dapat tanggihan ang :attribute.',
    'declined_if' => 'Dapat tanggihan ang :attribute kapag :other ay :value.',
    'different' => 'Dapat magkaiba ang :attribute at :other.',
    'digits' => 'Dapat may :digits digit ang :attribute.',
    'digits_between' => 'Dapat may :min hanggang :max digit ang :attribute.',
    'dimensions' => 'Hindi valid ang image dimensions ng :attribute.',
    'distinct' => 'May duplicate na value ang :attribute.',
    'doesnt_contain' => 'Hindi dapat naglalaman ang :attribute ng alinman sa mga sumusunod: :values.',
    'doesnt_end_with' => 'Hindi dapat nagtatapos ang :attribute sa alinman sa mga sumusunod: :values.',
    'doesnt_start_with' => 'Hindi dapat nagsisimula ang :attribute sa alinman sa mga sumusunod: :values.',
    'email' => 'Dapat valid na email address ang :attribute.',
    'encoding' => 'Dapat naka-encode bilang :encoding ang :attribute.',
    'ends_with' => 'Dapat magtapos ang :attribute sa isa sa mga sumusunod: :values.',
    'enum' => 'Hindi valid ang napiling :attribute.',
    'exists' => 'Hindi valid ang napiling :attribute.',
    'extensions' => 'Dapat may isa sa mga sumusunod na extension ang :attribute: :values.',
    'file' => 'Dapat file ang :attribute.',
    'filled' => 'Dapat may value ang :attribute.',
    'gt' => [
        'array' => 'Dapat may higit sa :value item ang :attribute.',
        'file' => 'Dapat mas malaki sa :value kilobytes ang :attribute.',
        'numeric' => 'Dapat mas malaki sa :value ang :attribute.',
        'string' => 'Dapat mas mahaba sa :value character ang :attribute.',
    ],
    'gte' => [
        'array' => 'Dapat may :value o higit pang item ang :attribute.',
        'file' => 'Dapat :value kilobytes o mas malaki ang :attribute.',
        'numeric' => 'Dapat :value o mas malaki ang :attribute.',
        'string' => 'Dapat may :value o higit pang character ang :attribute.',
    ],
    'hex_color' => 'Dapat valid na hexadecimal color ang :attribute.',
    'image' => 'Dapat image ang :attribute.',
    'in' => 'Hindi valid ang napiling :attribute.',
    'in_array' => 'Dapat nasa :other ang :attribute.',
    'in_array_keys' => 'Dapat may hindi bababa sa isa sa mga sumusunod na key ang :attribute: :values.',
    'integer' => 'Dapat integer ang :attribute.',
    'ip' => 'Dapat valid na IP address ang :attribute.',
    'ipv4' => 'Dapat valid na IPv4 address ang :attribute.',
    'ipv6' => 'Dapat valid na IPv6 address ang :attribute.',
    'json' => 'Dapat valid na JSON string ang :attribute.',
    'list' => 'Dapat list ang :attribute.',
    'lowercase' => 'Dapat lowercase ang :attribute.',
    'lt' => [
        'array' => 'Dapat mas kaunti sa :value item ang :attribute.',
        'file' => 'Dapat mas maliit sa :value kilobytes ang :attribute.',
        'numeric' => 'Dapat mas maliit sa :value ang :attribute.',
        'string' => 'Dapat mas maikli sa :value character ang :attribute.',
    ],
    'lte' => [
        'array' => 'Dapat may hindi hihigit sa :value item ang :attribute.',
        'file' => 'Dapat :value kilobytes o mas maliit ang :attribute.',
        'numeric' => 'Dapat :value o mas maliit ang :attribute.',
        'string' => 'Dapat may hindi hihigit sa :value character ang :attribute.',
    ],
    'mac_address' => 'Dapat valid na MAC address ang :attribute.',
    'max' => [
        'array' => 'Hindi dapat lumampas sa :max item ang :attribute.',
        'file' => 'Hindi dapat lumampas sa :max kilobytes ang :attribute.',
        'numeric' => 'Hindi dapat lumampas sa :max ang :attribute.',
        'string' => 'Hindi dapat lumampas sa :max character ang :attribute.',
    ],
    'max_digits' => 'Hindi dapat lumampas sa :max digit ang :attribute.',
    'mimes' => 'Dapat file na may type na :values ang :attribute.',
    'mimetypes' => 'Dapat file na may type na :values ang :attribute.',
    'min' => [
        'array' => 'Dapat may hindi bababa sa :min item ang :attribute.',
        'file' => 'Dapat hindi bababa sa :min kilobytes ang :attribute.',
        'numeric' => 'Dapat hindi bababa sa :min ang :attribute.',
        'string' => 'Dapat may hindi bababa sa :min character ang :attribute.',
    ],
    'min_digits' => 'Dapat may hindi bababa sa :min digit ang :attribute.',
    'missing' => 'Dapat wala ang :attribute.',
    'missing_if' => 'Dapat wala ang :attribute kapag :other ay :value.',
    'missing_unless' => 'Dapat wala ang :attribute maliban kung :other ay :value.',
    'missing_with' => 'Dapat wala ang :attribute kapag present ang :values.',
    'missing_with_all' => 'Dapat wala ang :attribute kapag present ang lahat ng :values.',
    'multiple_of' => 'Dapat multiple ng :value ang :attribute.',
    'not_in' => 'Hindi valid ang napiling :attribute.',
    'not_regex' => 'Hindi valid ang format ng :attribute.',
    'numeric' => 'Dapat numero ang :attribute.',
    'password' => [
        'letters' => 'Dapat may kahit isang letra ang :attribute.',
        'mixed' => 'Dapat may kahit isang uppercase at isang lowercase na letra ang :attribute.',
        'numbers' => 'Dapat may kahit isang numero ang :attribute.',
        'symbols' => 'Dapat may kahit isang symbol ang :attribute.',
        'uncompromised' => 'Nakita ang :attribute sa isang data leak. Pumili ng ibang value para sa :attribute.',
    ],
    'present' => 'Dapat present ang :attribute.',
    'present_if' => 'Dapat present ang :attribute kapag :other ay :value.',
    'present_unless' => 'Dapat present ang :attribute maliban kung :other ay :value.',
    'present_with' => 'Dapat present ang :attribute kapag present ang :values.',
    'present_with_all' => 'Dapat present ang :attribute kapag present ang lahat ng :values.',
    'prohibited' => 'Hindi pinapayagan ang :attribute.',
    'prohibited_if' => 'Hindi pinapayagan ang :attribute kapag :other ay :value.',
    'prohibited_if_accepted' => 'Hindi pinapayagan ang :attribute kapag accepted ang :other.',
    'prohibited_if_declined' => 'Hindi pinapayagan ang :attribute kapag declined ang :other.',
    'prohibited_unless' => 'Hindi pinapayagan ang :attribute maliban kung nasa :values ang :other.',
    'prohibits' => 'Hindi pinapayagan ng :attribute na present ang :other.',
    'regex' => 'Hindi valid ang format ng :attribute.',
    'required' => 'Required ang :attribute.',
    'required_array_keys' => 'Dapat may mga key na ito ang :attribute: :values.',
    'required_if' => 'Required ang :attribute kapag :other ay :value.',
    'required_if_accepted' => 'Required ang :attribute kapag accepted ang :other.',
    'required_if_declined' => 'Required ang :attribute kapag declined ang :other.',
    'required_unless' => 'Required ang :attribute maliban kung nasa :values ang :other.',
    'required_with' => 'Required ang :attribute kapag present ang :values.',
    'required_with_all' => 'Required ang :attribute kapag present ang lahat ng :values.',
    'required_without' => 'Required ang :attribute kapag wala ang :values.',
    'required_without_all' => 'Required ang :attribute kapag wala ang lahat ng :values.',
    'same' => 'Dapat magkapareho ang :attribute at :other.',
    'size' => [
        'array' => 'Dapat may :size item ang :attribute.',
        'file' => 'Dapat :size kilobytes ang :attribute.',
        'numeric' => 'Dapat :size ang :attribute.',
        'string' => 'Dapat :size character ang :attribute.',
    ],
    'starts_with' => 'Dapat magsimula ang :attribute sa isa sa mga sumusunod: :values.',
    'string' => 'Dapat string ang :attribute.',
    'timezone' => 'Dapat valid na timezone ang :attribute.',
    'unique' => 'Ginagamit na ang :attribute.',
    'uploaded' => 'Hindi na-upload ang :attribute.',
    'uppercase' => 'Dapat uppercase ang :attribute.',
    'url' => 'Dapat valid na URL ang :attribute.',
    'ulid' => 'Dapat valid na ULID ang :attribute.',
    'uuid' => 'Dapat valid na UUID ang :attribute.',

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
            'required' => 'Required ang custom column width.',
            'regex' => 'Invalid ang column width format. Gumamit ng px, rem, em, %, o vw.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'Required ang column width mode.',
            'in' => 'Invalid ang napiling column width mode.',
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
        'username' => 'Pangalan ng gumagamit',
        'remember' => 'Tandaan Ako',
        'work_name' => 'Pamagat sa Hapon',
        'metadata_language' => 'Wika ng metadata ng DLsite',
        'localized_title' => 'Pamagat na Naka-localize',
        'series' => 'Serye',
        'product_code_list' => 'Code o link ng produkto',
        'product_codes' => 'Mga code ng produkto',
        'product_codes.*' => 'Code ng Produkto',
        'product_format' => 'Format ng Produkto',
        'product_format.*' => 'Format ng Produkto',
        'circle' => 'Circle',
        'publisher' => 'Tagapaglathala',
        'brand' => 'Tatak',
        'maker_id' => 'Maker ID',
        'scenario' => 'Manunulat ng Senaryo',
        'voice_actor' => 'Voice Actor',
        'illustration' => 'Ilustrador',
        'author' => 'May-akda',
        'genre_custom' => 'Mga Pasadyang Tag',
        'description' => 'Deskripsiyong Hapones',
        'end_date' => 'Petsa ng Pagtatapos',
        'add.start_date.year' => 'Taon ng simula',
        'add.start_date.month' => 'Buwan ng simula',
        'add.start_date.day' => 'Araw ng simula',
        'add.finish_date.year' => 'Taon ng pagtatapos',
        'add.finish_date.month' => 'Buwan ng pagtatapos',
        'add.finish_date.day' => 'Araw ng pagtatapos',
        'num_re_listen_times' => 'Bilang ng muling pakikinig',
        'currentPassword' => 'Kasalukuyang password',
        'newPassword' => 'Bagong password',
        'newPasswordConfirmation' => 'Kumpirmahin ang bagong password',
        'followFetchedTagsLanguage' => 'Awtomatikong sundan ang wika ng UI',
        'enabled' => 'Katayuang naka-enable',
        'customValue' => 'Pasadyang halaga',
        'newTagTitle' => 'Bagong pamagat ng tag',
        'editingTagTitle' => 'Pamagat ng tag',
        'id' => 'Code ng Produkto',
        'product_code' => 'Code ng Produkto',
        'product_ids' => 'Mga Akda',
        'product_ids.*' => 'Akda',
        'title' => 'Pamagat',
        'name' => 'Pangalan',
        'email' => 'Email address',
        'password' => 'Password',
        'password_confirmation' => 'Kumpirmasyon ng password',
        'current_password' => 'Kasalukuyang password',
        'language' => 'Wika ng UI',
        'progress' => 'Progreso',
        'score' => 'Iskor',
        'age_category' => 'Kategorya ng Edad',
        'work_image' => 'Larawan ng Pabalat',
        'sample_images' => 'Mga Sample na Larawan',
        'sample_images.*' => 'Sample na Larawan',
        'sample_image_order' => 'Ayos ng mga sample na larawan',
        'description_japanese' => 'Deskripsiyong Hapones',
        'description_localized' => 'Deskripsiyong Naka-localize',
        'tags' => 'Mga Tag',
        'notes' => 'Mga Tala',
        'start_date' => 'Petsa ng Simula',
        'finish_date' => 'Petsa ng Pagtatapos',
        're_listen_value' => 'Halaga ng Muling Pakikinig',
        'priority' => 'Priyoridad',
        'newGroupTitle' => 'Bagong pamagat ng grupo',
        'mode' => 'Mode',
        'theme' => 'Tema',
        'sizeChoice' => 'Pinakamalaking laki bawat ZIP',
        'customMib' => 'Pinakamalaking laki bawat ZIP (MiB)',
        'tagOrder' => 'Ayos ng mga mungkahing tag',
        'seriesOrder' => 'Ayos ng mga mungkahi sa serye',
        'focus' => 'Mga Salita ng UI',
        'archive' => 'ZIP file',
    ],
];
