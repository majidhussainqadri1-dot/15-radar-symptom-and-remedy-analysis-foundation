<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

/**
 * Conservative scanner that prevents private Radar studies from becoming a
 * surrogate patient record. It is intentionally strict and returns categories,
 * never the matched secret itself.
 */
final class RSR_PII_Scanner
{
    /** @return array<string, string> */
    public static function patterns(): array
    {
        return [
            'email' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu',
            'phone' => '/(?<!\d)(?:\+?\d[\d\s().-]{7,}\d)(?!\d)/u',
            'pakistan_cnic' => '/(?<!\d)\d{5}-?\d{7}-?\d(?!\d)/u',
            'passport' => '/\b(?:passport|پاسپورٹ)\s*(?:no\.?|number|نمبر)?\s*[:#-]?\s*[A-Z0-9]{5,15}\b/iu',
            'medical_record' => '/\b(?:mrn|medical\s*record|patient\s*id|case\s*no\.?|مریض\s*(?:نمبر|نام)|رجسٹریشن\s*نمبر)\b/iu',
            'street_address' => '/\b(?:house|flat|street|road|lane|block|sector|گھر\s*نمبر|گلی\s*نمبر|مکان\s*نمبر)\s*[#:-]?\s*[A-Z0-9-]{1,12}\b/iu',
            'birth_date' => '/\b(?:date\s*of\s*birth|dob|تاریخ\s*پیدائش)\b/iu',
            'patient_name_label' => '/\b(?:patient\s*name|name\s*of\s*patient|مریض\s*کا\s*نام|نام\s*مریض)\b/iu',
        ];
    }

    /**
     * @param mixed $value
     * @return array{safe: bool, categories: array<int, string>}
     */
    public static function scan($value): array
    {
        $text = self::flatten($value);
        $categories = [];

        foreach (self::patterns() as $category => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $categories[] = $category;
            }
        }

        return [
            'safe' => $categories === [],
            'categories' => array_values(array_unique($categories)),
        ];
    }

    /** @param mixed $value */
    private static function flatten($value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string)$value;
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return '';
        }

        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = (string)$key;
            $parts[] = self::flatten($item);
        }
        return implode("\n", $parts);
    }
}
