<?php

// Laravel validation messages are based on the Turkish translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/tr).
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

    'accepted' => ':attribute kabul edilmelidir.',
    'accepted_if' => ':attribute, :other :value olduğunda kabul edilmelidir.',
    'active_url' => ':attribute geçerli bir URL olmalıdır.',
    'after' => ':attribute, :date tarihinden sonraki bir tarih olmalıdır.',
    'after_or_equal' => ':attribute, :date tarihinden sonra veya bu tarihe eşit olmalıdır.',
    'alpha' => ':attribute yalnızca harflerden oluşmalıdır.',
    'alpha_dash' => ':attribute yalnızca harf, rakam, tire ve alt çizgi içermelidir.',
    'alpha_num' => ':attribute yalnızca harf ve rakam içermelidir.',
    'any_of' => ':attribute geçersizdir.',
    'array' => ':attribute bir dizi olmalıdır.',
    'ascii' => ':attribute yalnızca tek baytlık alfasayısal karakterler ve semboller içermelidir.',
    'before' => ':attribute, :date tarihinden önceki bir tarih olmalıdır.',
    'before_or_equal' => ':attribute, :date tarihinden önce veya bu tarihe eşit olmalıdır.',
    'between' => [
        'array' => ':attribute :min ile :max arasında öğe içermelidir.',
        'file' => ':attribute :min ile :max kilobayt arasında olmalıdır.',
        'numeric' => ':attribute :min ile :max arasında olmalıdır.',
        'string' => ':attribute :min ile :max karakter arasında olmalıdır.',
    ],
    'boolean' => ':attribute doğru veya yanlış olmalıdır.',
    'can' => ':attribute yetkisiz bir değer içeriyor.',
    'confirmed' => ':attribute doğrulaması eşleşmiyor.',
    'contains' => ':attribute gerekli bir değeri içermiyor.',
    'current_password' => 'Parola yanlış.',
    'date' => ':attribute geçerli bir tarih olmalıdır.',
    'date_equals' => ':attribute, :date tarihine eşit olmalıdır.',
    'date_format' => ':attribute :format biçimiyle eşleşmiyor.',
    'decimal' => ':attribute :decimal ondalık basamağa sahip olmalıdır.',
    'declined' => ':attribute reddedilmelidir.',
    'declined_if' => ':attribute, :other :value olduğunda reddedilmelidir.',
    'different' => ':attribute ile :other farklı olmalıdır.',
    'digits' => ':attribute :digits basamaklı olmalıdır.',
    'digits_between' => ':attribute :min ile :max basamak arasında olmalıdır.',
    'dimensions' => ':attribute geçersiz görsel boyutlarına sahip.',
    'distinct' => ':attribute yinelenen bir değer içeriyor.',
    'doesnt_contain' => ':attribute şu değerlerden hiçbirini içermemelidir: :values.',
    'doesnt_end_with' => ':attribute şu değerlerden biriyle bitmemelidir: :values.',
    'doesnt_start_with' => ':attribute şu değerlerden biriyle başlamamalıdır: :values.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'encoding' => ':attribute :encoding olarak kodlanmalıdır.',
    'ends_with' => ':attribute şu değerlerden biriyle bitmelidir: :values.',
    'enum' => 'Seçilen :attribute geçersiz.',
    'exists' => 'Seçilen :attribute geçersiz.',
    'extensions' => ':attribute şu uzantılardan birine sahip olmalıdır: :values.',
    'file' => ':attribute bir dosya olmalıdır.',
    'filled' => ':attribute bir değere sahip olmalıdır.',
    'gt' => [
        'array' => ':attribute :value öğeden daha fazlasını içermelidir.',
        'file' => ':attribute :value kilobayttan büyük olmalıdır.',
        'numeric' => ':attribute :value değerinden büyük olmalıdır.',
        'string' => ':attribute :value karakterden uzun olmalıdır.',
    ],
    'gte' => [
        'array' => ':attribute en az :value öğe içermelidir.',
        'file' => ':attribute :value kilobayt veya daha büyük olmalıdır.',
        'numeric' => ':attribute :value veya daha büyük olmalıdır.',
        'string' => ':attribute en az :value karakter olmalıdır.',
    ],
    'hex_color' => ':attribute geçerli bir onaltılık renk olmalıdır.',
    'image' => ':attribute bir görsel olmalıdır.',
    'in' => 'Seçilen :attribute geçersiz.',
    'in_array' => ':attribute, :other içinde bulunmalıdır.',
    'in_array_keys' => ':attribute şu anahtarlardan en az birini içermelidir: :values.',
    'integer' => ':attribute bir tam sayı olmalıdır.',
    'ip' => ':attribute geçerli bir IP adresi olmalıdır.',
    'ipv4' => ':attribute geçerli bir IPv4 adresi olmalıdır.',
    'ipv6' => ':attribute geçerli bir IPv6 adresi olmalıdır.',
    'json' => ':attribute geçerli bir JSON metni olmalıdır.',
    'list' => ':attribute bir liste olmalıdır.',
    'lowercase' => ':attribute küçük harf olmalıdır.',
    'lt' => [
        'array' => ':attribute :value öğeden daha azını içermelidir.',
        'file' => ':attribute :value kilobayttan küçük olmalıdır.',
        'numeric' => ':attribute :value değerinden küçük olmalıdır.',
        'string' => ':attribute :value karakterden kısa olmalıdır.',
    ],
    'lte' => [
        'array' => ':attribute en fazla :value öğe içermelidir.',
        'file' => ':attribute :value kilobayt veya daha küçük olmalıdır.',
        'numeric' => ':attribute :value veya daha küçük olmalıdır.',
        'string' => ':attribute en fazla :value karakter olmalıdır.',
    ],
    'mac_address' => ':attribute geçerli bir MAC adresi olmalıdır.',
    'max' => [
        'array' => ':attribute en fazla :max öğe içermelidir.',
        'file' => ':attribute en fazla :max kilobayt olmalıdır.',
        'numeric' => ':attribute en fazla :max olmalıdır.',
        'string' => ':attribute en fazla :max karakter olmalıdır.',
    ],
    'max_digits' => ':attribute en fazla :max basamak içermelidir.',
    'mimes' => ':attribute şu türlerden bir dosya olmalıdır: :values.',
    'mimetypes' => ':attribute şu türlerden bir dosya olmalıdır: :values.',
    'min' => [
        'array' => ':attribute en az :min öğe içermelidir.',
        'file' => ':attribute en az :min kilobayt olmalıdır.',
        'numeric' => ':attribute en az :min olmalıdır.',
        'string' => ':attribute en az :min karakter olmalıdır.',
    ],
    'min_digits' => ':attribute en az :min basamak içermelidir.',
    'missing' => ':attribute alanı bulunmamalıdır.',
    'missing_if' => ':attribute, :other :value olduğunda bulunmamalıdır.',
    'missing_unless' => ':attribute, :other :value olmadığı sürece bulunmamalıdır.',
    'missing_with' => ':attribute, :values mevcut olduğunda bulunmamalıdır.',
    'missing_with_all' => ':attribute, :values değerlerinin tümü mevcut olduğunda bulunmamalıdır.',
    'multiple_of' => ':attribute :value değerinin katı olmalıdır.',
    'not_in' => 'Seçilen :attribute geçersiz.',
    'not_regex' => ':attribute biçimi geçersiz.',
    'numeric' => ':attribute bir sayı olmalıdır.',
    'password' => [
        'letters' => ':attribute en az bir harf içermelidir.',
        'mixed' => ':attribute en az bir büyük ve bir küçük harf içermelidir.',
        'numbers' => ':attribute en az bir rakam içermelidir.',
        'symbols' => ':attribute en az bir sembol içermelidir.',
        'uncompromised' => ':attribute bir veri sızıntısında bulundu. Lütfen farklı bir :attribute seçin.',
    ],
    'present' => ':attribute alanı mevcut olmalıdır.',
    'present_if' => ':attribute, :other :value olduğunda mevcut olmalıdır.',
    'present_unless' => ':attribute, :other :value olmadığı sürece mevcut olmalıdır.',
    'present_with' => ':attribute, :values mevcut olduğunda mevcut olmalıdır.',
    'present_with_all' => ':attribute, :values değerlerinin tümü mevcut olduğunda mevcut olmalıdır.',
    'prohibited' => ':attribute yasaktır.',
    'prohibited_if' => ':attribute, :other :value olduğunda yasaktır.',
    'prohibited_if_accepted' => ':attribute, :other kabul edildiğinde yasaktır.',
    'prohibited_if_declined' => ':attribute, :other reddedildiğinde yasaktır.',
    'prohibited_unless' => ':attribute, :other :values içinde olmadığı sürece yasaktır.',
    'prohibits' => ':attribute, :other alanının bulunmasını yasaklar.',
    'regex' => ':attribute biçimi geçersiz.',
    'required' => ':attribute alanı zorunludur.',
    'required_array_keys' => ':attribute şu anahtarları içermelidir: :values.',
    'required_if' => ':attribute, :other :value olduğunda zorunludur.',
    'required_if_accepted' => ':attribute, :other kabul edildiğinde zorunludur.',
    'required_if_declined' => ':attribute, :other reddedildiğinde zorunludur.',
    'required_unless' => ':attribute, :other :values içinde olmadığı sürece zorunludur.',
    'required_with' => ':attribute, :values mevcut olduğunda zorunludur.',
    'required_with_all' => ':attribute, :values değerlerinin tümü mevcut olduğunda zorunludur.',
    'required_without' => ':attribute, :values mevcut olmadığında zorunludur.',
    'required_without_all' => ':attribute, :values değerlerinin hiçbiri mevcut olmadığında zorunludur.',
    'same' => ':attribute ile :other eşleşmelidir.',
    'size' => [
        'array' => ':attribute :size öğe içermelidir.',
        'file' => ':attribute :size kilobayt olmalıdır.',
        'numeric' => ':attribute :size olmalıdır.',
        'string' => ':attribute :size karakter olmalıdır.',
    ],
    'starts_with' => ':attribute şu değerlerden biriyle başlamalıdır: :values.',
    'string' => ':attribute bir metin olmalıdır.',
    'timezone' => ':attribute geçerli bir saat dilimi olmalıdır.',
    'unique' => ':attribute daha önce alınmış.',
    'uploaded' => ':attribute yüklenemedi.',
    'uppercase' => ':attribute büyük harf olmalıdır.',
    'url' => ':attribute geçerli bir URL olmalıdır.',
    'ulid' => ':attribute geçerli bir ULID olmalıdır.',
    'uuid' => ':attribute geçerli bir UUID olmalıdır.',

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
            'required' => 'Özel sütun genişliği gereklidir.',
            'regex' => 'Geçersiz sütun genişliği biçimi. px, rem, em, %, veya vw kullanın.',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'Sütun genişliği modu gereklidir.',
            'in' => 'Seçilen sütun genişliği modu geçersiz.',
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
        'username' => 'Kullanıcı adı',
        'remember' => 'Beni hatırla',
        'work_name' => 'Japonca Başlık',
        'metadata_language' => 'DLsite meta veri dili',
        'localized_title' => 'Yerelleştirilmiş başlık',
        'series' => 'Seri',
        'product_code_list' => 'Ürün kodu veya bağlantı',
        'product_codes' => 'Ürün kodları',
        'product_codes.*' => 'Ürün Kodu',
        'product_format' => 'Ürün Formatı',
        'product_format.*' => 'Ürün Formatı',
        'circle' => 'Circle',
        'publisher' => 'Yayıncı',
        'brand' => 'Marka',
        'maker_id' => 'Yapımcı kimliği',
        'scenario' => 'Senaryo yazarı',
        'voice_actor' => 'Seslendirme sanatçısı',
        'illustration' => 'İllüstrasyon sanatçısı',
        'author' => 'Yazar',
        'genre_custom' => 'Özel Etiketler',
        'description' => 'Japonca açıklama',
        'end_date' => 'Bitiş tarihi',
        'add.start_date.year' => 'Başlangıç yılı',
        'add.start_date.month' => 'Başlangıç ayı',
        'add.start_date.day' => 'Başlangıç günü',
        'add.finish_date.year' => 'Bitiş yılı',
        'add.finish_date.month' => 'Bitiş ayı',
        'add.finish_date.day' => 'Bitiş günü',
        'num_re_listen_times' => 'Yeniden dinleme sayısı',
        'currentPassword' => 'Mevcut parola',
        'newPassword' => 'Yeni parola',
        'newPasswordConfirmation' => 'Yeni parola onayı',
        'followFetchedTagsLanguage' => 'UI dilini otomatik takip et',
        'enabled' => 'Etkin durumu',
        'customValue' => 'Özel değer',
        'newTagTitle' => 'Yeni etiket başlığı',
        'editingTagTitle' => 'Etiket başlığı',
        'id' => 'Ürün Kodu',
        'product_code' => 'Ürün Kodu',
        'product_ids' => 'Çalışmalar',
        'product_ids.*' => 'Çalışma',
        'title' => 'Başlık',
        'name' => 'Ad',
        'email' => 'E-posta adresi',
        'password' => 'Parola',
        'password_confirmation' => 'Parola onayı',
        'current_password' => 'Mevcut parola',
        'language' => 'Arayüz Dili',
        'progress' => 'İlerleme',
        'score' => 'Puan',
        'age_category' => 'Yaş kategorisi',
        'work_image' => 'Kapak Görseli',
        'sample_images' => 'Örnek Görseller',
        'sample_images.*' => 'Örnek görsel',
        'sample_image_order' => 'Örnek görsel sırası',
        'description_japanese' => 'Japonca Açıklama',
        'description_localized' => 'Yerelleştirilmiş Açıklama',
        'tags' => 'Etiketler',
        'notes' => 'Notlar',
        'start_date' => 'Başlangıç tarihi',
        'finish_date' => 'Bitiş Tarihi',
        're_listen_value' => 'Yeniden Dinleme Değeri',
        'priority' => 'Öncelik',
        'newGroupTitle' => 'Yeni grup başlığı',
        'mode' => 'Mod',
        'theme' => 'Tema',
        'sizeChoice' => 'ZIP başına maksimum boyut',
        'customMib' => 'ZIP başına maksimum boyut (MiB)',
        'tagOrder' => 'Etiket önerileri sırası',
        'seriesOrder' => 'Seri önerileri sırası',
        'focus' => 'Arayüz İfadeleri',
        'archive' => 'ZIP dosyası',
    ],
];
