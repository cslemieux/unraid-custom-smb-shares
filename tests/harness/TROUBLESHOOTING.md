# Test Harness Troubleshooting Guide

## Common Issues

### Port Already in Use

**Symptom**: `Address already in use` error when starting harness

**Solution**:
```bash
# Find process using port
lsof -ti :8888

# Kill it
kill $(lsof -ti :8888)

# Or let harness handle it (automatic)
```

**Prevention**: Harness automatically kills processes on port before starting

### Server Won't Start

**Symptom**: `PHP server started but not responding` error

**Causes**:
1. Port blocked by firewall
2. PHP not in PATH
3. Router.php syntax error

**Solutions**:
```bash
# Check PHP
which php
php --version

# Test router manually
php -S localhost:8888 -t /path/to/harness tests/harness/router.php

# Check syntax
php -l tests/harness/router.php
```

### Tests Hang

**Symptom**: Tests timeout or hang indefinitely

**Causes**:
1. Modal not closing
2. AJAX request stuck
3. ChromeDriver crashed

**Solutions**:
```bash
# Kill ChromeDriver
pkill -f chromedriver

# Check for zombie processes
ps aux | grep chrome

# Restart Docker/Finch
docker restart $(docker ps -q)
```

### Cleanup Failures

**Symptom**: `/tmp/unraid-test-harness-*` directories remain

**Solution**:
```bash
# Manual cleanup
rm -rf /tmp/unraid-test-harness-*
rm -rf /tmp/chroot-test-*

# Check for lock files
rm -f /tmp/unraid-test-harness-*.lock
```

**Prevention**: Emergency cleanup handler runs automatically

### Modal Tests Fail

**Symptom**: `Modal not found` or timeout errors

**Causes**:
1. jQuery not loaded
2. Dialog CSS missing
3. Animation timing

**Solutions**:
- Increase `HarnessConfig::MODAL_ANIMATION_MS`
- Check browser console for JS errors
- Verify jQuery UI loaded

### AJAX Tests Fail

**Symptom**: `AJAX request failed` or no response

**Causes**:
1. CSRF token missing
2. Endpoint not found
3. PHP syntax error

**Solutions**:
```bash
# Check AJAX log
cat /tmp/ajax-trace.log

# Verify endpoint exists
ls -la source/usr/local/emhttp/plugins/custom.smb.shares/*.php

# Test endpoint manually
curl -X POST http://localhost:8888/plugins/custom.smb.shares/add.php
```

## Performance Issues

### Slow Startup

**Symptom**: Harness takes >2 seconds to start

**Solutions**:
- Check system load: `top`
- Verify adaptive backoff working
- Reduce `HarnessConfig::SERVER_STARTUP_TIMEOUT_MS`

### Slow Tests

**Symptom**: Test suite takes >60 seconds

**Solutions**:
- Enable parallel execution: `phpunit --process-isolation`
- Reduce test count: `phpunit tests/unit/`
- Check for network timeouts

## Configuration

### Adjust Timeouts

Edit `tests/harness/HarnessConfig.php`:

```php
// Server startup
const SERVER_STARTUP_TIMEOUT_MS = 2000;  // Increase if slow

// Modal animations
const MODAL_ANIMATION_MS = 300;  // Increase if flaky

// AJAX timeouts
const AJAX_TIMEOUT_MS = 10000;  // Increase if slow network
```

### Enable Debug Logging

```php
HarnessLogger::setLevel('DEBUG');
HarnessLogger::setEnabled(true);
```

### Change Port

```php
$harness = UnraidTestHarness::setup(['port' => 9999]);
```

## Debugging Tips

### Check Harness State

```bash
# List running harnesses
ls -la /tmp/unraid-test-harness-*

# Check lock files
cat /tmp/unraid-test-harness-*.lock

# View logs
tail -f /tmp/unraid-test-harness-*/logs/*.log
```

### Inspect Test Environment

```php
// In test
var_dump($this->harness);
var_dump(UnraidTestHarness::getUrl());
```

### Browser Debugging

```php
// Take screenshot
$this->driver->takeScreenshot('/tmp/debug.png');

// Get page source
file_put_contents('/tmp/page.html', $this->driver->getPageSource());

// Check console errors
$errors = $this->driver->executeScript('return window.jsErrors || [];');
```

## Getting Help

1. Check this guide first
2. Review test output carefully
3. Enable debug logging
4. Check `/tmp/ajax-trace.log`
5. Take screenshots of failures
6. Report issue with full context
