# Custom SMB Shares Plugin - Test Suite

## Setup

Install PHPUnit:
```bash
composer require --dev phpunit/phpunit
```

## Running Tests

Run all tests:
```bash
cd tests
./run-tests.sh
```

Run specific test suites:
```bash
phpunit --testsuite Unit
phpunit --testsuite Integration
./e2e/test-workflow.sh
```

## Test Structure

- **unit/** - Unit tests for validation logic
- **integration/** - Integration tests for CRUD operations and config generation
- **e2e/** - End-to-end workflow tests
- **mocks/** - Mock Unraid environment helpers

## Mock Environment

Tests use a temporary mock filesystem at `/tmp/unraid-mock-{pid}` that simulates:
- `/boot/config/plugins/custom.smb.shares/` - Plugin config storage
- `/boot/config/smb-extra.conf` - Samba config file
- `/usr/local/emhttp/plugins/custom.smb.shares/` - Plugin web files

The mock environment is automatically cleaned up after tests complete.
