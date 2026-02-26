/**
 * LinkForge 404 — Admin JavaScript
 *
 * Handles dashboard data loading, CRUD operations via REST API,
 * bulk actions, and export functionality.
 *
 * @package LinkForge404
 */

/* global jQuery, linkforgeAdmin, wp */
(function ($) {
    'use strict';

    const API = linkforgeAdmin.restUrl;
    const i18n = linkforgeAdmin.i18n;

    /**
     * Fetch wrapper using wp.apiFetch (handles nonce automatically).
     */
    function apiFetch(endpoint, options = {}) {
        return wp.apiFetch({
            path: 'linkforge/v1/' + endpoint,
            ...options,
        });
    }

    /* ── Dashboard ─────────────────────────────────────────── */

    function loadStats() {
        apiFetch('stats').then(function (data) {
            $('#stat-active-redirects').text(data.active_redirects || 0);
            $('#stat-total-hits').text(formatNumber(data.total_redirect_hits || 0));
            $('#stat-unresolved').text(data.unresolved_404s || 0);
            $('#stat-total-404-hits').text(formatNumber(data.total_404_hits || 0));

            // Show warning if DB tables are missing.
            if (data.tables_ok === false) {
                showNotice(i18n.tablesWarning, 'error');
            }
        }).catch(function () {
            $('#stat-active-redirects, #stat-total-hits, #stat-unresolved, #stat-total-404-hits').text('?');
        });
    }

    function loadLogs(page) {
        page = page || 1;

        apiFetch('logs?page=' + page + '&per_page=25').then(function (data) {
            renderLogsTable(data.items || []);
            renderPagination('#linkforge-logs-pagination', data.total, data.page, data.per_page, loadLogs);
        }).catch(function () {
            $('#linkforge-logs-body').html(
                '<tr><td colspan="6" class="linkforge-loading">' + i18n.error + '</td></tr>'
            );
        });
    }

    function renderLogsTable(items) {
        var $body = $('#linkforge-logs-body');
        $body.empty();

        if (items.length === 0) {
            $body.html('<tr><td colspan="6" class="linkforge-loading">' + esc(i18n.noLogs) + '</td></tr>');
            return;
        }

        items.forEach(function (item) {
            var row = '<tr data-id="' + esc(item.id) + '">' +
                '<td class="check-column"><input type="checkbox" name="log_ids[]" value="' + esc(item.id) + '" /></td>' +
                '<td class="column-url"><code>' + esc(item.url) + '</code></td>' +
                '<td class="column-hits"><strong>' + esc(item.hit_count) + '</strong></td>' +
                '<td class="column-referrer">' + (item.referrer ? esc(truncate(item.referrer, 40)) : '—') + '</td>' +
                '<td class="column-last-seen">' + esc(item.last_seen || '') + '</td>' +
                '<td class="column-actions">' +
                    '<a class="linkforge-row-action linkforge-quick-redirect" data-url="' + esc(item.url) + '" data-id="' + esc(item.id) + '">' + esc(i18n.quickRedirect) + '</a>' +
                '</td>' +
                '</tr>';
            $body.append(row);
        });
    }

    /* ── Redirects ─────────────────────────────────────────── */

    function loadRedirects(page) {
        page = page || 1;

        apiFetch('redirects?page=' + page + '&per_page=25').then(function (data) {
            renderRedirectsTable(data.items || []);
            renderPagination('#linkforge-redirects-pagination', data.total, data.page, data.per_page, loadRedirects);
        }).catch(function () {
            $('#linkforge-redirects-body').html(
                '<tr><td colspan="8" class="linkforge-loading">' + i18n.error + '</td></tr>'
            );
        });
    }

    function renderRedirectsTable(items) {
        var $body = $('#linkforge-redirects-body');
        $body.empty();

        if (items.length === 0) {
            $body.html('<tr><td colspan="8" class="linkforge-loading">' + esc(i18n.noRedirects) + '</td></tr>');
            return;
        }

        items.forEach(function (item) {
            var activeClass = parseInt(item.is_active) ? 'active' : '';
            var typeBadge = '<span class="linkforge-badge linkforge-badge-' + typeBadgeColor(item.match_type) + '">' + esc(item.match_type) + '</span>';

            var row = '<tr data-id="' + esc(item.id) + '">' +
                '<td class="check-column"><input type="checkbox" name="redirect_ids[]" value="' + esc(item.id) + '" /></td>' +
                '<td class="column-url-from"><code>' + esc(truncate(item.url_from, 50)) + '</code></td>' +
                '<td class="column-url-to"><code>' + esc(truncate(item.url_to, 50)) + '</code></td>' +
                '<td class="column-type">' + typeBadge + '</td>' +
                '<td class="column-status">' + esc(item.status_code) + '</td>' +
                '<td class="column-hits"><strong>' + esc(item.hit_count) + '</strong></td>' +
                '<td class="column-active"><span class="linkforge-toggle ' + activeClass + '" data-id="' + esc(item.id) + '"></span></td>' +
                '<td class="column-actions">' +
                    '<a class="linkforge-row-action linkforge-delete" data-id="' + esc(item.id) + '">' + esc(i18n.deleteAction) + '</a>' +
                '</td>' +
                '</tr>';
            $body.append(row);
        });
    }

    /* ── Add Redirect Form ─────────────────────────────────── */

    $(document).on('submit', '#linkforge-add-form', function (e) {
        e.preventDefault();

        var data = {
            url_from: $('#lf-url-from').val(),
            url_to: $('#lf-url-to').val(),
            match_type: $('#lf-match-type').val(),
            status_code: parseInt($('#lf-status-code').val()),
        };

        apiFetch('redirects', {
            method: 'POST',
            data: data,
        }).then(function () {
            $('#lf-url-from, #lf-url-to').val('');
            loadRedirects(1);
            showNotice(i18n.saved, 'success');
        }).catch(function () {
            showNotice(i18n.error, 'error');
        });
    });

    /* ── Delete Redirect ───────────────────────────────────── */

    $(document).on('click', '.linkforge-delete', function () {
        if (!confirm(i18n.confirmDelete)) return;

        var id = $(this).data('id');

        apiFetch('redirects/' + id, { method: 'DELETE' }).then(function () {
            loadRedirects(1);
            loadStats();
        }).catch(function () {
            showNotice(i18n.error, 'error');
        });
    });

    /* ── Toggle Active ─────────────────────────────────────── */

    $(document).on('click', '.linkforge-toggle', function () {
        var $el = $(this);
        var id = $el.data('id');
        var isActive = $el.hasClass('active');

        // Optimistic UI update.
        $el.toggleClass('active');

        // Use REST to update (we piggyback on the resolve endpoint pattern).
        // For a dedicated toggle, we'd need a PATCH endpoint. For now, a simple approach:
        $.ajax({
            url: API + 'redirects/' + id,
            method: 'POST',
            headers: { 'X-WP-Nonce': linkforgeAdmin.nonce },
            data: JSON.stringify({ is_active: !isActive }),
            contentType: 'application/json',
        }).fail(function () {
            $el.toggleClass('active'); // Revert on failure.
            showNotice(i18n.error, 'error');
        });
    });

    /* ── Quick Redirect from Dashboard ─────────────────────── */

    $(document).on('click', '.linkforge-quick-redirect', function () {
        var logId = $(this).data('id');
        var url = $(this).data('url');

        if (!confirm(i18n.confirmQuickRedirect.replace('%s', url))) return;

        apiFetch('logs/resolve', {
            method: 'POST',
            data: {
                log_ids: [logId],
                url_to: '',
                status_code: 301,
            },
        }).then(function () {
            loadLogs(1);
            loadStats();
            showNotice(i18n.saved, 'success');
        }).catch(function () {
            showNotice(i18n.error, 'error');
        });
    });

    /* ── Bulk Actions (Logs) ───────────────────────────────── */

    $('#linkforge-bulk-action').on('change', function () {
        if ($(this).val() === 'redirect-custom') {
            $('#linkforge-custom-url').show();
        } else {
            $('#linkforge-custom-url').hide();
        }
    });

    $(document).on('click', '#linkforge-bulk-apply', function () {
        var action = $('#linkforge-bulk-action').val();
        if (!action) return;

        var ids = [];
        $('input[name="log_ids[]"]:checked').each(function () {
            ids.push(parseInt($(this).val()));
        });

        if (ids.length === 0) return;
        if (!confirm(i18n.confirmResolve)) return;

        var statusCode = action === 'redirect-410' ? 410 : 301;
        var urlTo = action === 'redirect-custom' ? $('#linkforge-custom-url').val() : '';

        apiFetch('logs/resolve', {
            method: 'POST',
            data: {
                log_ids: ids,
                url_to: urlTo,
                status_code: statusCode,
            },
        }).then(function () {
            loadLogs(1);
            loadStats();
            showNotice(i18n.saved, 'success');
        }).catch(function () {
            showNotice(i18n.error, 'error');
        });
    });

    /* ── Export ─────────────────────────────────────────────── */

    $(document).on('click', '#linkforge-export-apache, #linkforge-export-nginx', function () {
        var format = $(this).attr('id').includes('nginx') ? 'nginx' : 'apache';
        var title = format === 'nginx' ? i18n.exportNginx : i18n.exportHtaccess;

        $.post(linkforgeAdmin.ajaxUrl, {
            action: 'linkforge_export_rules',
            _ajax_nonce: linkforgeAdmin.ajaxNonce,
            format: format,
        }, function (response) {
            if (response.success) {
                $('#linkforge-export-title').text(title);
                $('#linkforge-export-output').val(response.data.rules);
                $('#linkforge-export-modal').show();
            }
        });
    });

    $(document).on('click', '#linkforge-export-copy', function () {
        var $textarea = $('#linkforge-export-output');
        $textarea.select();
        document.execCommand('copy');
        $(this).text(i18n.copied);
        setTimeout(function () { $('#linkforge-export-copy').text(i18n.copyToClipboard); }, 2000);
    });

    $(document).on('click', '#linkforge-export-close', function () {
        $('#linkforge-export-modal').hide();
    });

    /* ── Select All ────────────────────────────────────────── */

    $(document).on('change', '#linkforge-select-all', function () {
        $('input[name="log_ids[]"]').prop('checked', $(this).prop('checked'));
    });

    $(document).on('change', '#linkforge-redirects-select-all', function () {
        $('input[name="redirect_ids[]"]').prop('checked', $(this).prop('checked'));
    });

    /* ── Utilities ─────────────────────────────────────────── */

    function renderPagination(selector, total, page, perPage, callback) {
        var $el = $(selector);
        $el.empty();

        var totalPages = Math.ceil(total / perPage);
        if (totalPages <= 1) return;

        for (var i = 1; i <= totalPages; i++) {
            var cls = i === page ? 'button current' : 'button';
            $el.append('<button type="button" class="' + cls + '" data-page="' + i + '">' + i + '</button>');
        }

        $el.on('click', '.button', function () {
            callback(parseInt($(this).data('page')));
        });
    }

    function formatNumber(n) {
        return parseInt(n).toLocaleString();
    }

    function truncate(str, max) {
        return str && str.length > max ? str.substring(0, max) + '…' : str;
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function typeBadgeColor(type) {
        switch (type) {
            case 'exact': return 'success';
            case 'regex': return 'info';
            case 'fuzzy': return 'warning';
            case 'ai': return 'muted';
            default: return 'muted';
        }
    }

    function showNotice(message, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        var $notice = $('<div class="notice ' + cls + ' is-dismissible"><p>' + esc(message) + '</p></div>');
        $('.linkforge-wrap h1').after($notice);

        setTimeout(function () {
            $notice.fadeOut(function () { $(this).remove(); });
        }, 3000);
    }

    /* ── Auto-Save Settings ───────────────────────────────── */

    /**
     * Auto-save: any change to a settings input instantly saves via REST.
     * No need to scroll down and click "Save Changes".
     */
    function initAutoSave() {
        var $forms = $('.linkforge-wrap form[action*="options.php"]');
        if (!$forms.length) return;

        // Prevent default form submission — auto-save handles it.
        $forms.on('submit', function (e) {
            e.preventDefault();
        });

        // Hide submit buttons (auto-save replaces them).
        $forms.find('.submit').hide();

        // Listen for changes on all inputs within settings forms.
        $forms.find('input, select, textarea').on('change', function () {
            var $input = $(this);
            var name = $input.attr('name');

            if (!name || name.startsWith('_wp') || name === 'option_page' || name === 'action') {
                return; // Skip WP internal fields (nonce, referer, etc.).
            }

            var value;
            if ($input.is(':checkbox')) {
                value = $input.is(':checked') ? true : false;
            } else {
                value = $input.val();
            }

            // Visual feedback: dim the row while saving.
            var $row = $input.closest('tr');
            $row.css('opacity', '0.6');

            apiFetch('settings', {
                method: 'POST',
                data: {
                    option: name,
                    value: value,
                },
            }).then(function () {
                $row.css('opacity', '1');
                showSavedIndicator($input);
            }).catch(function () {
                $row.css('opacity', '1');
                showNotice(i18n.error, 'error');
            });
        });

        // Also debounce text/number inputs on keyup (save after 800ms pause).
        var debounceTimers = {};
        $forms.find('input[type="text"], input[type="number"], input[type="password"]').on('input', function () {
            var $input = $(this);
            var name = $input.attr('name');

            if (!name || name.startsWith('_wp') || name === 'option_page' || name === 'action') {
                return;
            }

            clearTimeout(debounceTimers[name]);
            debounceTimers[name] = setTimeout(function () {
                $input.trigger('change');
            }, 800);
        });
    }

    /**
     * Show a brief "Saved ✓" indicator next to the input.
     */
    function showSavedIndicator($input) {
        // Remove any existing indicator for this input.
        $input.siblings('.linkforge-saved-indicator').remove();

        var $indicator = $('<span class="linkforge-saved-indicator" style="color:#46b450;font-weight:600;margin-left:8px;transition:opacity .3s;">' + esc(i18n.savedIndicator) + '</span>');
        $input.closest('td').append($indicator);

        setTimeout(function () {
            $indicator.css('opacity', '0');
            setTimeout(function () { $indicator.remove(); }, 400);
        }, 1500);
    }

    /* ── Init ──────────────────────────────────────────────── */

    $(document).ready(function () {
        // Dashboard page.
        if ($('#linkforge-stats').length) {
            loadStats();
            loadLogs(1);
        }

        // Redirects page.
        if ($('#linkforge-redirects-table').length && !$('#linkforge-stats').length) {
            loadRedirects(1);
        }

        // Settings page — auto-save on change.
        initAutoSave();
    });

})(jQuery);
