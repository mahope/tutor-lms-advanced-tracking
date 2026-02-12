#!/bin/bash
# WordPress Compatibility Test Runner
# Tests TLAT against WordPress 6.4, 6.5, 6.6
#
# Usage: ./scripts/test-wp-compat.sh [version]
# Examples:
#   ./scripts/test-wp-compat.sh          # Test all versions
#   ./scripts/test-wp-compat.sh 6.6      # Test specific version

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$SCRIPT_DIR"

# Test versions
WP_VERSIONS=("6.4" "6.5" "6.6")
RESULTS_FILE="tests/docker/compat-results.md"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# If specific version requested
if [ -n "$1" ]; then
    WP_VERSIONS=("$1")
fi

echo "═══════════════════════════════════════════════════════"
echo "  TLAT WordPress Compatibility Test Suite"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "Testing WordPress versions: ${WP_VERSIONS[*]}"
echo ""

# Initialize results
mkdir -p tests/docker
cat > "$RESULTS_FILE" << EOF
# TLAT WordPress Compatibility Test Results

**Test Date:** $(date '+%Y-%m-%d %H:%M:%S')
**Plugin Version:** $(grep "Version:" tutor-lms-advanced-tracking.php | head -1 | sed 's/.*Version: //')

| WordPress | PHP | Status | Notes |
|-----------|-----|--------|-------|
EOF

test_version() {
    local WP_VERSION=$1
    local PORT=$((8080 + ${WP_VERSION//./}))
    
    echo ""
    echo "────────────────────────────────────────────"
    echo -e "${YELLOW}Testing WordPress $WP_VERSION...${NC}"
    echo "────────────────────────────────────────────"
    
    # Clean up any existing containers
    WP_VERSION=$WP_VERSION WP_PORT=$PORT docker compose -f docker-compose.test.yml down -v 2>/dev/null || true
    
    # Start containers
    echo "🚀 Starting containers..."
    if ! WP_VERSION=$WP_VERSION WP_PORT=$PORT docker compose -f docker-compose.test.yml up -d; then
        echo -e "${RED}❌ Failed to start containers${NC}"
        echo "| $WP_VERSION | - | ❌ Failed | Container startup failed |" >> "$RESULTS_FILE"
        return 1
    fi
    
    # Wait for WordPress to be ready
    echo "⏳ Waiting for WordPress..."
    local RETRIES=30
    while [ $RETRIES -gt 0 ]; do
        if curl -s "http://localhost:$PORT/wp-admin/install.php" > /dev/null 2>&1; then
            break
        fi
        RETRIES=$((RETRIES - 1))
        sleep 2
    done
    
    if [ $RETRIES -eq 0 ]; then
        echo -e "${RED}❌ WordPress failed to start${NC}"
        echo "| $WP_VERSION | - | ❌ Failed | WordPress didn't start |" >> "$RESULTS_FILE"
        WP_VERSION=$WP_VERSION WP_PORT=$PORT docker compose -f docker-compose.test.yml down -v
        return 1
    fi
    
    # Run setup
    echo "📦 Running WordPress setup..."
    if ! docker compose -f docker-compose.test.yml exec -T wp-cli /scripts/setup-wordpress.sh; then
        echo -e "${RED}❌ Setup failed${NC}"
        echo "| $WP_VERSION | - | ❌ Failed | Setup script failed |" >> "$RESULTS_FILE"
        WP_VERSION=$WP_VERSION WP_PORT=$PORT docker compose -f docker-compose.test.yml down -v
        return 1
    fi
    
    # Get version info from REST API
    echo "🔍 Checking plugin status..."
    sleep 2
    local STATUS=$(curl -s "http://localhost:$PORT/wp-json/tlat-test/v1/status" 2>/dev/null || echo "{}")
    
    local ACTUAL_WP=$(echo "$STATUS" | grep -o '"wordpress_version":"[^"]*"' | cut -d'"' -f4)
    local PHP_VER=$(echo "$STATUS" | grep -o '"php_version":"[^"]*"' | cut -d'"' -f4)
    local TLAT_ACTIVE=$(echo "$STATUS" | grep -o '"tlat_active":[^,}]*' | cut -d':' -f2)
    
    # Log results
    if [ "$TLAT_ACTIVE" = "true" ]; then
        echo -e "${GREEN}✅ WordPress $ACTUAL_WP (PHP $PHP_VER) - PASSED${NC}"
        echo "| $ACTUAL_WP | $PHP_VER | ✅ Passed | Plugin loads correctly |" >> "$RESULTS_FILE"
    else
        echo -e "${YELLOW}⚠️ WordPress $ACTUAL_WP (PHP $PHP_VER) - Plugin inactive${NC}"
        echo "| $ACTUAL_WP | $PHP_VER | ⚠️ Warning | Plugin inactive (may need Tutor LMS) |" >> "$RESULTS_FILE"
    fi
    
    # Cleanup
    echo "🧹 Cleaning up..."
    WP_VERSION=$WP_VERSION WP_PORT=$PORT docker compose -f docker-compose.test.yml down -v
    
    return 0
}

# Run tests
PASSED=0
FAILED=0

for VERSION in "${WP_VERSIONS[@]}"; do
    if test_version "$VERSION"; then
        PASSED=$((PASSED + 1))
    else
        FAILED=$((FAILED + 1))
    fi
done

# Summary
echo ""
echo "═══════════════════════════════════════════════════════"
echo "  Test Summary"
echo "═══════════════════════════════════════════════════════"
echo -e "  ${GREEN}Passed: $PASSED${NC}"
echo -e "  ${RED}Failed: $FAILED${NC}"
echo ""
echo "Full results: $RESULTS_FILE"
echo ""

# Add summary to results file
cat >> "$RESULTS_FILE" << EOF

## Summary

- **Passed:** $PASSED
- **Failed:** $FAILED

## Notes

- Tests run without Tutor LMS installed (plugin may show as inactive)
- Full testing requires manual verification with Tutor LMS Free and Pro
- See TESTING.md for manual test procedures
EOF

exit $FAILED
