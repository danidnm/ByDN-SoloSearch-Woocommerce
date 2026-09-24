# SoloSearch Official Plugin

WordPress/WooCommerce plugin for [SoloSearch](https://solosearch.app) — an instant search engine and management panel for ecommerce, built as an affordable alternative to costlier options like Doofinder.

SoloSearch indexes your product catalog and serves a fast, typo-tolerant search widget that drops into your storefront with a single `<script>` tag. Search behavior, ranking, boosts, filters, and the widget's own look and feel are all managed from SoloSearch's panel — no redeploys needed on the WordPress side once this plugin is installed and configured.

- **Website:** [solosearch.app](https://solosearch.app)
- **Documentation:** [solosearch.app/docs](https://solosearch.app/docs)
- **Sign up:** [solosearch.app/panel/register](https://solosearch.app/panel/register)

## What this plugin does

- **Generates the product feed** SoloSearch indexes — a scheduled job (or an on-demand command) builds an XML feed from your published products and writes it to a public path SoloSearch fetches.
- **Handles WooCommerce-specific product logic** so the feed is accurate out of the box: variable-product pricing (min variation price, with sale detection), stock status, flat category names, and which products can't be added to cart directly from a search result (variable, grouped, and external/affiliate products, or anything that isn't purchasable — out of stock without backorders, for example).
- **Embeds the SoloSearch widget** on your storefront — no manual `<script>` tag editing required, it's all driven by the plugin's own settings. Only added on actual WooCommerce pages (shop, categories, product pages), so your site's native search stays untouched everywhere else.
- **Wires up add-to-cart** from search results, so a product card's "add to cart" button actually adds to the WooCommerce cart and refreshes the mini-cart — both the classic fragments-based mini-cart and the Cart/Checkout blocks' reactive one.
- **Notifies SoloSearch after every feed generation**, asking it to re-fetch and reindex immediately instead of waiting for its own schedule.

## Requirements

- PHP 8.0 or later
- WordPress with WooCommerce 8.0 or later (tested up to 10.1)

## Installation

This plugin isn't listed on the WordPress.org directory — install it from a release ZIP instead.

### Upload via WP Admin (recommended)

1. Download the latest ZIP: [solosearch-for-woocommerce.zip](https://github.com/danidnm/ByDN-SoloSearch-Woocommerce/releases/latest/download/solosearch-for-woocommerce.zip)
2. In your site, go to **Plugins → Add New → Upload Plugin**, choose the file, and click **Install Now**.
3. Activate **SoloSearch for WooCommerce**.

### WP-CLI

```bash
wp plugin install https://github.com/danidnm/ByDN-SoloSearch-Woocommerce/releases/latest/download/solosearch-for-woocommerce.zip --activate
```

No Composer/build step required either way — the plugin has no external dependencies and ships ready to run.

## Configuration

Configuration lives under **WooCommerce → Settings → SoloSearch**, split into four sections.

### General

Enable/disable the plugin, and the API URL and Token used to talk to SoloSearch.

### Feed Generation

Controls the scheduled daily feed generation (time and on/off).

### Field Mapping

Maps additional WooCommerce product attributes/meta to SoloSearch feed fields, on top of the structural fields (`id`, `sku`, `title`, `link`, `image`, `price`, `sale_price`, `availability`, `disable_add_to_cart`, `categories`, `currency`) that are always included automatically.

### Widget Embed

Enables the SoloSearch widget on the storefront and configures which search engine it connects to, without touching any theme template.

## WP-CLI commands

```bash
# Generate the feed manually, regardless of the scheduled cron or "Enable automatic generation"
wp solosearch feed generate
```

## Support

Questions or issues: [hello@solosearch.app](mailto:hello@solosearch.app)

## License

Proprietary.
