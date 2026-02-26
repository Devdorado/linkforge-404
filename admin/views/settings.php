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

        <!-- 404 Behavior Section -->
        <div class="linkforge-section">
            <h2>
                <span class="dashicons dashicons-migrate" style="margin-right: 4px;"></span>
                <?php esc_html_e( '404 Behavior', 'linkforge-404' ); ?>
            </h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="linkforge_redirect_all_home"><?php esc_html_e( 'Redirect All 404s to Homepage', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="linkforge_redirect_all_home" id="linkforge_redirect_all_home" value="1"
                                <?php checked( get_option( 'linkforge_redirect_all_home', false ) ); ?> />
                            <?php esc_html_e( 'Automatically redirect all 404 errors to the homepage (301)', 'linkforge-404' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'When enabled, every 404 request is immediately redirected to your homepage. The 404 is still logged. This overrides the matching cascade (Exact, Regex, Fuzzy, AI).', 'linkforge-404' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

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
                        <label for="linkforge_immediate_logging"><?php esc_html_e( 'Immediate Logging', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="linkforge_immediate_logging" id="linkforge_immediate_logging" value="1"
                                <?php checked( get_option( 'linkforge_immediate_logging', true ) ); ?> />
                            <?php esc_html_e( 'Write 404 logs directly to the database (no buffer delay)', 'linkforge-404' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Recommended for most sites. Logs appear instantly in the dashboard. Disable only on very high-traffic sites that use Redis/Memcached buffering.', 'linkforge-404' ); ?></p>
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

    <!-- Updates Section -->
    <div class="linkforge-section">
        <h2>
            <span class="dashicons dashicons-update" style="margin-right: 4px;"></span>
            <?php esc_html_e( 'Automatic Updates', 'linkforge-404' ); ?>
        </h2>
        <form method="post" action="options.php">
            <?php settings_fields( 'linkforge_settings' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="linkforge_auto_update"><?php esc_html_e( 'Auto-Update', 'linkforge-404' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="linkforge_auto_update" id="linkforge_auto_update" value="1"
                                <?php checked( get_option( 'linkforge_auto_update', true ) ); ?> />
                            <?php esc_html_e( 'Automatically check for new versions via GitHub Releases', 'linkforge-404' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'When enabled, LinkForge 404 checks for updates every 12 hours. New versions appear as regular WordPress plugin updates. Disable this if you prefer to update manually.', 'linkforge-404' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Installed Version', 'linkforge-404' ); ?></th>
                    <td>
                        <code><?php echo esc_html( LINKFORGE_VERSION ); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Update Source', 'linkforge-404' ); ?></th>
                    <td>
                        <a href="https://github.com/Devdorado/linkforge-404/releases" target="_blank" rel="noopener noreferrer">
                            GitHub Releases &rarr;
                        </a>
                    </td>
                </tr>
                <?php
                $remote_cache = get_transient( 'linkforge_update_check' );
                if ( is_array( $remote_cache ) && ! empty( $remote_cache['version'] ) ) :
                ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Latest Available', 'linkforge-404' ); ?></th>
                    <td>
                        <code><?php echo esc_html( $remote_cache['version'] ); ?></code>
                        <?php if ( version_compare( $remote_cache['version'], LINKFORGE_VERSION, '>' ) ) : ?>
                            <span class="linkforge-badge linkforge-badge-success" style="margin-left: 8px;">
                                <?php esc_html_e( 'Update available!', 'linkforge-404' ); ?>
                            </span>
                        <?php else : ?>
                            <span class="linkforge-badge linkforge-badge-info" style="margin-left: 8px;">
                                <?php esc_html_e( 'Up to date', 'linkforge-404' ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <?php submit_button( __( 'Save Update Settings', 'linkforge-404' ) ); ?>
        </form>
    </div>

    <!-- Support Section -->
    <div class="linkforge-section">
        <h2><?php esc_html_e( 'Support', 'linkforge-404' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Documentation', 'linkforge-404' ); ?></th>
                <td>
                    <a href="https://devdorado.com/linkforge-404" target="_blank" rel="noopener noreferrer">
                        devdorado.com/linkforge-404
                    </a>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Email Support', 'linkforge-404' ); ?></th>
                <td>
                    <a href="mailto:support@devdorado.com">support@devdorado.com</a>
                    <p class="description">
                        <?php esc_html_e( 'Send us your question or bug report and we will get back to you within 24 hours.', 'linkforge-404' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'GitHub', 'linkforge-404' ); ?></th>
                <td>
                    <a href="https://github.com/Devdorado/linkforge-404" target="_blank" rel="noopener noreferrer">
                        Devdorado/linkforge-404
                    </a>
                    <p class="description">
                        <?php esc_html_e( 'Report issues, request features, or contribute to the project.', 'linkforge-404' ); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Devdorado Branding Footer -->
    <div class="linkforge-branding-footer">
        <p>
            <strong>LinkForge 404</strong> <?php esc_html_e( 'by', 'linkforge-404' ); ?>
            <a href="https://devdorado.com" target="_blank" rel="noopener noreferrer">Devdorado</a>
            &mdash;
            <?php esc_html_e( 'Need help?', 'linkforge-404' ); ?>
            <a href="mailto:support@devdorado.com">support@devdorado.com</a>
        </p>
    </div>
</div>
