<?php

// Laravel validation messages are based on the Thai translations maintained by Laravel-Lang (https://github.com/Laravel-Lang/lang/tree/main/locales/th).
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

    'accepted' => 'ข้อมูล :attribute ต้องผ่านการยอมรับก่อน',
    'accepted_if' => 'ข้อมูล :attribute ต้องผ่านการยอมรับ เมื่อ :other เป็น :value',
    'active_url' => 'ข้อมูล :attribute ไม่ใช่ URL ที่ถูกรูปแบบ',
    'after' => 'ข้อมูล :attribute ต้องเป็นวันที่หลังจาก :date',
    'after_or_equal' => 'ข้อมูล :attribute ต้องเป็นวันที่ตั้งแต่วันที่ :date หรือหลังจากนั้น',
    'alpha' => 'ข้อมูล :attribute ต้องเป็นตัวอักษรภาษาอังกฤษเท่านั้น',
    'alpha_dash' => 'ข้อมูล :attribute ต้องเป็นตัวอักษรภาษาอังกฤษ ตัวเลข และ _ เท่านั้น',
    'alpha_num' => 'ข้อมูล :attribute ต้องเป็นตัวอักษรภาษาอังกฤษ ตัวเลข เท่านั้น',
    'any_of' => 'ช่องข้อมูล :attribute ไม่ถูกต้อง',
    'array' => 'ข้อมูล :attribute ต้องเป็น array เท่านั้น',
    'ascii' => ':attribute ต้องมีอักขระและสัญลักษณ์ที่เป็นตัวอักษรและตัวเลขคละกันแบบไบต์เดี่ยวเท่านั้น',
    'before' => 'ข้อมูล :attribute ต้องเป็นวันที่ก่อน :date',
    'before_or_equal' => 'ข้อมูล :attribute ต้องเป็นวันที่ก่อนหรือเท่ากับวันที่ :date',
    'between' => [
        'array' => 'ข้อมูล :attribute ต้องมีค่าระหว่าง :min - :max ค่า',
        'file' => 'ข้อมูล :attribute ต้องมีขนาดระหว่าง :min - :max กิโลไบต์',
        'numeric' => 'ข้อมูล :attribute ต้องอยู่ในช่วงระหว่าง :min - :max',
        'string' => 'ข้อมูล :attribute ต้องยาวระหว่าง :min - :max ตัวอักษร',
    ],
    'boolean' => 'ข้อมูล :attribute ต้องเป็นจริงหรือเท็จเท่านั้น',
    'can' => 'ช่องข้อมูล :attribute มีค่าที่ไม่ได้รับอนุญาต',
    'confirmed' => 'ข้อมูล :attribute ไม่ตรงกัน',
    'contains' => 'ช่องข้อมูล :attribute นี้ยังขาดข้อมูลที่ต้องกรอก',
    'current_password' => 'รหัสผ่านไม่ถูกต้อง',
    'date' => 'ข้อมูล :attribute ต้องเป็นวันที่',
    'date_equals' => 'ข้อมูล :attribute ต้องเป็นวันที่ที่เท่ากับ :date',
    'date_format' => 'ข้อมูล :attribute ไม่ตรงกับข้อมูล :format ที่กำหนด',
    'decimal' => ':attribute ต้องมีทศนิยม :decimal ตำแหน่ง',
    'declined' => 'ข้อมูล :attribute ต้องถูกปฏิเสธ',
    'declined_if' => 'ข้อมูล :attribute ต้องถูกปฏิเสธเมื่อ :other มีค่าเท่ากับ :value',
    'different' => 'ข้อมูล :attribute และ :other ต้องไม่เท่ากัน',
    'digits' => 'ข้อมูล :attribute ต้องเป็น :digits',
    'digits_between' => 'ข้อมูล :attribute ต้องอยู่ในช่วงระหว่าง :min ถึง :max',
    'dimensions' => 'ข้อมูล :attribute มีขนาดไม่ถูกต้อง',
    'distinct' => 'ข้อมูล :attribute มีค่าที่ซ้ำกัน',
    'doesnt_contain' => 'ช่องข้อมูล :attribute ต้องไม่มีสิ่งต่อไปนี้: :values',
    'doesnt_end_with' => 'ข้อมูล :attribute ไม่ควรลงท้ายด้วยค่าดังต่อไปนี้: :values',
    'doesnt_start_with' => 'ข้อมูล :attribute ไม่ควรเริ่มต้นด้วยค่าดังต่อไปนี้: :values',
    'email' => 'ข้อมูล :attribute ต้องเป็นอีเมล์',
    'encoding' => ':attribute ต้องเป็น เข้ารหัสด้วย :encoding',
    'ends_with' => 'ข้อมูล :attribute ต้องจบด้วยค่าดังต่อไปนี้: :values',
    'enum' => 'ข้อมูล :attribute ที่เลือกไม่ถูกต้อง',
    'exists' => 'ข้อมูล :attribute ที่เลือกไม่ถูกต้อง',
    'extensions' => 'ช่อง :attribute ต้องมีส่วนขยายอย่างใดอย่างหนึ่งต่อไปนี้: :values',
    'file' => 'ข้อมูล :attribute ต้องเป็นไฟล์',
    'filled' => 'จำเป็นต้องกรอกข้อมูล :attribute',
    'gt' => [
        'array' => 'ข้อมูล :attribute ต้องมีค่ามากกว่า :value',
        'file' => 'ข้อมูล :attribute ต้องมีขนาดมากกว่า :value กิโลไบต์',
        'numeric' => 'ข้อมูล :attribute ต้องมีค่ามากกว่า :value',
        'string' => 'ข้อมูล :attribute ต้องยาวมากกว่า :value ตัวอักษร',
    ],
    'gte' => [
        'array' => 'ข้อมูล :attribute ต้องมีค่า :value หรือมากกว่า',
        'file' => 'ข้อมูล :attribute ต้องมีขนาดมากกว่าหรือเท่ากับ :value กิโลไบต์',
        'numeric' => 'ข้อมูล :attribute ต้องมีค่ามากกว่าหรือเท่ากับ :value',
        'string' => 'ข้อมูล :attribute ต้องยาวมากกว่าหรือเท่ากับ :value ตัวอักษร',
    ],
    'hex_color' => 'ช่อง :attribute ต้องเป็นสีเลขฐานสิบหกที่ถูกต้อง',
    'image' => 'ข้อมูล :attribute ต้องเป็นรูปภาพ',
    'in' => 'ข้อมูลที่ถูกเลือกใน :attribute ไม่ถูกต้อง',
    'in_array' => 'ข้อมูล :attribute ไม่มีอยู่ภายในค่าของ :other',
    'in_array_keys' => 'ช่องข้อมูล :attribute ต้องมีคีย์อย่างน้อยหนึ่งรายการดังต่อไปนี้: :values',
    'integer' => 'ข้อมูล :attribute ต้องเป็นตัวเลข',
    'ip' => 'ข้อมูล :attribute ต้องเป็น IP',
    'ipv4' => 'ข้อมูล :attribute ต้องตรงตามรูปแบบที่อยู่ IPv4',
    'ipv6' => 'ข้อมูล :attribute ต้องตรงตามรูปแบบที่อยู่ IPv6',
    'json' => 'ข้อมูล :attribute ต้องเป็นอักขระ JSON ที่สมบูรณ์',
    'list' => 'ช่อง :attribute ต้องเป็นรายการ',
    'lowercase' => ':attribute ต้องเป็นตัวพิมพ์เล็ก',
    'lt' => [
        'array' => 'ข้อมูล :attribute ต้องมีน้อยกว่า :value ค่า',
        'file' => 'ข้อมูล :attribute ต้องมีขนาดน้อยกว่า :value กิโลไบต์',
        'numeric' => 'ข้อมูล :attribute ต้องมีค่าน้อยกว่า :value',
        'string' => 'ข้อมูล :attribute ต้องยาวน้อยกว่า :value ตัวอักษร',
    ],
    'lte' => [
        'array' => 'ข้อมูล :attribute ต้องมีไม่เกิน :value ค่า',
        'file' => 'ข้อมูล :attribute ต้องมีขนาดน้อยกว่าหรือเท่ากับ :value กิโลไบต์',
        'numeric' => 'ข้อมูล :attribute ต้องมีค่าน้อยกว่าหรือเท่ากับ :value',
        'string' => 'ข้อมูล :attribute ต้องยาวน้อยกว่าหรือเท่ากับ :value ตัวอักษร',
    ],
    'mac_address' => ':attribute ต้องเป็นที่อยู่ MAC ที่ถูกต้อง',
    'max' => [
        'array' => 'ข้อมูล :attribute ต้องมีไม่เกิน :max ค่า',
        'file' => 'ข้อมูล :attribute ต้องมีขนาดไม่เกิน :max กิโลไบต์',
        'numeric' => 'ข้อมูล :attribute ต้องมีค่าไม่เกิน :max',
        'string' => 'ข้อมูล :attribute ต้องยาวไม่เกิน :max ตัวอักษร',
    ],
    'max_digits' => ':attribute ต้องไม่เกิน :max หลัก',
    'mimes' => 'ข้อมูล :attribute ต้องเป็นชนิดแฟ้ม: :values',
    'mimetypes' => 'ข้อมูล :attribute ต้องเป็นชนิดแฟ้ม: :values',
    'min' => [
        'array' => 'ข้อมูล :attribute ต้องมีอย่างน้อย :min ค่า',
        'file' => 'ข้อมูล :attribute ต้องมีขนาดอย่างน้อย :min กิโลไบต์',
        'numeric' => 'ข้อมูล :attribute ต้องมีค่าอย่างน้อย :min',
        'string' => 'ข้อมูล :attribute ต้องยาวอย่างน้อย :min ตัวอักษร',
    ],
    'min_digits' => ':attribute ต้องมีอย่างน้อย :min หลัก',
    'missing' => 'ช่องข้อมูล :attribute ต้องหายไป',
    'missing_if' => 'ช่องข้อมูล :attribute ต้องหายไป เมื่อ :other เป็น :value',
    'missing_unless' => 'ช่องข้อมูล :attribute ต้องหายไป เว้นแต่ :other จะเป็น :value',
    'missing_with' => 'ช่องข้อมูล :attribute ต้องหายไป เมื่อมี :values',
    'missing_with_all' => 'ช่องข้อมูล :attribute ต้องหายไป เมื่อมี :values',
    'multiple_of' => 'ค่า :attribute จะต้องเป็นผลคูณของ :value',
    'not_in' => 'ข้อมูล ที่เลือกจาก :attribute ไม่ถูกต้อง',
    'not_regex' => 'ข้อมูล :attribute มีรูปแบบไม่ถูกต้อง',
    'numeric' => 'ข้อมูล :attribute ต้องเป็นตัวเลข',
    'password' => [
        'letters' => 'ข้อมูล :attribute ต้องประกอบด้วยตัวอักษรอย่างน้อย 1 ตัว',
        'mixed' => 'ข้อมูล :attribute ต้องประกอบด้วยอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว และอักษรพิมพ์เล็กอย่างน้อย 1 ตัว',
        'numbers' => 'ข้อมูล :attribute ต้องประกอบด้วยตัวเลขอย่างน้อย 1 ตัว',
        'symbols' => 'ข้อมูล :attribute ต้องประกอบด้วยสัญลักษณ์อย่างน้อย 1 ตัว',
        'uncompromised' => 'ข้อมูล :attribute นี้ถูกพบในข้อมูลที่รั่วไหล โปรดเลือก :attribute อื่น',
    ],
    'present' => 'ต้องมีช่องข้อมูล :attribute',
    'present_if' => 'ต้องมีช่องข้อมูล :attribute เมื่อ :other เป็น :value',
    'present_unless' => 'ต้องมีช่องข้อมูล :attribute เว้นแต่ :other อยู่ใน :value',
    'present_with' => 'ต้องมีช่องข้อมูล :attribute เมื่อมี :values',
    'present_with_all' => 'ต้องมีช่องข้อมูล :attribute เมื่อมี :values',
    'prohibited' => 'ช่องข้อมูล :attribute ถูกห้ามไม่ให้ใช้',
    'prohibited_if' => 'ช่องข้อมูล :attribute ถูกห้ามไม่ให้ใช้ เมื่อ :other เป็น :value',
    'prohibited_if_accepted' => 'ช่องข้อมูล :attribute ถูกห้ามไม่ให้ใช้ :other ถูกยอมรับ',
    'prohibited_if_declined' => 'ช่องข้อมูล :attribute ถูกห้ามไม่ให้ใช้ :other ถูกปฏิเสธ',
    'prohibited_unless' => 'ช่องข้อมูล :attribute ถูกห้ามไม่ให้ใช้ เว้นแต่ :other อยู่ใน :values',
    'prohibits' => 'ช่องข้อมูล :attribute กันไม่ให้ :other มีได้',
    'regex' => 'ข้อมูล :attribute มีรูปแบบไม่ถูกต้อง',
    'required' => 'ต้องกรอกช่องข้อมูล :attribute',
    'required_array_keys' => 'ช่องข้อมูล :attribute ต้องมีรายการสำหรับ: :values',
    'required_if' => 'ต้องกรอกช่องข้อมูล :attribute เมื่อ :other เป็น :value',
    'required_if_accepted' => 'ต้องกรอกช่องข้อมูล :attribute เมื่อยอมรับ :other',
    'required_if_declined' => 'ต้องกรอกช่องข้อมูล :attribute เมื่อ :other ถูกปฏิเสธ',
    'required_unless' => 'ต้องกรอกช่องข้อมูล :attribute เว้นแต่ :other อยู่ใน :values',
    'required_with' => 'ต้องกรอกช่องข้อมูล :attribute เมื่อมี :values',
    'required_with_all' => 'ต้องกรอกช่องข้อมูล :attribute เมื่อมี :values',
    'required_without' => 'ต้องกรอกช่องข้อมูล :attribute เมื่อไม่มี :values',
    'required_without_all' => 'ต้องกรอกช่องข้อมูล :attribute เมื่อไม่มี :values ทั้งหมด',
    'same' => 'ข้อมูล :attribute และ :other ต้องตรงกัน',
    'size' => [
        'array' => 'ข้อมูล :attribute ต้องเท่ากับ :size ค่า',
        'file' => 'ข้อมูล :attribute ต้องเท่ากับ :size กิโลไบต์',
        'numeric' => 'ข้อมูล :attribute ต้องเท่ากับ :size',
        'string' => 'ข้อมูล :attribute ต้องเท่ากับ :size ตัวอักษร',
    ],
    'starts_with' => 'ข้อมูล :attribute ต้องเริ่มด้วยค่าใดค่าหนึ่งต่อไปนี้: :values',
    'string' => 'ข้อมูล :attribute ต้องเป็นอักขระ',
    'timezone' => 'ข้อมูล :attribute ต้องเป็นข้อมูลเขตเวลาที่ถูกต้อง',
    'unique' => ':attribute ถูกใช้ไปแล้ว',
    'uploaded' => 'ไม่สามารถอัปโหลดข้อมูล :attribute ได้',
    'uppercase' => ':attribute ต้องเป็นตัวพิมพ์ใหญ่',
    'url' => 'ข้อมูล :attribute ต้องเป็น URL ที่ถูกรูปแบบ',
    'ulid' => ':attribute ต้องเป็น ULID ที่ถูกต้อง',
    'uuid' => 'ข้อมูล :attribute ต้องเป็นค่า UUID ที่ถูกรูปแบบ',

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
            'required' => 'ต้องระบุความกว้างคอลัมน์แบบกำหนดเอง',
            'regex' => 'รูปแบบความกว้างคอลัมน์ไม่ถูกต้อง ใช้ px, rem, em, % หรือ vw',
        ],
        'indexFields.*.width_mode' => [
            'required' => 'ต้องระบุโหมดความกว้างคอลัมน์',
            'in' => 'โหมดความกว้างคอลัมน์ที่เลือกไม่ถูกต้อง',
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
        'username' => 'ชื่อผู้ใช้',
        'remember' => 'จำฉันไว้',
        'work_name' => 'ชื่อภาษาญี่ปุ่น',
        'metadata_language' => 'ภาษาข้อมูลเมตาที่ต้องการ',
        'localized_title' => 'ชื่อที่แปลแล้ว',
        'series' => 'ซีรีส์',
        'product_code_list' => 'รหัสผลิตภัณฑ์หรือลิงก์',
        'product_codes' => 'รหัสผลิตภัณฑ์',
        'product_codes.*' => 'รหัสผลิตภัณฑ์',
        'product_format' => 'รูปแบบผลงาน',
        'product_format.*' => 'รูปแบบผลงาน',
        'circle' => 'เซอร์เคิล',
        'publisher' => 'สำนักพิมพ์',
        'brand' => 'แบรนด์',
        'maker_id' => 'ID ผู้สร้าง',
        'scenario' => 'ผู้เขียนบท',
        'voice_actor' => 'นักพากย์',
        'illustration' => 'นักวาดภาพประกอบ',
        'author' => 'ผู้เขียน',
        'genre_custom' => 'แท็กกำหนดเอง',
        'description' => 'คำอธิบายภาษาญี่ปุ่น',
        'end_date' => 'วันที่สิ้นสุด',
        'add.start_date.year' => 'ปีที่เริ่ม',
        'add.start_date.month' => 'เดือนที่เริ่ม',
        'add.start_date.day' => 'วันที่เริ่ม',
        'add.finish_date.year' => 'ปีที่เสร็จ',
        'add.finish_date.month' => 'เดือนที่เสร็จ',
        'add.finish_date.day' => 'วันที่เสร็จ',
        'num_re_listen_times' => 'จำนวนครั้งที่ฟังซ้ำทั้งหมด',
        'currentPassword' => 'รหัสผ่านปัจจุบัน',
        'newPassword' => 'รหัสผ่านใหม่',
        'newPasswordConfirmation' => 'ยืนยันรหัสผ่านใหม่',
        'followFetchedTagsLanguage' => 'ตามภาษา UI โดยอัตโนมัติ',
        'enabled' => 'การตั้งค่าที่เปิดใช้',
        'customValue' => 'ค่ากำหนดเอง',
        'newTagTitle' => 'ชื่อแท็กใหม่',
        'editingTagTitle' => 'ชื่อแท็ก',
        'id' => 'รหัสผลิตภัณฑ์',
        'product_code' => 'รหัสผลิตภัณฑ์',
        'product_ids' => 'ผลงาน',
        'product_ids.*' => 'ผลงาน',
        'title' => 'ชื่อเรื่อง',
        'name' => 'ชื่อ',
        'email' => 'ที่อยู่อีเมล',
        'password' => 'รหัสผ่าน',
        'password_confirmation' => 'ยืนยันรหัสผ่าน',
        'current_password' => 'รหัสผ่านปัจจุบัน',
        'language' => 'ภาษา UI',
        'progress' => 'ความคืบหน้า',
        'score' => 'คะแนน',
        'age_category' => 'อายุ',
        'work_image' => 'ภาพปก',
        'sample_images' => 'รูปภาพตัวอย่าง',
        'sample_images.*' => 'รูปภาพตัวอย่าง',
        'sample_image_order' => 'ลำดับรูปตัวอย่าง',
        'description_japanese' => 'คำอธิบายภาษาญี่ปุ่น',
        'description_localized' => 'คำอธิบายที่แปลแล้ว',
        'tags' => 'แท็ก',
        'notes' => 'บันทึก',
        'start_date' => 'วันที่เริ่ม',
        'finish_date' => 'วันที่สิ้นสุด',
        're_listen_value' => 'ค่าการฟังซ้ำ',
        'priority' => 'ลำดับความสำคัญ',
        'newGroupTitle' => 'ชื่อกลุ่มใหม่',
        'mode' => 'โหมดการแสดงผล',
        'theme' => 'ธีม',
        'sizeChoice' => 'ขนาดส่วน ZIP',
        'customMib' => 'ขนาดสูงสุดต่อ ZIP (MiB)',
        'tagOrder' => 'ลำดับแท็ก',
        'seriesOrder' => 'ลำดับซีรีส์',
        'focus' => 'ประเภทเนื้อหา',
        'archive' => 'ไฟล์ ZIP',
    ],
];
