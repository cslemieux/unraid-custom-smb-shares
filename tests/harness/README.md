# Unraid WebGUI Test Harness

A comprehensive test harness that emulates Unraid's WebGUI environment for testing plugins outside of Unraid.

## Overview

This harness replicates Unraid's page rendering pipeline and helper functions, allowing you to:

- Test `.page` files with proper Markdown and translation processing
- Test PHP endpoints with CSRF token handling
- Verify JavaScript behavior with Unraid-compatible utilities
- Run integration tests without a live Unraid server

## Components

### UnraidFunctions.php

PHP function emulation for Unraid's core helpers:

| Function | Description |
|----------|-------------|
| `parse_text($text)` | Process `_(...)_` translation markers |
| `parse_file($file, $markdown)` | Read file with translation + Markdown |
| `Markdown($text)` | Convert Markdown to HTML (via Parsedown) |
| `_($text)` | Translation function (passthrough in test) |
| `_var(&$name, $key, $default)` | Safe variable/array access |
| `mk_option($select, $value, $text, $extra)` | Generate `<option>` elements |
| `my_scale($value, &$unit, $decimals, $scale, $kilo)` | Format bytes with units |
| `my_number($value)` | Locale-aware number formatting |
| `my_temp($value)` | Temperature with C/F conversion |
| `my_time($time, $fmt)` | Locale-aware date/time |
| `my_disk($name, $raw)` | Format disk names |
| `parse_plugin_cfg($plugin, $sections, $scanner)` | Load plugin configuration |
| `my_parse_ini_file($file, $sections, $scanner)` | Parse INI with # comments |
| `my_parse_ini_string($text, $sections, $scanner)` | Parse INI string |
| `file_put_contents_atomic($filename, $data)` | Atomic file writes |
| `my_logger($message, $tag)` | Logging utility |
| `celsius($temp)` / `fahrenheit($temp)` | Temperature conversion |
| `autov($file)` | Cache-busting URLs |
| `compress($data, $decompress)` | Data compression |
| `my_explode($delimiter, $string, $limit, $default)` | Explode with defaults |
| `my_preg_split($pattern, $string, $limit)` | Regex split |

### UnraidJavaScript.php

JavaScript emulation for client-side behavior:

```php
// Get complete inline script
echo UnraidJavaScript::getInlineScript($csrfToken);

// Or get individual components
echo UnraidJavaScript::getCsrfHandling($csrfToken);
echo UnraidJavaScript::getDialogStyle();
echo UnraidJavaScript::getFormEnhancement($csrfToken);
echo UnraidJavaScript::getUtilityFunctions();
echo UnraidJavaScript::getSweetAlertPolyfill();
```

**Included JavaScript:**

- `csrf_token` global variable
- `$(document).ajaxSend()` - Auto-append CSRF to POST requests
- `dialogStyle()` - Standard jQuery UI dialog styling
- `done()`, `refresh()`, `signal()` - Navigation helpers
- `openWindow()`, `openTerminal()` - Window management
- `toggleElement()`, `validateForm()` - UI utilities
- `timers` object - Timer management
- `swal()` polyfill - Basic SweetAlert fallback

### router.php

PHP built-in server router that:

1. Maps URLs to `.page` files (e.g., `/Settings/CustomSMBShares`)
2. Processes `.page` files through Unraid's rendering pipeline
3. Handles PHP endpoints with CSRF validation
4. Serves static assets (JS, CSS, images)
5. Validates PHP syntax before execution
6. Logs AJAX requests for debugging

## Usage

### Starting the Test Server

```bash
# From project root
php -S localhost:8888 -t tests/harness/chroot/usr/local/emhttp tests/harness/router.php
```

### Directory Structure

```
tests/harness/
├── router.php              # Main router
├── UnraidFunctions.php     # PHP function emulation
├── UnraidJavaScript.php    # JavaScript emulation
├── dependencies.json       # CDN dependencies (jQuery, etc.)
├── validate-append.php     # Output validation
├── assets/                 # Test assets (fileTree, etc.)
└── chroot/                 # Simulated Unraid filesystem
    ├── boot/config/        # Persistent config
    ├── usr/local/emhttp/   # WebGUI files (DOCUMENT_ROOT)
    │   └── plugins/        # Plugin files
    └── var/local/emhttp/   # Runtime state (var.ini)
```

### Writing Tests

```php
use Tests\Helpers\UnraidTestHarness;

class MyPluginTest extends TestCase
{
    private static $harness;

    public static function setUpBeforeClass(): void
    {
        self::$harness = UnraidTestHarness::setup(8888);
    }

    public function testPageLoads(): void
    {
        $response = file_get_contents(self::$harness['url'] . '/Settings/MyPlugin');
        $this->assertStringContainsString('My Plugin', $response);
    }
}
```

## Global Variables

The harness initializes these globals (matching Unraid):

```php
$GLOBALS['display'] = [
    'scale'    => -1,      // Auto-scale for my_scale()
    'number'   => '.,',    // Decimal point, thousands separator
    'unit'     => 'C',     // Temperature unit: C or F
    'date'     => '%c',    // Date format
    'time'     => '%R',    // Time format
    'critical' => 90,      // Critical usage threshold
    'warning'  => 70,      // Warning usage threshold
    'raw'      => false,   // Show raw disk names
];

$GLOBALS['language'] = [
    'prefix_SI'  => 'K M G T P E Z Y',
    'prefix_IEC' => 'Ki Mi Gi Ti Pi Ei Zi Yi',
];

$GLOBALS['var'] = [
    'csrf_token' => '...',  // Auto-generated
];
```

## Key Patterns Emulated

### Page Rendering Pipeline

Matches Unraid's `MainContent.php`:

```php
// 1. Process translation markers
$content = parse_text($pageContent);

// 2. Apply Markdown (if enabled)
$content = Markdown($content);

// 3. Execute PHP via eval (like Unraid)
eval('?' . '>' . $content);
```

### CSRF Token Flow

1. Token stored in `var/local/emhttp/var.ini`
2. Injected as global JS variable: `var csrf_token = "..."`
3. jQuery `ajaxSend` auto-appends to all POST requests
4. Forms get hidden `csrf_token` field on page load

### Dialog Pattern

```javascript
var popup = $("#dialogContainer");
popup.html($("#templatePopup").html());  // Clone template
popup.dialog({
    title: "_(Title)_",
    modal: true,
    buttons: { /* ... */ }
});
dialogStyle();  // Always call after opening
```

## Dependencies

- PHP 8.0+
- Parsedown (for Markdown processing)
- jQuery 3.7+ (loaded from CDN)
- jQuery UI (loaded from CDN)

## License

GPL-2.0 (matching Unraid WebGUI)

## Credits

Based on analysis of [Unraid WebGUI](https://github.com/unraid/webgui) source code.
