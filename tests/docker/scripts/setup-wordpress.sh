#!/bin/bash
# Setup WordPress for TLAT testing
# Run inside wp-cli container: docker-compose exec wp-cli /scripts/setup-wordpress.sh

set -e

echo "🚀 Setting up WordPress for TLAT testing..."

# Wait for database
echo "⏳ Waiting for database..."
until wp db check --allow-root 2>/dev/null; do
    sleep 2
done

# Check if already installed
if wp core is-installed --allow-root 2>/dev/null; then
    echo "✅ WordPress already installed"
else
    echo "📦 Installing WordPress..."
    wp core install \
        --url="http://localhost:8080" \
        --title="TLAT Test Site" \
        --admin_user="admin" \
        --admin_password="admin" \
        --admin_email="admin@test.local" \
        --skip-email \
        --allow-root
fi

# Get WordPress version for report
WP_VERSION=$(wp core version --allow-root)
PHP_VERSION=$(php -v | head -1 | cut -d' ' -f2)

echo ""
echo "═══════════════════════════════════════════"
echo "  TLAT Test Environment"
echo "═══════════════════════════════════════════"
echo "  WordPress: $WP_VERSION"
echo "  PHP:       $PHP_VERSION"
echo "═══════════════════════════════════════════"
echo ""

# Activate plugin
echo "🔌 Activating TLAT plugin..."
wp plugin activate tutor-lms-advanced-tracking --allow-root 2>/dev/null || echo "⚠️  Plugin activation skipped (may need Tutor LMS)"

# List active plugins
echo ""
echo "📋 Active plugins:"
wp plugin list --status=active --allow-root

# Check for PHP errors in plugin
echo ""
echo "🔍 Syntax checking plugin files..."
ERRORS=0
for file in /var/www/html/wp-content/plugins/tutor-lms-advanced-tracking/*.php \
            /var/www/html/wp-content/plugins/tutor-lms-advanced-tracking/includes/*.php; do
    if [ -f "$file" ]; then
        if ! php -l "$file" > /dev/null 2>&1; then
            echo "❌ Syntax error in: $file"
            ERRORS=$((ERRORS + 1))
        fi
    fi
done

if [ $ERRORS -eq 0 ]; then
    echo "✅ All PHP files pass syntax check"
else
    echo "❌ Found $ERRORS files with syntax errors"
    exit 1
fi

echo ""
echo "✅ Setup complete!"
echo ""
echo "Access WordPress at: http://localhost:8080"
echo "Admin login: admin / admin"
