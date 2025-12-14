<?php

declare(strict_types=1);

/**
 * Unraid WebGUI JavaScript Emulation
 *
 * This file generates JavaScript that replicates Unraid's client-side behavior:
 * - CSRF token injection for AJAX requests
 * - dialogStyle() for jQuery UI dialog styling
 * - Form auto-enhancement (CSRF token fields)
 * - Standard utility functions
 *
 * Usage in test harness:
 *   <?php echo UnraidJavaScript::getInlineScript($csrfToken); ?>
 *
 * @package UnraidTestHarness
 * @license GPL-2.0
 * @see https://github.com/unraid/webgui for original implementations
 */

class UnraidJavaScript
{
    /**
     * Get the complete inline JavaScript for Unraid emulation
     *
     * @param string $csrfToken CSRF token to inject
     * @return string Complete <script> block
     */
    public static function getInlineScript(string $csrfToken): string
    {
        $js = self::getCsrfHandling($csrfToken);
        $js .= self::getDialogStyle();
        $js .= self::getFormEnhancement($csrfToken);
        $js .= self::getUtilityFunctions();
        $js .= self::getSweetAlertPolyfill();

        return "<script>\n$js\n</script>";
    }

    /**
     * Get CSRF token handling JavaScript
     *
     * This replicates Boot.php's ajaxSend handler that automatically
     * appends CSRF tokens to all POST requests.
     *
     * @param string $csrfToken CSRF token
     * @return string JavaScript code
     */
    public static function getCsrfHandling(string $csrfToken): string
    {
        return <<<JS
// ============================================================================
// CSRF TOKEN HANDLING
// Automatically appends csrf_token to all jQuery AJAX POST requests
// Source: /usr/local/emhttp/plugins/dynamix/include/Boot.php
// ============================================================================

// Global CSRF token variable (available to all scripts)
var csrf_token = "$csrfToken";

// Auto-append CSRF token to all POST requests
\$(document).ajaxSend(function(elm, xhr, s) {
    if (s.type == 'POST') {
        s.data += s.data ? "&" : "";
        s.data += "csrf_token=" + encodeURIComponent(csrf_token);
    }
});

JS;
    }

    /**
     * Get dialogStyle() function
     *
     * This is the standard styling function called after opening
     * jQuery UI dialogs in Unraid.
     *
     * @return string JavaScript code
     */
    public static function getDialogStyle(): string
    {
        return <<<'JS'
// ============================================================================
// DIALOG STYLING
// Standard function to style jQuery UI dialogs consistently
// Source: Multiple .page files (CacheDevices.page, DeviceInfo.page, etc.)
// ============================================================================

function dialogStyle() {
    $('.ui-dialog-titlebar-close').css({'display': 'none'});
    $('.ui-dialog-title').css({
        'text-align': 'center',
        'width': '100%',
        'font-size': '1.8rem'
    });
    $('.ui-dialog-content').css({
        'padding-top': '15px',
        'vertical-align': 'bottom'
    });
    $('.ui-button-text').css({'padding': '0px 5px'});
}

JS;
    }

    /**
     * Get form enhancement JavaScript
     *
     * Automatically adds CSRF token hidden fields to forms on page load.
     *
     * @param string $csrfToken CSRF token
     * @return string JavaScript code
     */
    public static function getFormEnhancement(string $csrfToken): string
    {
        return <<<JS
// ============================================================================
// FORM ENHANCEMENT
// Automatically adds CSRF token to forms on page load
// Source: /usr/local/emhttp/plugins/dynamix/include/DefaultPageLayout/BodyInlineJS.php
// ============================================================================

\$(function() {
    // Add CSRF token to all forms that don't already have it
    \$('form').each(function() {
        if (\$(this).find('input[name="csrf_token"]').length === 0) {
            \$(this).append(\$('<input>').attr({
                type: 'hidden',
                name: 'csrf_token',
                value: csrf_token
            }));
        }
    });
});

JS;
    }

    /**
     * Get utility functions
     *
     * Common utility functions used across Unraid WebGUI.
     *
     * @return string JavaScript code
     */
    public static function getUtilityFunctions(): string
    {
        return <<<'JS'
// ============================================================================
// UTILITY FUNCTIONS
// Common utilities used across Unraid WebGUI
// ============================================================================

/**
 * Navigate to done page (standard form completion handler)
 */
function done() {
    location.href = '/Main';
}

/**
 * Refresh the current page
 */
function refresh() {
    location.reload();
}

/**
 * Signal form change (enables Apply button)
 * @param {HTMLFormElement} form - The form element
 */
function signal(form) {
    $(form).find('input[type="submit"]').prop('disabled', false);
}

/**
 * Open a new window with specified URL
 * @param {string} url - URL to open
 * @param {string} name - Window name
 * @param {string} features - Window features
 */
function openWindow(url, name, features) {
    window.open(url, name || '_blank', features || '');
}

/**
 * Open a terminal window (nchan-based)
 * @param {string} cmd - Command to run
 * @param {string} title - Window title
 */
function openTerminal(cmd, title) {
    var width = 1000;
    var height = 600;
    var left = (screen.width - width) / 2;
    var top = (screen.height - height) / 2;
    var features = 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top;
    window.open('/webGui/include/StartCommand.php?cmd=' + encodeURIComponent(cmd) + '&title=' + encodeURIComponent(title || 'Terminal'), 'Terminal', features);
}

/**
 * Show/hide element with animation
 * @param {string} id - Element ID
 * @param {string} speed - Animation speed ('fast', 'slow', or milliseconds)
 */
function toggleElement(id, speed) {
    $('#' + id).toggle(speed || 'fast');
}

/**
 * Validate form before submission
 * @param {HTMLFormElement} form - Form to validate
 * @returns {boolean} True if valid
 */
function validateForm(form) {
    var valid = true;
    $(form).find('input[required], select[required], textarea[required]').each(function() {
        if (!$(this).val()) {
            $(this).addClass('validation-error');
            valid = false;
        } else {
            $(this).removeClass('validation-error');
        }
    });
    return valid;
}

/**
 * Timer management object
 * Used for managing recurring timers across the page
 */
var timers = {};

/**
 * Clear all managed timers
 */
function clearTimers() {
    for (var key in timers) {
        if (timers.hasOwnProperty(key)) {
            clearTimeout(timers[key]);
            delete timers[key];
        }
    }
}

JS;
    }

    /**
     * Get SweetAlert polyfill
     *
     * Provides a basic swal() function if SweetAlert is not loaded.
     *
     * @return string JavaScript code
     */
    public static function getSweetAlertPolyfill(): string
    {
        return <<<'JS'
// ============================================================================
// SWEETALERT POLYFILL
// Basic fallback if SweetAlert library is not loaded
// ============================================================================

if (typeof swal === 'undefined') {
    /**
     * Basic SweetAlert polyfill using native dialogs
     * @param {Object|string} options - Alert options or title string
     * @param {string} text - Alert text (if options is title)
     * @param {string} type - Alert type (success, error, warning, info)
     * @param {Function} callback - Callback function
     */
    window.swal = function(options, text, type, callback) {
        // Handle string arguments (simple alert)
        if (typeof options === 'string') {
            options = {
                title: options,
                text: text,
                type: type
            };
        }
        
        var title = options.title || '';
        var message = options.text || '';
        var showCancel = options.showCancelButton || false;
        var confirmText = options.confirmButtonText || 'OK';
        var cancelText = options.cancelButtonText || 'Cancel';
        
        // Build message
        var fullMessage = title;
        if (message) {
            fullMessage += '\n\n' + message;
        }
        
        // Show appropriate dialog
        var result;
        if (showCancel) {
            result = confirm(fullMessage);
        } else {
            alert(fullMessage);
            result = true;
        }
        
        // Call callback if provided
        if (typeof callback === 'function') {
            callback(result);
        } else if (typeof arguments[arguments.length - 1] === 'function') {
            arguments[arguments.length - 1](result);
        }
    };
}

JS;
    }

    /**
     * Get just the CSRF token script (minimal version)
     *
     * @param string $csrfToken CSRF token
     * @return string Minimal script block
     */
    public static function getMinimalScript(string $csrfToken): string
    {
        $js = self::getCsrfHandling($csrfToken);
        return "<script>\n$js\n</script>";
    }

    /**
     * Get the head inline JavaScript
     *
     * This replicates HeadInlineJS.php content.
     *
     * @param string $csrfToken CSRF token
     * @return string JavaScript code for <head>
     */
    public static function getHeadScript(string $csrfToken): string
    {
        return <<<JS
<script>
// CSRF token (from HeadInlineJS.php)
var csrf_token = "$csrfToken";

// Document ready state
var documentReady = false;
\$(function() { documentReady = true; });
</script>
JS;
    }
}
