# Related Content for CCI Posts Carousel All In One 1.0.0

## What it solves

Use this no-cost extension when a post, guide or product page needs a carousel that follows the current topic automatically. It excludes the item already being viewed and selects the strongest available matching strategy.

## Matching order

1. For a product-only carousel on a WooCommerce product, the extension calls
   `wc_get_related_products()`. WooCommerce remains responsible for its product
   category/tag algorithm and related-product filters.
2. For posts, pages and other public post types, an active Yoast SEO installation
   can provide a primary term through `yoast_get_primary_term_id()`. Items from
   that primary term are placed first.
3. Empty positions are filled from other public taxonomy terms shared with the
   reference item.

Yoast SEO Premium internal-linking suggestions are an editor feature, not a
documented frontend list of related posts or products. This extension therefore
uses only Yoast's supported primary-term function and does not read Yoast
indexable tables or private Premium data.

## Install and use

1. Install and activate CCI Posts Carousel All In One 2.0.0 or newer.
2. Install and activate `related-content-for-cci-posts-carousel-1.0.0.zip`.
3. Create a query-based carousel and select **Related to the current post or product** in the ordering field.
4. Select the target post type, card template and item limit.
5. Place the saved carousel on a singular post or product page.

The current queried post or product is used as the reference. If there is no usable reference, the filter returns the original query arguments unchanged. Place the carousel on an individual post or product page for recommendations tied to that item, or supply a reference with the filter below. When a valid reference has no shared public terms and WooCommerce returns no matches, the carousel has no related items.

## Disable one integration

Disable native WooCommerce matching and use the shared-taxonomy fallback:

```php
add_filter(
    'wp_posts_carousel_related_content_use_woocommerce',
    '__return_false'
);
```

Disable Yoast primary-term priority:

```php
add_filter(
    'wp_posts_carousel_related_content_use_yoast_primary_term',
    '__return_false'
);
```

Both filters receive four arguments:

- `$enabled` (`bool`) — whether the integration should run; return a boolean.
- `$reference` (`WP_Post`) — reference post or product.
- `$config` (`array`) — normalized carousel configuration.
- `$queryArgs` (`array`) — query arguments prepared by CCI Posts Carousel All In One.

## Reference item filter

Use `wp_posts_carousel_related_content_reference_post_id` to provide a reference item explicitly.

```php
add_filter(
    'wp_posts_carousel_related_content_reference_post_id',
    function (int $referenceId, array $config, array $queryArgs): int {
        return is_page('seasonal-picks') ? 123 : $referenceId;
    },
    10,
    3
);
```

Parameters:

- `$referenceId` (`int`) — detected queried post/product ID or `0`.
- `$config` (`array`) — normalized saved carousel configuration.
- `$queryArgs` (`array`) — query arguments built by the core plugin before related terms are added.

Return a positive WordPress post ID.

## Final query filter

Use `wp_posts_carousel_related_content_query_args` after the reference item, taxonomy clauses, exclusion and date order have been applied.

```php
add_filter(
    'wp_posts_carousel_related_content_query_args',
    function (array $args, WP_Post $reference, array $config): array {
        $args['posts_per_page'] = min(8, (int) $args['posts_per_page']);
        return $args;
    },
    10,
    3
);
```

The filter receives the final `WP_Query` arguments and must return an array. The extension produces no HTML; card markup remains owned by the selected CCI Posts Carousel All In One template.
