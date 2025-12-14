# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- PHPStan stubs for Unraid-provided functions
- User-facing README with installation guide
- CHANGELOG.md
- CONTRIBUTING.md

### Fixed
- PHPStan level 8 compliance (0 errors)
- E2E test CSRF token handling
- SambaMock script creation timing

## [2025.01.18] - 2025-01-18

### Added
- Initial release
- Create, edit, delete custom SMB shares
- User/group browser with autocomplete search
- Permission levels: Read Only, Read/Write
- Path browser with directory picker
- Client-side and server-side validation
- Automatic Samba configuration generation
- Samba service reload with testparm validation
- Import/export configuration
- Comprehensive test suite (176 tests)
  - Unit tests for validation logic
  - Integration tests for CRUD operations
  - E2E browser tests with Selenium
  - Security penetration tests

### Security
- Input sanitization on all user data
- Output escaping with htmlspecialchars
- Path traversal prevention (realpath canonicalization)
- CSRF protection via Unraid global validation
