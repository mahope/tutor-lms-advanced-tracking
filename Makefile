.PHONY: ci lint build dev test seo links test-wp test-wp-compat

ci: install build lint lint-php

install:
	[ -f package.json ] && npm ci || true
	[ -f composer.json ] && composer install --no-dev --prefer-dist || true

build:
	[ -f package.json ] && npm run build --if-present || true

lint:
	[ -f package.json ] && npm run lint --if-present || true

lint-php:
	find . -type f -name "*.php" ! -path "./vendor/*" -print0 | xargs -0 -n1 -P2 php -l

seo:
	[ -x scripts/seo-check.sh ] && scripts/seo-check.sh || echo "No SEO script"

links:
	[ -x scripts/affiliate-validate.sh ] && scripts/affiliate-validate.sh || echo "No affiliate validator"

# WordPress compatibility testing (requires Docker)
test-wp-compat:
	./scripts/test-wp-compat.sh

test-wp-64:
	./scripts/test-wp-compat.sh 6.4

test-wp-65:
	./scripts/test-wp-compat.sh 6.5

test-wp-66:
	./scripts/test-wp-compat.sh 6.6

# Start local test environment
test-up:
	docker compose -f docker-compose.test.yml up -d
	@echo "WordPress available at http://localhost:8080"

test-down:
	docker compose -f docker-compose.test.yml down -v

test-setup:
	docker compose -f docker-compose.test.yml exec wp-cli /scripts/setup-wordpress.sh

# PHPUnit tests
test:
	[ -f vendor/bin/phpunit ] && vendor/bin/phpunit || echo "Run 'composer install' first"
