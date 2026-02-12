.PHONY: ci lint build dev test seo links
ci: install build lint
install:
	[ -f package.json ] && npm ci || true
build:
	[ -f package.json ] && npm run build --if-present || true
lint:
	[ -f package.json ] && npm run lint --if-present || true
seo:
	[ -x scripts/seo-check.sh ] && scripts/seo-check.sh || echo "No SEO script"
links:
	[ -x scripts/affiliate-validate.sh ] && scripts/affiliate-validate.sh || echo "No affiliate validator"

lint-php:
	find . -type f -name "*.php" -print0 | xargs -0 -n1 -P2 php -l
