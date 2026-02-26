<?php
/**
 * Settings view — plugin configuration.
 *
 * PRD §5.2, §5.4, §5.6: Logging, matching, and privacy settings.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap linkforge-wrap">
    <h1>
        <span class="dashicons dashicons-randomize"></span>
        <?php esc_html_e( 'LinkForge 404 — Settings', 'linkforge-404' ); ?>
    </h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'linkforge_settings' ); ?>

        <!-- Logging Section -->
        <div class="linkforge-section">
            <h2><?php esc_html_e( 'Logging', 'linkforge-404' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="linkforge_logging_enabled"><?php esc_html_e( '404 Logging', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="linkforge_logging_enabled" id="linkforge_logging_enabled" value="1"
                                <?php checked( get_option( 'linkforge_logging_enabled', true ) ); ?> />
                            <?php esc_html_e( 'Enable 404 logging', 'linkforge-404' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="linkforge_log_retention_days"><?php esc_html_e( 'Log Retention (Days)', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="linkforge_log_retention_days" id="linkforge_log_retention_days"
                            value="<?php echo esc_attr( (string) get_option( 'linkforge_log_retention_days', 90 ) ); ?>"
                            min="7" max="365" step="1" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Log entries older than this will be automatically deleted. Set to 0 to keep indefinitely.', 'linkforge-404' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="linkforge_ignore_extensions"><?php esc_html_e( 'Ignored File Extensions', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="linkforge_ignore_extensions" id="linkforge_ignore_extensions"
                            value="<?php echo esc_attr( (string) get_option( 'linkforge_ignore_extensions', 'ico,css,js,jpg,jpeg,png,gif,svg,webp,woff,woff2,ttf,eot,map' ) ); ?>"
                            class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Comma-separated list of file extensions to ignore (no logging for static assets).', 'linkforge-404' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Rate Limiting Section -->
        <div class="linkforge-section">
            <h2><?php esc_html_e( 'Rate Limiting', 'linkforge-404' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="linkforge_rate_limit_per_ip"><?php esc_html_e( 'Max 404 Requests per IP', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="linkforge_rate_limit_per_ip" id="linkforge_rate_limit_per_ip"
                            value="<?php echo esc_attr( (string) get_option( 'linkforge_rate_limit_per_ip', 60 ) ); ?>"
                            min="0" max="10000" step="1" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Set to 0 to disable rate limiting. Recommended: 60.', 'linkforge-404' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="linkforge_rate_limit_window"><?php esc_html_e( 'Rate Limit Window (Seconds)', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="linkforge_rate_limit_window" id="linkforge_rate_limit_window"
                            value="<?php echo esc_attr( (string) get_option( 'linkforge_rate_limit_window', 300 ) ); ?>"
                            min="60" max="3600" step="1" class="small-text" />
                    </td>
                </tr>
            </table>
        </div>

        <!-- Matching Section -->
        <div class="linkforge-section">
            <h2><?php esc_html_e( 'Matching Algorithms', 'linkforge-404' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="linkforge_fuzzy_threshold"><?php esc_html_e( 'Fuzzy Match Threshold', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="linkforge_fuzzy_threshold" id="linkforge_fuzzy_threshold"
                            value="<?php echo esc_attr( (string) get_option( 'linkforge_fuzzy_threshold', 0.85 ) ); ?>"
                            min="0.5" max="1.0" step="0.01" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Jaro-Winkler similarity score (0.0–1.0). Higher = stricter matching. Default: 0.85.', 'linkforge-404' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Privacy Section -->
        <div class="linkforge-section">
            <h2><?php esc_html_e( 'Privacy & GDPR', 'linkforge-404' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="linkforge_ip_anonymize"><?php esc_html_e( 'IP Anonymization', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="linkforge_ip_anonymize" id="linkforge_ip_anonymize" value="1"
                                <?php checked( get_option( 'linkforge_ip_anonymize', true ) ); ?> />
                            <?php esc_html_e( 'Anonymize IP addresses before storage (GDPR-compliant)', 'linkforge-404' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Strongly recommended. When enabled, the last octet of IPv4 addresses is zeroed, then the IP is hashed with HMAC-SHA256.', 'linkforge-404' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- AI Premium Section (Phase 3) -->
        <div class="linkforge-section">
            <h2>
                <?php esc_html_e( 'AI Premium', 'linkforge-404' ); ?>
                <span class="linkforge-badge linkforge-badge-info"><?php esc_html_e( 'Phase 3', 'linkforge-404' ); ?></span>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="linkforge_ai_enabled"><?php esc_html_e( 'Enable AI Matching', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="linkforge_ai_enabled" id="linkforge_ai_enabled" value="1"
                                <?php checked( get_option( 'linkforge_ai_enabled', false ) ); ?> />
                            <?php esc_html_e( 'Enable OpenAI Embeddings semantic matching', 'linkforge-404' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="linkforge_ai_api_key"><?php esc_html_e( 'OpenAI API Key', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="linkforge_ai_api_key" id="linkforge_ai_api_key"
                            value="<?php echo esc_attr( (string) get_option( 'linkforge_ai_api_key', '' ) ); ?>"
                            class="regular-text" autocomplete="off" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="linkforge_ai_confidence"><?php esc_html_e( 'Auto-Apply Confidence', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="linkforge_ai_confidence" id="linkforge_ai_confidence"
                            value="<?php echo esc_attr( (string) get_option( 'linkforge_ai_confidence', 0.85 ) ); ?>"
                            min="0.5" max="1.0" step="0.01" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Matches above this score are auto-applied. Below = manual review.', 'linkforge-404' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="linkforge_ai_daily_budget"><?php esc_html_e( 'Daily API Budget', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="linkforge_ai_daily_budget" id="linkforge_ai_daily_budget"
                            value="<?php echo esc_attr( (string) get_option( 'linkforge_ai_daily_budget', 100 ) ); ?>"
                            min="1" max="10000" step="1" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Maximum number of API calls per day to control costs.', 'linkforge-404' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
