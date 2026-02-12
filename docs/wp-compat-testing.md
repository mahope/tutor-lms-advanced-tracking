# WordPress Compatibility Testing

This guide explains how to test TLAT against different WordPress versions using Docker.

## Quick Start

```bash
# Test WordPress 6.6 (default)
make test-up
make test-setup
# Visit http://localhost:8080 - login: admin/admin
make test-down

# Test specific version
WP_VERSION=6.4 docker compose -f docker-compose.test.yml up -d
```

## Automated Test Matrix

Run tests against all supported WordPress versions:

```bash
./scripts/test-wp-compat.sh
```

Or test a specific version:

```bash
./scripts/test-wp-compat.sh 6.6
```

## Supported Versions

| WordPress | PHP | Status |
|-----------|-----|--------|
| 6.4 | 8.2 | Supported |
| 6.5 | 8.2 | Supported |
| 6.6 | 8.2 | Supported |

## Manual Testing

### 1. Start Test Environment

```bash
make test-up
```

### 2. Run WordPress Setup

```bash
make test-setup
```

This installs WordPress with:
- Admin user: `admin` / `admin`
- URL: `http://localhost:8080`
- Plugin auto-activated (if no Tutor LMS dependency issues)

### 3. Install Tutor LMS

For full testing, manually install Tutor LMS:

1. Download from [themeum.com](https://themeum.com/product/tutor-lms/)
2. Go to Plugins → Add New → Upload
3. Install Tutor LMS Free or Pro

### 4. Test Plugin Features

- [ ] Dashboard loads at `/wp-admin/admin.php?page=tlat-dashboard`
- [ ] License activation works
- [ ] Charts render correctly
- [ ] CSV/JSON export functions
- [ ] No PHP errors in debug.log

### 5. Clean Up

```bash
make test-down
```

## Test Checklist

### WordPress 6.4
- [ ] Plugin activates without errors
- [ ] Admin dashboard loads
- [ ] Charts display data
- [ ] Export functions work

### WordPress 6.5
- [ ] Plugin activates without errors
- [ ] Admin dashboard loads
- [ ] Charts display data
- [ ] Export functions work

### WordPress 6.6
- [ ] Plugin activates without errors
- [ ] Admin dashboard loads
- [ ] Charts display data
- [ ] Export functions work

### Tutor LMS Free
- [ ] Enrollments tracked
- [ ] Quiz data displays
- [ ] Cohort analytics work

### Tutor LMS Pro
- [ ] All Free features work
- [ ] Pro-specific data captured
- [ ] No conflicts with Pro features

## REST API Test Endpoint

The test environment exposes a status endpoint:

```bash
curl http://localhost:8080/wp-json/tlat-test/v1/status
```

Response:
```json
{
  "wordpress_version": "6.6.2",
  "php_version": "8.2.28",
  "tlat_active": true,
  "tutor_lms_active": false,
  "tutor_lms_version": null,
  "mysql_version": "8.0.41",
  "timestamp": "2026-02-13 00:30:00"
}
```

## Troubleshooting

### Container won't start
- Check Docker is running: `docker info`
- Check port 8080 is free: `lsof -i :8080`
- Try different port: `WP_PORT=8888 make test-up`

### Plugin not activating
- Check for PHP errors: `docker compose -f docker-compose.test.yml logs wordpress`
- Verify Tutor LMS is installed if required

### Slow first start
- First run downloads ~500MB of Docker images
- Subsequent runs use cached images

## CI/CD Integration

For GitHub Actions, see `.github/workflows/wp-compat.yml` (if present).

Basic example:
```yaml
- name: Test WordPress Compatibility
  run: |
    docker compose -f docker-compose.test.yml up -d
    sleep 60
    curl -f http://localhost:8080/wp-json/tlat-test/v1/status
```
