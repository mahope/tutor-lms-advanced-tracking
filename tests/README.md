# TLAT Plugin Tests

This directory contains PHPUnit tests for Tutor LMS Advanced Tracking.

## Structure

```
tests/
├── bootstrap.php          # Test bootstrap with WordPress mocks
├── Unit/                  # Unit tests (no external deps)
│   └── LicenseValidatorTest.php
└── Integration/           # Integration tests (requires license server)
    └── LicenseFlowTest.php
```

## Running Tests

### Prerequisites

1. PHP 7.4+ with extensions: mbstring, intl, pdo_sqlite
2. Composer

### Install Dependencies

```bash
composer install
```

### Run Unit Tests

```bash
# All unit tests
composer test

# Or directly
vendor/bin/phpunit --testsuite Unit
```

### Run Unit Tests with Coverage

```bash
composer test:coverage

# Or directly
vendor/bin/phpunit --testsuite Unit --coverage-text
```

### Run Integration Tests

Integration tests require a running license server:

```bash
# Set environment variables
export TLAT_TEST_SERVER_URL="https://license.example.com"
export TLAT_TEST_LICENSE_KEY="TLAT-XXXX-XXXX-XXXX-XXXX"

# Run integration tests
vendor/bin/phpunit --testsuite Integration
```

## CI/CD

Tests run automatically via GitHub Actions on:
- Push to `main` or `develop` branch
- Pull requests to `main`

The CI workflow tests against multiple PHP versions (7.4, 8.0, 8.1, 8.2, 8.3) 
and WordPress versions (6.4, 6.5, 6.6).

## Writing Tests

### Unit Tests

Unit tests should:
- Not make external HTTP requests
- Not depend on a database
- Test individual methods in isolation
- Use mocks for WordPress functions (see bootstrap.php)

### Integration Tests

Integration tests should:
- Test end-to-end flows
- Use the `@group integration` annotation
- Skip gracefully if required resources unavailable

## Test Coverage Goals

- [ ] License activation/deactivation
- [ ] License validation
- [ ] Grace period logic
- [ ] Heartbeat mechanism
- [ ] Admin notices
- [ ] REST API endpoints
