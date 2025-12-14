# Test Configuration Files

Per-test configuration files for automated test harness setup with dependency scanning.

## Structure

Each test gets its own JSON config file:

```
tests/configs/
├── ComprehensiveUITest.json
├── AnotherTest.json
└── README.md (this file)
```

## Config Format

```json
{
  "testName": "ComprehensiveUITest",
  "harness": {
    "port": 8888,
    "createTestDirs": ["test1", "test2", "EditTest"]
  },
  "dependencies": {
    "scan": true,
    "scanPaths": [
      "source/usr/local/emhttp/plugins/custom.smb.shares/CustomSMBShares.page"
    ]
  }
}
```

## Fields

### testName
Human-readable test name (for documentation)

### harness.port
Port number for test server (default: 8888)

### harness.createTestDirs
Array of test directories to create in `/mnt/user/`

### dependencies.scan
Enable automatic dependency scanning (default: false)

### dependencies.scanPaths
Array of files/directories to scan for dependencies

## Usage

In your test class:

```php
public static function setUpBeforeClass(): void
{
    $configPath = __DIR__ . '/../configs/MyTest.json';
    self::$harness = UnraidTestHarness::setupFromConfig($configPath);
    self::$baseUrl = self::$harness['url'];
    
    // ... rest of setup
}
```

## How It Works

1. **Config Loading**: Test calls `setupFromConfig($path)`
2. **Dependency Scanning**: If `scan: true`, DependencyScanner analyzes specified paths
3. **Detection**: Scanner finds jQuery, Tablesorter, SweetAlert, Font Awesome, etc.
4. **Generation**: Creates `dependencies.json` with CDN URLs
5. **Injection**: Router.php injects dependencies into HTML responses
6. **Testing**: Browser tests run with all required libraries loaded

## Benefits

- ✅ **Per-test configuration**: Each test specifies its own requirements
- ✅ **Automatic detection**: No manual dependency management
- ✅ **Always current**: Scans before each test run
- ✅ **Isolated**: Tests don't interfere with each other
- ✅ **Fast**: Only scans specified paths

## Example: ComprehensiveUITest.json

```json
{
  "testName": "ComprehensiveUITest",
  "harness": {
    "port": 8888,
    "createTestDirs": ["test1", "test2", "test3", "EditTest", "DeleteTest"]
  },
  "dependencies": {
    "scan": true,
    "scanPaths": [
      "source/usr/local/emhttp/plugins/custom.smb.shares/CustomSMBShares.page",
      "source/usr/local/emhttp/plugins/custom.smb.shares/Edit.page"
    ]
  }
}
```

This config:
- Runs test server on port 8888
- Creates 5 test directories
- Scans both .page files for dependencies
- Auto-detects: jQuery, Tablesorter, SweetAlert, Font Awesome, jQuery UI
- Injects all detected libraries into test pages

## Adding New Tests

1. Create config file: `tests/configs/MyTest.json`
2. Specify harness settings and scan paths
3. Use in test: `UnraidTestHarness::setupFromConfig($path)`
4. Dependencies automatically handled

## Debugging

Check generated dependencies:
```bash
cat tests/harness/dependencies.json
```

View scanner output:
```bash
php tests/harness/DependencyScanner.php source/path/to/scan
```
