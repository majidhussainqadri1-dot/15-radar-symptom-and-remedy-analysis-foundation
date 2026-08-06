<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

/** Field-level authenticated encryption for private study notes. */
final class RSR_Crypto
{
    private const PREFIX_SODIUM = 's2:';
    private const PREFIX_OPENSSL = 'o2:';
    private const LEGACY_SODIUM = 's1:';
    private const LEGACY_OPENSSL = 'o1:';

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key = self::current_key();
        $kid = self::fingerprint($key);
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return self::PREFIX_SODIUM . $kid . ':' . base64_encode($nonce . $cipher);
        }
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('No supported encryption extension is available.');
        }
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }
        return self::PREFIX_OPENSSL . $kid . ':' . base64_encode($nonce . $tag . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }
        foreach (self::key_ring() as $key) {
            try {
                return self::decrypt_with_key($encoded, $key);
            } catch (Throwable $error) {
                // Try the next explicitly configured previous key.
            }
        }
        throw new RuntimeException('Encrypted study authentication failed for all configured keys.');
    }

    public static function key_fingerprint(): string
    {
        return self::fingerprint(self::current_key());
    }

    /** @return array<int, string> */
    private static function key_ring(): array
    {
        $materials = [self::current_material()];
        $previous = [];
        if (defined('RSR_PRIVATE_STUDY_PREVIOUS_KEYS')) {
            $previous = (array)constant('RSR_PRIVATE_STUDY_PREVIOUS_KEYS');
        }
        if (function_exists('apply_filters')) {
            $previous = (array)apply_filters('rsr_private_study_previous_keys', $previous);
        }
        foreach ($previous as $material) {
            $material = trim((string)$material);
            if ($material !== '') {
                $materials[] = $material;
            }
        }
        $keys = [];
        foreach (array_values(array_unique($materials)) as $material) {
            $keys[] = self::derive($material);
        }
        return $keys;
    }

    private static function current_key(): string
    {
        return self::derive(self::current_material());
    }

    private static function current_material(): string
    {
        if (defined('RSR_TESTING') && defined('RSR_TEST_KEY')) {
            return (string)RSR_TEST_KEY;
        }
        $material = defined('RSR_PRIVATE_STUDY_KEY') ? trim((string)RSR_PRIVATE_STUDY_KEY) : '';
        if (function_exists('apply_filters')) {
            $material = trim((string)apply_filters('rsr_private_study_current_key', $material));
        }
        if ($material === '') {
            foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'] as $constant) {
                if (defined($constant)) {
                    $material .= (string)constant($constant);
                }
            }
        }
        if (strlen($material) < 32) {
            throw new RuntimeException('A production-grade private study encryption key is not configured.');
        }
        return $material;
    }

    private static function derive(string $material): string
    {
        return hash_hkdf('sha256', $material, 32, 'rsr-private-study-v2', 'sabri-platform-file-15');
    }

    private static function fingerprint(string $key): string
    {
        return substr(hash('sha256', $key), 0, 16);
    }

    private static function decrypt_with_key(string $encoded, string $key): string
    {
        $payload = $encoded;
        $sodium = false;
        $openssl = false;
        if (str_starts_with($encoded, self::PREFIX_SODIUM) || str_starts_with($encoded, self::PREFIX_OPENSSL)) {
            $parts = explode(':', $encoded, 3);
            if (count($parts) !== 3 || strlen($parts[1]) !== 16) {
                throw new RuntimeException('Encrypted study key identifier is invalid.');
            }
            if (!hash_equals($parts[1], self::fingerprint($key))) {
                throw new RuntimeException('Encrypted study key identifier does not match.');
            }
            $sodium = $parts[0] === 's2';
            $openssl = $parts[0] === 'o2';
            $payload = $parts[2];
        } elseif (str_starts_with($encoded, self::LEGACY_SODIUM)) {
            $sodium = true;
            $payload = substr($encoded, strlen(self::LEGACY_SODIUM));
        } elseif (str_starts_with($encoded, self::LEGACY_OPENSSL)) {
            $openssl = true;
            $payload = substr($encoded, strlen(self::LEGACY_OPENSSL));
        } else {
            throw new RuntimeException('Unknown encrypted study format.');
        }

        $raw = base64_decode($payload, true);
        if ($raw === false) {
            throw new RuntimeException('Encrypted study data is invalid.');
        }
        if ($sodium) {
            if (!function_exists('sodium_crypto_secretbox_open') || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                throw new RuntimeException('Sodium ciphertext is invalid or unsupported.');
            }
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
            if ($plain === false) {
                throw new RuntimeException('Sodium authentication failed.');
            }
            return $plain;
        }
        if ($openssl) {
            if (!function_exists('openssl_decrypt') || strlen($raw) <= 28) {
                throw new RuntimeException('OpenSSL ciphertext is invalid or unsupported.');
            }
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            if ($plain === false) {
                throw new RuntimeException('OpenSSL authentication failed.');
            }
            return $plain;
        }
        throw new RuntimeException('Unsupported encrypted study format.');
    }
}
