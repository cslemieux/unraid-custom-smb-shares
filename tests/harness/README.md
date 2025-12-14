# Unraid Test Harness

A complete test environment that mimics Unraid's structure with authentication bypass for Selenium testing.

## Features

- **Auth Bypass**: Always returns 200 for auth requests
- **CSRF Validation**: Maintains CSRF token validation like production
- **Chroot Environment**: Isolated `/mnt/` structure
- **Full Plugin Support**: Runs actual plugin code
- **Screenshot Capture**: Automated UI screenshots
- **No Unraid Server Required**: Runs locally with PHP built-in server

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                  PHPUnit Test Suite                     │
│  ┌──────────────────┬──────────────────┬─────────────┐ │
│  │  E2ETestBase     │ IntegrationTest  │  Unit Tests │ │
│  │  (Selenium)      │  (Chroot)        │             │ │
│  └──────────────────┴──────────────────┴─────────────┘ │
├─────────────────────────────────────────────────────────┤
│              UnraidTestHarness (Core)                   │
│  ┌──────────────┬──────────────┬────────────────────┐  │
│  │ Router.php   │ SambaMock    │ ChrootEnvironment  │  │
│  │ (Page parser)│ (rc.samba)   │ (/mnt/ structure)  │  │
│  └──────────────┴──────────────┴────────────────────┘  │
│  ┌──────────────┬──────────────┬────────────────────┐  │
│  │ HarnessConfig│ ProcessMgr   │ HarnessLogger      │  │
│  │ (Constants)  │ (Kill procs) │ (Logging)          │  │
│  └──────────────┴──────────────┴────────────────────┘  │
├─────────────────────────────────────────────────────────┤
│         PHP Built-in Server (localhost:8888)            │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Document Root: /tmp/unraid-test-harness-xxx/    │  │
│  │  Router: tests/harness/router.php                │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

## Directory Structure

```
/tmp/unraid-test-harness-xxx/
├── usr/local/emhttp/
│   ├── auth-request.php          # Auth bypass (always 200)
│   ├── webGui/include/
│   │   └── local_prepend.php     # CSRF validation
│   └── plugins/
│       └── custom.smb.shares/    # Plugin files
├── var/local/emhttp/
│   └── var.ini                   # CSRF token
├── boot/config/plugins/          # Plugin config
├── mnt/user/                     # Test shares
├── logs/                         # Server logs
└── run/                          # PID files
```

## Components

### 1. Auth Bypass (`auth-request.php`)

```php
session_start();
$_SESSION['unraid_login'] = time();
http_response_code(200);
```

Always authorizes requests - no login required.

### 2. CSRF Validation (`local_prepend.php`)

Maintains production CSRF validation:
- Validates POST requests
- Checks `csrf_token` from `var.ini`
- Removes token from `$_POST` after validation

### 3. Nginx Configuration

- Listens on port 8888
- Routes to PHP-FPM via Unix socket
- Serves plugin files from `/plugins/`
- Uses auth_request directive

### 4. PHP-FPM Configuration

- Auto-prepends `local_prepend.php`
- Unix socket communication
- Isolated process pool

## Usage

### Prerequisites

```bash
# macOS
brew install nginx php

# Start ChromeDriver
chromedriver --port=9515 &
```

### Run Tests

```bash
./run-harness-tests.sh
```

### Manual Usage

```php
require_once 'tests/harness/UnraidTestHarness.php';

// Setup
$harness = UnraidTestHarness::setup(8888);
echo "Test server: " . $harness['url'] . "\n";

// Run tests...

// Teardown
UnraidTestHarness::teardown();
```

## Test Examples

### Basic Page Load

```php
public function testPageLoads()
{
    self::$driver->get(self::$baseUrl . '/Settings/CustomSMBShares');
    
    // No auth redirect!
    $currentUrl = self::$driver->getCurrentURL();
    $this->assertStringContainsString('CustomSMBShares', $currentUrl);
}
```

### Screenshot Capture

```php
private function takeScreenshot($name)
{
    $filename = 'screenshots/' . $name . '.png';
    self::$driver->takeScreenshot($filename);
}
```

### Form Interaction

```php
public function testFormSubmission()
{
    $nameField = self::$driver->findElement(WebDriverBy::name('name'));
    $nameField->sendKeys('TestShare');
    
    $submitButton = self::$driver->findElement(WebDriverBy::cssSelector('input[type="submit"]'));
    $submitButton->click();
    
    // CSRF token automatically included by jQuery
}
```

## Benefits

### vs. Real Unraid Server

- ✅ No authentication required
- ✅ Isolated environment
- ✅ Fast setup/teardown
- ✅ No server access needed
- ✅ Parallel test execution
- ✅ Screenshot automation

### vs. Mock Environment

- ✅ Real nginx + PHP-FPM
- ✅ Actual plugin code
- ✅ Real CSRF validation
- ✅ True browser rendering
- ✅ Realistic URL routing

## Troubleshooting

### Port Already in Use

```bash
# Change port in test
UnraidTestHarness::setup(8889);
```

### nginx Won't Start

```bash
# Check logs
cat /tmp/unraid-test-harness-xxx/logs/nginx-error.log
```

### PHP-FPM Issues

```bash
# Check logs
cat /tmp/unraid-test-harness-xxx/logs/php-fpm.log

# Verify socket
ls -la /tmp/unraid-test-harness-xxx/run/php-fpm.sock
```

### Screenshots Not Saving

```bash
# Create directory
mkdir -p screenshots/

# Check permissions
chmod 755 screenshots/
```

## Limitations

1. **No Samba**: Doesn't run actual Samba service
2. **No Array**: No Unraid array management
3. **No Docker**: Docker integration not available
4. **Simplified Auth**: Auth bypass only, no user management

## Troubleshooting

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for common issues and solutions.

## Future Enhancements

- [ ] Multiple user sessions
- [ ] Theme switching tests
- [ ] Mobile viewport testing
- [ ] Performance profiling
- [ ] Network throttling
- [ ] Accessibility testing
