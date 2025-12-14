# Testing Scripts

## API Testing

### test-api.sh
Helper script for testing Unraid plugin APIs with automatic authentication and CSRF handling.

**Usage:**
```bash
# GET request
./scripts/test-api.sh GET /plugins/custom.smb.shares/users.php

# POST request with form data
./scripts/test-api.sh POST /plugins/custom.smb.shares/add.php \
  -d name=TestShare \
  -d path=/mnt/user/test \
  -d comment="Test share"
```

**Features:**
- Automatic session management (uses unraid-login.sh)
- CSRF token extraction and injection
- Supports GET and POST methods
- Form data and JSON support

**CSRF Handling:**
- Extracts token from `/var/local/emhttp/var.ini` on server
- Automatically includes in POST requests
- Validates responses for CSRF errors

## DOM Fetching

### unraid-dom-fetch.sh
Fetch and analyze DOM from Unraid pages.

**Usage:**
```bash
# Fetch page DOM
./scripts/unraid-dom-fetch.sh fetch /Settings/CustomSMBShares

# Extract specific elements
./scripts/unraid-dom-fetch.sh extract /Settings/CustomSMBShares forms
```

**CSRF Handling:**
- Uses authenticated session cookie
- GET requests don't require CSRF token
- Session automatically refreshed if expired

## Login Management

### unraid-login.sh
Manage Unraid authentication sessions.

**Usage:**
```bash
# Login
./scripts/unraid-login.sh login

# Test session
./scripts/unraid-login.sh test

# Show credentials
./scripts/unraid-login.sh show
```

## E2E Testing

### run-e2e-tests.sh
Run end-to-end tests with ChromeDriver management.

**Usage:**
```bash
# Run full suite
./scripts/run-e2e-tests.sh

# Run individually (workaround for cleanup issues)
./scripts/run-e2e-tests.sh --individual
```

**Features:**
- Automatic ChromeDriver lifecycle management
- Health checks before running tests
- Cleanup on exit/interrupt
- Timeout enforcement (300s suite, 60s per test)
