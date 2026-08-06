<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Capabilities
{
    public const STUDIES = 'rsr_manage_own_studies';
    public const EXPORT_STUDIES = 'rsr_export_own_studies';
    public const MANAGE_SCHEMA = 'rsr_manage_schema';
    public const MANAGE_SOURCES = 'rsr_manage_sources';
    public const RUN_INGESTION = 'rsr_run_ingestion';
    public const REVIEW_REPORTS = 'rsr_review_reports';
    public const PUBLISH_REPORTS = 'rsr_publish_reports';
    public const CORRECT_REPORTS = 'rsr_correct_reports';
    public const VIEW_DIAGNOSTICS = 'rsr_view_diagnostics';
    public const PURGE_DATA = 'rsr_purge_data';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::STUDIES,
            self::EXPORT_STUDIES,
            self::MANAGE_SCHEMA,
            self::MANAGE_SOURCES,
            self::RUN_INGESTION,
            self::REVIEW_REPORTS,
            self::PUBLISH_REPORTS,
            self::CORRECT_REPORTS,
            self::VIEW_DIAGNOSTICS,
            self::PURGE_DATA,
        ];
    }

    public static function grant_administrator(): void
    {
        $role = get_role('administrator');
        if (!$role) {
            return;
        }
        foreach (self::all() as $capability) {
            $role->add_cap($capability);
        }
    }

    public static function hooks(): void
    {
        add_filter('map_meta_cap', [self::class, 'map_meta_cap'], 20, 4);
    }

    /**
     * @param array<int, string> $caps
     * @param string             $cap
     * @param int                $user_id
     * @param array<int, mixed>  $args
     * @return array<int, string>
     */
    public static function map_meta_cap(array $caps, string $cap, int $user_id, array $args): array
    {
        if (!in_array($cap, self::all(), true)) {
            return $caps;
        }

        if (self::is_access_restricted($user_id)) {
            return ['do_not_allow'];
        }

        if (self::is_founder($user_id)) {
            return ['exist'];
        }

        if (in_array($cap, [self::STUDIES, self::EXPORT_STUDIES], true)) {
            return self::is_verified_doctor($user_id) ? ['exist'] : ['do_not_allow'];
        }

        return $caps;
    }

    public static function is_verified_doctor(?int $user_id = null): bool
    {
        $user_id = $user_id ?? get_current_user_id();
        if ($user_id <= 0 || self::is_access_restricted($user_id)) {
            return false;
        }

        /**
         * File 00 is the authoritative identity/claim owner. The filter allows
         * its versioned claim adapter to answer without File 15 reading File 00
         * tables or user meta directly.
         */
        $verified = (bool)apply_filters('rsr_file00_claim', false, 'doctor.verified', $user_id, '1');
        if ($verified) {
            return true;
        }

        // Compatibility bridge only; can be disabled after File 00 adapter is live.
        $legacy = (bool)apply_filters('rsr_legacy_verified_doctor', false, $user_id);
        return $legacy && !self::is_access_restricted($user_id);
    }

    public static function is_founder(?int $user_id = null): bool
    {
        $user_id = $user_id ?? get_current_user_id();
        if ($user_id <= 0 || self::is_access_restricted($user_id)) {
            return false;
        }
        return (bool)apply_filters('rsr_file00_claim', false, 'institution.founder', $user_id, '1');
    }

    /**
     * Fail closed for explicit current suspension/revocation assertions.
     * Absence of an optional assertion does not invent a negative claim; the
     * authoritative verified/founder claim is still required separately.
     */
    public static function is_access_restricted(?int $user_id = null): bool
    {
        $user_id = $user_id ?? get_current_user_id();
        if ($user_id <= 0) {
            return true;
        }

        $restricted = (bool)apply_filters('rsr_file00_access_restricted', false, $user_id, '1');
        foreach (['account.suspended', 'doctor.suspended', 'doctor.verification_revoked', 'account.security_hold'] as $claim) {
            if ((bool)apply_filters('rsr_file00_claim', false, $claim, $user_id, '1')) {
                $restricted = true;
                break;
            }
        }
        return $restricted;
    }

    public static function can(string $capability, ?int $user_id = null): bool
    {
        $user_id = $user_id ?? get_current_user_id();
        if ($user_id <= 0 || self::is_access_restricted($user_id)) {
            return false;
        }
        if (user_can($user_id, $capability)) {
            return true;
        }
        if (self::is_founder($user_id)) {
            return true;
        }
        return false;
    }

    /** @return true|WP_Error */
    public static function require_verified_doctor(?int $user_id = null)
    {
        $user_id = $user_id ?? get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('rsr_auth_required', __('Authentication is required.', RSR_TEXT_DOMAIN), ['status' => 401]);
        }
        if (self::is_access_restricted($user_id)) {
            return new WP_Error(
                'rsr_account_restricted',
                __('This account is currently suspended, revoked, or under a security hold.', RSR_TEXT_DOMAIN),
                ['status' => 403]
            );
        }
        if (!self::is_verified_doctor($user_id) && !self::is_founder($user_id)) {
            return new WP_Error(
                'rsr_verified_doctor_required',
                __('A currently verified doctor account is required.', RSR_TEXT_DOMAIN),
                ['status' => 403]
            );
        }
        return true;
    }
}
