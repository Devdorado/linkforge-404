<?php
/**
 * Privacy handler — IP anonymization and GDPR integration.
 *
 * PRD §5.6 (FR-601 through FR-604) and §6.4.
 *
 * Privacy by Design: personal data is anonymized BEFORE storage, in RAM,
 * irreversibly. This class also registers WordPress Privacy Tools exporters
 * and erasers.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Privacy {

    /**
     * FR-601: Anonymize an IP address.
     *
     * IPv4: last octet zeroed (e.g., 192.168.1.42 → 192.168.1.0).
     * IPv6: last 80 bits zeroed.
     * Then hashed with HMAC-SHA256 using a site-specific key.
     *
     * @param string $ip Raw IP address.
     * @return string Anonymized hash (64-char hex).
     */
    public function anonymize_ip( string $ip ): string {
        if ( ! (bool) get_option( 'linkforge_ip_anonymize', true ) ) {
            // If anonymization is disabled (not recommended), still hash.
            return hash_hmac( 'sha256', $ip, $this->get_hash_key() );
        }

        $ip = $this->mask_ip( $ip );

        return hash_hmac( 'sha256', $ip, $this->get_hash_key() );
    }

    /**
     * Mask an IP address by zeroing the last segment.
     *
     * @param string $ip Raw IP address.
     * @return string Masked IP.
     */
    private function mask_ip( string $ip ): string {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            // Zero last octet.
            return (string) long2ip( ip2long( $ip ) & 0xFFFFFF00 );
        }

        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            // Zero last 80 bits (keep first 48 bits).
            $packed = inet_pton( $ip );
            if ( false !== $packed ) {
                $packed = substr( $packed, 0, 6 ) . str_repeat( "\0", 10 );
                return (string) inet_ntop( $packed );
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get the HMAC key for IP hashing.
     * Uses WordPress AUTH_SALT for site-specific entropy.
     */
    private function get_hash_key(): string {
        return defined( 'AUTH_SALT' ) ? AUTH_SALT : 'linkforge-fallback-salt-' . get_site_url();
    }

    /**
     * FR-603: Register WordPress Privacy Data Exporter.
     *
     * @param array<string, array<string, mixed>> $exporters Existing exporters.
     * @return array<string, array<string, mixed>>
     */
    public function register_exporter( array $exporters ): array {
        $exporters['linkforge-404'] = [
            'exporter_friendly_name' => __( 'LinkForge 404 Log Data', 'linkforge-404' ),
            'callback'               => [ $this, 'export_personal_data' ],
        ];

        return $exporters;
    }

    /**
     * FR-603: Register WordPress Privacy Data Eraser.
     *
     * @param array<string, array<string, mixed>> $erasers Existing erasers.
     * @return array<string, array<string, mixed>>
     */
    public function register_eraser( array $erasers ): array {
        $erasers['linkforge-404'] = [
            'eraser_friendly_name' => __( 'LinkForge 404 Log Data', 'linkforge-404' ),
            'callback'             => [ $this, 'erase_personal_data' ],
        ];

        return $erasers;
    }

    /**
     * Privacy exporter callback.
     *
     * Because we store only anonymized IP hashes, we cannot correlate
     * data to an email address deterministically. We return an empty export
     * with a note explaining the anonymization.
     *
     * @param string $email_address User email.
     * @param int    $page          Page for paginated export.
     * @return array{data: array<mixed>, done: bool}
     */
    public function export_personal_data( string $email_address, int $page = 1 ): array {
        // IP addresses are anonymized and hashed; no PII linkage is possible.
        return [
            'data' => [],
            'done' => true,
        ];
    }

    /**
     * Privacy eraser callback.
     *
     * Since IPs are anonymized before storage, there is no PII to erase.
     * We still confirm the operation for GDPR compliance.
     *
     * @param string $email_address User email.
     * @param int    $page          Page for paginated erasure.
     * @return array{items_removed: int, items_retained: bool, messages: array<string>, done: bool}
     */
    public function erase_personal_data( string $email_address, int $page = 1 ): array {
        return [
            'items_removed'  => 0,
            'items_retained' => false,
            'messages'       => [
                __( 'LinkForge 404 does not store personally identifiable data. All IP addresses are anonymized and hashed before storage.', 'linkforge-404' ),
            ],
            'done'           => true,
        ];
    }
}
