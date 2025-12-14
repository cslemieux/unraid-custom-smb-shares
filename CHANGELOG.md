# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2025.12.14] - 2025-12-14

### Added
- Initial public release
- Create, edit, delete custom SMB shares
- User/group browser with autocomplete search
- Permission levels: Read Only, Read/Write
- Path browser with directory picker
- Client-side and server-side validation
- Automatic Samba configuration generation
- Samba service reload with testparm validation
- Import/export configuration (JSON)
- Quick presets (Basic, Read-Only, Secure, macOS, Time Machine)
- Service status display with auto-refresh
- Comprehensive test suite (189 tests)
  - Unit tests for validation logic
  - Integration tests for CRUD operations
  - E2E browser tests with Selenium
- CI/CD with GitHub Actions
  - Multi-PHP testing (8.0-8.3)
  - Security scanning (composer audit)
  - Automated releases on tags
  - Dependabot for dependency updates
- PHPStan level 8 compliance
- PSR-12 code style

### Security
- Input sanitization on all user data
- Output escaping with htmlspecialchars
- Path traversal prevention (realpath canonicalization)
- CSRF protection via Unraid global validation
