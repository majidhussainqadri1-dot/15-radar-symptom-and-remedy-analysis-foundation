<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_PII_Scanner
{
    private const MAX_DEPTH = 8;
    private const MAX_NODES = 500;
    private const MAX_BYTES = 65536;

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

    /** @param mixed $value @return array{safe: bool, categories: array<int, string>} */
    public static function scan($value): array
    {
        $state = ['nodes' => 0, 'bytes' => 0, 'too_deep' => false, 'too_large' => false];
        $text = self::flatten($value, 0, $state);
        $categories = [];
        if ($state['too_deep']) {
            $categories[] = 'input_too_complex';
        }
        if ($state['too_large']) {
            $categories[] = 'input_too_large';
        }
        foreach (self::patterns() as $category => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $categories[] = $category;
            }
        }
        $categories = array_values(array_unique($categories));
        return ['safe' => $categories === [], 'categories' => $categories];
    }

    /** @param mixed $value @param array<string,mixed> $state */
    private static function flatten($value, int $depth, array &$state): string
    {
        $state['nodes']++;
        if ($depth > self::MAX_DEPTH || $state['nodes'] > self::MAX_NODES) {
            $state['too_deep'] = true;
            return '';
        }
        if (is_scalar($value) || $value === null) {
            $text = (string)$value;
            $remaining = max(0, self::MAX_BYTES - (int)$state['bytes']);
            if (strlen($text) > $remaining) {
                $state['too_large'] = true;
                $text = substr($text, 0, $remaining);
            }
            $state['bytes'] += strlen($text);
            return $text;
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return '';
        }
        $parts = [];
        foreach ($value as $key => $item) {
            if ($state['too_large'] || $state['too_deep']) {
                break;
            }
            $parts[] = self::flatten((string)$key, $depth + 1, $state);
            $parts[] = self::flatten($item, $depth + 1, $state);
        }
        return implode("\n", $parts);
    }
}
