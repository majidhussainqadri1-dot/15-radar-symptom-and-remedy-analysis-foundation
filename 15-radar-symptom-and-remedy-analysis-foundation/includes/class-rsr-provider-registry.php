<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

interface RSR_Trend_Provider
{
    public function key(): string;

    /** @return array<string, mixed> */
    public function capabilities(): array;

    /**
     * @param array<string, mixed> $source
     * @param array<string, string> $window
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function collect(array $source, array $window, array $context = []): array;
}

final class RSR_Provider_Registry
{
    /** @var array<string, RSR_Trend_Provider>|null */
    private static ?array $providers = null;

    public static function register(RSR_Trend_Provider $provider): void
    {
        $key = sanitize_key($provider->key());
        $capabilities = $provider->capabilities();
        $valid = $key !== ''
            && isset($capabilities['windows'], $capabilities['privacy'], $capabilities['network'])
            && is_array($capabilities['windows'])
            && $capabilities['privacy'] === 'aggregated_only'
            && array_diff($capabilities['windows'], ['daily', 'weekly', 'monthly', 'yearly']) === [];
        if (!$valid) {
            throw new InvalidArgumentException('Invalid File 15 trend provider contract.');
        }
        if (!empty($capabilities['network'])) {
            foreach (['timeout_seconds', 'destination_allowlist', 'supports_replay'] as $required) {
                if (!array_key_exists($required, $capabilities)) {
                    throw new InvalidArgumentException('Network provider capability contract is incomplete.');
                }
            }
            if ((int)$capabilities['timeout_seconds'] < 1 || (int)$capabilities['timeout_seconds'] > 60 || !is_array($capabilities['destination_allowlist'])) {
                throw new InvalidArgumentException('Network provider safety contract is invalid.');
            }
        }
        if (self::$providers === null) {
            self::$providers = [];
        }
        self::$providers[$key] = $provider;
    }

    /** @return array<string, RSR_Trend_Provider> */
    public static function all(): array
    {
        if (self::$providers === null) {
            self::$providers = [];
            self::register(new RSR_Manual_Provider());
            /**
             * External provider adapters register here. File 15 never stores
             * raw credentials; adapters resolve a credentials_ref through the
             * platform secret manager.
             */
            do_action('rsr_register_trend_providers');
        }
        return self::$providers;
    }

    public static function get(string $key): ?RSR_Trend_Provider
    {
        $providers = self::all();
        return $providers[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public static function describe(): array
    {
        $out = [];
        foreach (self::all() as $key => $provider) {
            $out[$key] = $provider->capabilities();
        }
        return $out;
    }
}
