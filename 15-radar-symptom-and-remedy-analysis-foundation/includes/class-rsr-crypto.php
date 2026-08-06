<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

/** Field-level authenticated encryption for private study notes. */
final class RSR_Crypto
{
    private const PREFIX_SODIUM = 's1:';
    private const PREFIX_OPENSSL = 'o1:';

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = self::key();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return self::PREFIX_SODIUM . base64_encode($nonce . $cipher);
        }

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('No supported encryption extension is available.');
        }

        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }
        return self::PREFIX_OPENSSL . base64_encode($nonce . $tag . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }

        $key = self::key();
        if (str_starts_with($encoded, self::PREFIX_SODIUM)) {
            if (!function_exists('sodium_crypto_secretbox_open')) {
                throw new RuntimeException('Sodium is required to decrypt this study.');
            }
            $raw = base64_decode(substr($encoded, strlen(self::PREFIX_SODIUM)), true);
            if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                throw new RuntimeException('Encrypted study data is invalid.');
            }
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            if ($plain === false) {
                throw new RuntimeException('Encrypted study authentication failed.');
            }
            return $plain;
        }

        if (str_starts_with($encoded, self::PREFIX_OPENSSL)) {
            if (!function_exists('openssl_decrypt')) {
                throw new RuntimeException('OpenSSL is required to decrypt this study.');
            }
            $raw = base64_decode(substr($encoded, strlen(self::PREFIX_OPENSSL)), true);
            if ($raw === false || strlen($raw) <= 28) {
                throw new RuntimeException('Encrypted study data is invalid.');
            }
            $nonce = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);
            $plain = openssl_decrypt(
                $cipher,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag
            );
            if ($plain === false) {
                throw new RuntimeException('Encrypted study authentication failed.');
            }
            return $plain;
        }

        throw new RuntimeException('Unknown encrypted study format.');
    }

    public static function key_fingerprint(): string
    {
        return substr(hash('sha256', self::key()), 0, 16);
    }

    private static function key(): string
    {
        $material = '';
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'] as $constant) {
            if (defined($constant)) {
                $material .= (string)constant($constant);
            }
        }
        if ($material === '') {
            $material = defined('RSR_TEST_KEY') ? (string)RSR_TEST_KEY : 'rsr-test-only-key-material';
        }
        return hash_hkdf('sha256', $material, 32, 'rsr-private-study-v1', 'sabri-platform-file-15');
    }
}
