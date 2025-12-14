# Samba Mock for Test Harness

Complete Samba service simulation for testing plugin interactions.

## Features

- **Service Control**: Mock `/etc/rc.d/rc.samba` script
- **Config Validation**: Mock `testparm` command
- **Config Reload**: Mock `smbcontrol` command
- **Status Tracking**: Simulated service state
- **Logging**: All operations logged
- **Config Management**: Read/write Samba configuration

## Mock Scripts

### 1. `/etc/rc.d/rc.samba`

```bash
rc.samba start    # Start Samba (sets status to 'running')
rc.samba stop     # Stop Samba (sets status to 'stopped')
rc.samba restart  # Restart Samba
rc.samba reload   # Reload configuration
rc.samba status   # Check status (exit 0 if running, 3 if stopped)
```

### 2. `/usr/bin/testparm`

```bash
testparm          # Validate and display config
testparm -s       # Suppress header, show config only
```

**Validation**:
- Checks config file exists
- Verifies share sections present
- Detects syntax errors

### 3. `/usr/bin/smbcontrol`

```bash
smbcontrol all reload-config  # Reload Samba configuration
```

## API

### Initialization

```php
SambaMock::init($harnessDir);
```

Creates mock scripts and initializes status.

### Service Control

```php
// Set status
SambaMock::setStatus('running');
SambaMock::setStatus('stopped');

// Get status
$status = SambaMock::getStatus();  // 'running' or 'stopped'
```

### Configuration Management

```php
// Write config
$config = "[TestShare]\npath = /mnt/user/test\n";
SambaMock::writeConfig($config);

// Read config
$config = SambaMock::readConfig();

// Get config file path
$path = SambaMock::getConfigFile();
```

### Validation

```php
$result = SambaMock::validateConfig();
// Returns: ['valid' => bool, 'output' => string, 'exit_code' => int]

if ($result['valid']) {
    echo "Config is valid\n";
} else {
    echo "Config errors:\n" . $result['output'];
}
```

### Reload

```php
$result = SambaMock::reload();
// Returns: ['success' => bool, 'output' => string, 'exit_code' => int]
```

### Share Queries

```php
// Get all shares
$shares = SambaMock::getShares();  // ['Share1', 'Share2']

// Check if share exists
if (SambaMock::hasShare('TestShare')) {
    echo "Share exists\n";
}

// Get share config section
$config = SambaMock::getShareConfig('TestShare');
// Returns: "path = /mnt/user/test\nbrowseable = yes\n"
```

### Logging

```php
// Get log
$log = SambaMock::getLog();

// Clear log
SambaMock::clearLog();

// Manual log entry
SambaMock::log('Custom message');
```

## Test Examples

### Test Config Generation

```php
public function testPluginWritesConfig()
{
    // Clear existing
    SambaMock::writeConfig('');
    
    // Trigger plugin action (add share)
    // ...
    
    // Verify config written
    $config = SambaMock::readConfig();
    $this->assertStringContainsString('[TestShare]', $config);
}
```

### Test Service Reload

```php
public function testReloadAfterChange()
{
    SambaMock::clearLog();
    
    // Trigger plugin reload
    // ...
    
    // Verify reload was called
    $log = SambaMock::getLog();
    $this->assertStringContainsString('reload', $log);
}
```

### Test Validation

```php
public function testConfigValidation()
{
    $config = "[Share]\npath = /mnt/user/test\n";
    SambaMock::writeConfig($config);
    
    $result = SambaMock::validateConfig();
    $this->assertTrue($result['valid']);
}
```

### Test Status Check

```php
public function testStatusDisplay()
{
    SambaMock::setStatus('running');
    
    // Load page
    $driver->get($baseUrl . '/Settings/CustomSMBShares');
    
    // Verify status shown
    $status = $driver->findElement(By::id('samba-status'))->getText();
    $this->assertStringContainsString('running', $status);
}
```

## Integration with Plugin

The plugin's Samba interactions work transparently with the mock:

```php
// Plugin code (unchanged)
exec('/etc/rc.d/rc.samba reload', $output, $ret);

// In test harness:
// - PATH includes {harness_dir}/etc/rc.d
// - Executes mock rc.samba script
// - Returns simulated output
```

### Config File Location

Plugin writes to:
```
{harness_dir}/boot/config/plugins/custom.smb.shares/smb-extra.conf
```

Mock scripts read from same location.

## Log Format

```
[2025-01-18 06:30:00] Samba mock initialized
[2025-01-18 06:30:05] Config written (245 bytes)
[2025-01-18 06:30:06] Config validation: PASS
[2025-01-18 06:30:07] Configuration reloaded via smbcontrol
[2025-01-18 06:30:10] Status changed to: stopped
```

## Limitations

1. **No Actual Service**: Doesn't run real smbd/nmbd
2. **No Network**: Can't test actual SMB connections
3. **Simplified Validation**: Basic syntax checking only
4. **No Permissions**: Doesn't enforce Samba ACLs

## Benefits

- ✅ Fast execution (no service startup)
- ✅ Deterministic behavior
- ✅ Complete operation logging
- ✅ Easy state manipulation
- ✅ No system dependencies
- ✅ Parallel test execution

## Future Enhancements

- [ ] Simulate service startup delays
- [ ] Mock smbstatus output
- [ ] Simulate connection failures
- [ ] Mock smbclient for connection testing
- [ ] Simulate permission errors
