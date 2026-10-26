<?php
/**
 * Plugin Name: Related Content for CCI Posts Carousel All In One
 * Plugin URI: https://coolcatideas.com/products/cci-posts-carousel-related-content/
 * Description: Fills CCI Posts Carousel All In One rows with related posts and pages from shared terms, or with native WooCommerce related products on product pages.
 * Version: 1.0.0
 * Author: Cool Cat Ideas
 * Author URI: https://coolcatideas.com/
 * Text Domain: related-content-for-cci-posts-carousel-all-in-one
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Posts_Carousel_Related_Content_Addon', false ) ) {

	define( 'WP_POSTS_CAROUSEL_RELATED_CONTENT_FILE', __FILE__ );
	define( 'WP_POSTS_CAROUSEL_RELATED_CONTENT_DIR', plugin_dir_path( __FILE__ ) );

	require_once WP_POSTS_CAROUSEL_RELATED_CONTENT_DIR . 'includes/class-related-content-addon.php';

	add_action(
		'plugins_loaded',
		static function () {
			new WP_Posts_Carousel_Related_Content_Addon();
		}
	);

}
