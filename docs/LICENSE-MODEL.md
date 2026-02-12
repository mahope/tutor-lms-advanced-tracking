# Licensing model (scaffold)

Decision notes:
- Annual license per site (domain-bound)
- Validator is filter-driven to support LemonSqueezy or WooCommerce REST endpoint
- Soft-fail: never blocks admin; shows notice until integrated

Next steps (needs decision):
- Vendor: LemonSqueezy vs Woo (self-hosted) vs Paddle
- Price points + grace period policy
- Remote endpoint spec (auth, payload)

Integration sketch:
- Filter `tlat_validate_license` receives ($default, $license, $domain) and returns validation array
- WP-CLI command for manual checks (todo)
