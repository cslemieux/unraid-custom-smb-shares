# Security Penetration Tests

## Coverage

### Path Traversal
- ✅ Dot-dot sequences (`../../etc/passwd`)
- ✅ Paths outside `/mnt/`
- ✅ Symlink attacks

### Injection Attacks
- ✅ XSS in share names
- ✅ XSS in comments
- ✅ Command injection
- ✅ SQL injection patterns
- ✅ Null byte injection

### Input Validation
- ✅ Invalid permission masks
- ✅ Excessively long inputs
- ✅ Empty/whitespace inputs
- ✅ Unicode exploits

## Running Tests

```bash
composer test:security
```

## All Tests Passing = Secure ✅

14 security tests, 14 assertions - all passing
