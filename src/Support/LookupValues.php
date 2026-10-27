<?php

namespace AmrSaleh\FasahPay\Support;

class LookupValues
{
    public const PORTS = [
        '10' => 'جمرك ميناء جده الاسلامي',
        '17' => 'جمرك ميناء ينبع التجاري',
        '19' => 'جمرك ميناء الملك فهد الصناعي',
        '30' => 'جمرك ميناء الملك عبدالعزيز',
        '32' => 'جمرك ميناء رأس تنوره',
        '33' => 'جمرك رأس الخير',
        '42' => 'جمرك ميناء الجبيل',
        '43' => 'جمرك راس مشعاب',
        '45' => 'جمرك ميناء الجبيل الصناعي',
        '53' => 'جمرك ميناء ضباء',
        '60' => 'جمرك ميناء جيزان',
        '75' => 'جمرك ميناء الملك عبد الله',
        '96' => 'جمرك ميناء رابغ',
        '34' => 'جمرك الرياض - الميناء الجاف',
        '31' => 'جمرك البطحاء',
        '35' => 'جمرك الرقعي',
        '36' => 'جمرك العديد',
        '41' => 'جمرك سلوى',
        '44' => 'جمرك الخفجي',
        '51' => 'جمرك حالة عمار',
        '54' => 'جمرك الدره',
        '61' => 'جمرك الطوال',
        '62' => 'جمرك الموسم',
        '63' => 'جمرك الخوبة',
        '66' => 'جمرك الوديعة',
        '67' => 'جمرك علب',
        '69' => 'جمرك الخضراء',
        '83' => 'جمرك جسر الملك فهد',
        '84' => 'جمرك فرسان',
        '90' => 'جمرك جديدة عرعر',
        '91' => 'جمرك طريف',
        '95' => 'جمرك الحديثة',
        '100' => 'جمرك مطار حائل',
        '11' => 'جمرك مطارالملك عبدالعزيزالدولي',
        '12' => 'جمرك مطار نجران',
        '13' => 'جمرك مطار نيوم',
        '14' => 'جمرك قاعدة الأمير سلطان الجوية',
        '18' => 'جمرك مطار ينبع',
        '20' => 'جمرك مطار الملك فهد الدولي',
        '21' => 'مطار الأمير محمد بن عبدالعزيز',
        '22' => 'جمرك مطار القصيم',
        '23' => 'جمرك مطار الملك خالد الدولي',
        '24' => 'جمرك مطار الطائف',
        '25' => 'جمرك مطار حائل',
        '29' => 'جمرك مطار الأحساء الدولي',
        '50' => 'جمرك مطار تبوك',
        '64' => 'مطار جازان',
        '71' => 'جمرك مطار أبها',
        '72' => 'جمرك مطار شروره',
        '94' => 'جمرك مطار الجوف',
        '26' => 'جمرك الطرود البريدية بجدة',
        '27' => 'جمرك الطرود البريدية بالمدينة',
        '28' => 'جمرك الرياض للبريد',
        '38' => 'جمرك الطرود البريدية بالدمام',
        '46' => 'جمرك الطرود البريدية بمكة',
    ];

    public const SHIPMENT_TYPES = [
        1 => 'Import', 2 => 'Export', 3 => 'Oil', 4 => 'Transit import',
        5 => 'Code 5', 9 => 'Code 9', 11 => 'Code 11', 12 => 'Code 12',
        15 => 'Code 15', 16 => 'Code 16', 98 => 'Code 98', 99 => 'Code 99',
    ];

    public const DECLARATION_TYPES = [
        1 => 'Import', 2 => 'Direct import', 3 => 'Export', 4 => 'Re-export',
        5 => 'Oil export', 6 => 'Personal export (disputed)', 8 => 'Statistical transit (disputed)',
        9 => 'Bonded-zone export', 11 => 'Import from outside GCC', 12 => 'Export to outside GCC',
        13 => 'Statistical transit (disputed)', 15 => 'GCC declaration', 16 => 'Export of GCC origin',
        98 => 'Bonded export from outside GCC', 99 => 'Bonded export from GCC',
    ];

    public const LICENSE_TYPES = [1 => 'Government broker', 2 => 'General broker', 5 => 'Customs clearance company'];

    public const CONFIRMED_TYPES = [1, 2, 3, 4, 5, 9, 11, 12, 15, 16, 98, 99];

    public const DISPUTED_DECLARATION_TYPES = [6, 8, 13];

    public static function isKnownPort(string $port): bool
    {
        return array_key_exists($port, self::PORTS);
    }

    /**
     * Normalize a Saudi mobile to 05XXXXXXXX, or null if invalid.
     */
    public static function normalizeMobile(string $mobile): ?string
    {
        $compact = preg_replace('/[\s().-]+/u', '', trim($mobile)) ?? trim($mobile);
        $local = preg_replace('/^(?:\+?966|00966)/', '0', $compact) ?? $compact;

        return preg_match('/^05[0-9]{8}$/D', $local) === 1 ? $local : null;
    }
}
