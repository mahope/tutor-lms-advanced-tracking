#!/bin/bash
# Test TLAT with Tutor LMS Free and Pro
# Usage: ./scripts/test-with-tutor.sh [free|pro]
#
# Free version: Installed from WordPress.org
# Pro version: Requires manual installation (licensed)

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$SCRIPT_DIR"

MODE=${1:-free}
PORT=8080

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  TLAT + Tutor LMS Compatibility Test"
echo "═══════════════════════════════════════════════════════"
echo ""
echo -e "Mode: ${BLUE}${MODE}${NC}"
echo ""

# Cleanup any existing containers
echo "🧹 Cleaning up existing containers..."
docker compose -f docker-compose.test.yml down -v 2>/dev/null || true

# Start fresh containers
echo "🚀 Starting Docker containers..."
WP_VERSION=6.6 WP_PORT=$PORT docker compose -f docker-compose.test.yml up -d

# Wait for WordPress to be healthy
echo "⏳ Waiting for WordPress to be ready..."
RETRIES=60
while [ $RETRIES -gt 0 ]; do
    if docker ps --filter "name=tlat-test-wp" --filter "health=healthy" --format "{{.Names}}" | grep -q tlat-test-wp; then
        break
    fi
    RETRIES=$((RETRIES - 1))
    sleep 2
done

if [ $RETRIES -eq 0 ]; then
    echo -e "${RED}❌ WordPress container not healthy after timeout${NC}"
    exit 1
fi

# Install WordPress
echo "📦 Installing WordPress..."
docker exec tlat-test-cli wp core install \
    --url="http://localhost:$PORT" \
    --title="TLAT Test Site" \
    --admin_user="admin" \
    --admin_password="admin" \
    --admin_email="admin@test.local" \
    --skip-email \
    --allow-root

# Install Tutor LMS
echo "📦 Installing Tutor LMS ${MODE}..."
if [ "$MODE" = "free" ]; then
    docker exec tlat-test-cli wp plugin install tutor --activate --allow-root
elif [ "$MODE" = "pro" ]; then
    echo -e "${YELLOW}⚠️  Tutor LMS Pro requires manual installation:${NC}"
    echo "   1. Download tutor-pro.zip from Themeum"
    echo "   2. Copy to tests/docker/plugins/"
    echo "   3. Run: docker exec tlat-test-cli wp plugin install /var/www/html/wp-content/plugins/tutor-pro.zip --activate --allow-root"
    echo ""
    echo "For now, installing Free version..."
    docker exec tlat-test-cli wp plugin install tutor --activate --allow-root
fi

# Activate TLAT
echo "🔌 Activating TLAT plugin..."
docker exec tlat-test-cli wp plugin activate tutor-lms-advanced-tracking --allow-root

# Wait for everything to settle
sleep 5

# Test via REST API
echo ""
echo "🔍 Testing plugin status..."
STATUS=$(curl -s "http://localhost:$PORT/wp-json/tlat-test/v1/status")

if echo "$STATUS" | grep -q '"tlat_active":true'; then
    echo -e "${GREEN}✅ TLAT plugin is active${NC}"
else
    echo -e "${RED}❌ TLAT plugin failed to activate${NC}"
    echo "Response: $STATUS"
    exit 1
fi

if echo "$STATUS" | grep -q '"tutor_free_active":true\|"tutor_pro_active":true'; then
    echo -e "${GREEN}✅ Tutor LMS is active${NC}"
else
    echo -e "${RED}❌ Tutor LMS not active${NC}"
    echo "Response: $STATUS"
    exit 1
fi

# Check for critical classes
if echo "$STATUS" | grep -q '"Tutor":true'; then
    echo -e "${GREEN}✅ TUTOR\Tutor class loaded${NC}"
fi

if echo "$STATUS" | grep -q '"TutorAdvancedTracking":true'; then
    echo -e "${GREEN}✅ TutorAdvancedTracking class loaded${NC}"
fi

if echo "$STATUS" | grep -q '"TLAT_Cache":true'; then
    echo -e "${GREEN}✅ Cache system initialized${NC}"
fi

# Extract versions
TLAT_VER=$(echo "$STATUS" | grep -o '"tlat_version":"[^"]*"' | cut -d'"' -f4)
TUTOR_VER=$(echo "$STATUS" | grep -o '"tutor_version":"[^"]*"' | cut -d'"' -f4)
WP_VER=$(echo "$STATUS" | grep -o '"wordpress_version":"[^"]*"' | cut -d'"' -f4)
PHP_VER=$(echo "$STATUS" | grep -o '"php_version":"[^"]*"' | cut -d'"' -f4)

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  Test Results"
echo "═══════════════════════════════════════════════════════"
echo "  WordPress:  $WP_VER"
echo "  PHP:        $PHP_VER"
echo "  Tutor LMS:  $TUTOR_VER ($MODE)"
echo "  TLAT:       $TLAT_VER"
echo "═══════════════════════════════════════════════════════"
echo ""
echo -e "${GREEN}✅ All tests passed!${NC}"
echo ""
echo "Access WordPress at: http://localhost:$PORT"
echo "Admin login: admin / admin"
echo ""
echo "To stop: docker compose -f docker-compose.test.yml down -v"
