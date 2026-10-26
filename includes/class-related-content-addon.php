<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WP_Posts_Carousel_Related_Content_Addon {

	const VERSION  = '1.0.0';
	const ORDER_BY = 'wpc_related_content';

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
		add_filter( 'wp_posts_carousel_features', array( $this, 'register_feature' ) );
		add_filter( 'wp_posts_carousel_post_criteria_meta_box_orders_by', array( $this, 'add_order_option' ) );
		add_filter( 'wp_posts_carousel_order_by_map', array( $this, 'add_order_by_map' ) );
		add_filter( 'wp_posts_carousel_query_args', array( $this, 'apply_related_content_source' ), 10, 2 );
	}

	public function dependency_notice() {
		if ( class_exists( 'WP_Posts_Carousel_Query_Builder' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>'
			. esc_html__( 'Related Content for CCI Posts Carousel All In One requires CCI Posts Carousel All In One 2.0.0 or newer.', 'related-content-for-cci-posts-carousel-all-in-one' )
			. '</p></div>';
	}

	public function register_feature( $features ) {
		$features['related_content_source']      = array(
			'label'       => __( 'Related content source', 'related-content-for-cci-posts-carousel-all-in-one' ),
			'description' => __( 'Builds a carousel from related posts or products, with native WooCommerce matching and optional Yoast SEO primary-term ranking.', 'related-content-for-cci-posts-carousel-all-in-one' ),
			'enabled'     => class_exists( 'WP_Posts_Carousel_Query_Builder' ),
			'source'      => 'addon',
			'author'      => 'Cool Cat Ideas',
			'version'     => self::VERSION,
			'plan'        => 'free',
		);
		$features['related_content_yoast_seo']   = array(
			'label'       => __( 'Yoast SEO primary-term relevance', 'related-content-for-cci-posts-carousel-all-in-one' ),
			'description' => __( 'Prioritizes content assigned to the Yoast SEO primary term, then fills the carousel from other shared public terms.', 'related-content-for-cci-posts-carousel-all-in-one' ),
			'enabled'     => function_exists( 'yoast_get_primary_term_id' ),
			'source'      => 'related-content',
			'plan'        => 'free',
		);
		$features['related_content_woocommerce'] = array(
			'label'       => __( 'WooCommerce related products', 'related-content-for-cci-posts-carousel-all-in-one' ),
			'description' => __( 'Uses the native WooCommerce related-product matcher for product-only carousels.', 'related-content-for-cci-posts-carousel-all-in-one' ),
			'enabled'     => function_exists( 'wc_get_related_products' ),
			'source'      => 'related-content',
			'plan'        => 'free',
		);

		return $features;
	}

	public function add_order_option( $options ) {
		$options[] = array(
			'value' => self::ORDER_BY,
			'label' => __( 'Related to the current post or product', 'related-content-for-cci-posts-carousel-all-in-one' ),
		);

		return $options;
	}

	public function add_order_by_map( $map ) {
		$map[ self::ORDER_BY ] = self::ORDER_BY;

		return $map;
	}

	public function apply_related_content_source( $args, $config ) {
		$source = isset( $config['source'] ) && is_array( $config['source'] ) ? $config['source'] : array();

		if ( empty( $source['order_by'] ) || $source['order_by'] !== self::ORDER_BY ) {
			return $args;
		}

		$reference_id = ! empty( $source['related_to_post_id'] )
			? absint( $source['related_to_post_id'] )
			: absint( get_queried_object_id() );
		$reference_id = absint(
			apply_filters(
				'wp_posts_carousel_related_content_reference_post_id',
				$reference_id,
				$config,
				$args
			)
		);
		$reference    = $reference_id ? get_post( $reference_id ) : null;

		if ( ! $reference ) {
			return $args;
		}

		$target_post_types = isset( $args['post_type'] )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $args['post_type'] ) ) ) )
			: array( 'post' );
		$target_post_types = ! empty( $target_post_types ) ? $target_post_types : array( 'post' );
		$excluded          = isset( $args['post__not_in'] )
			? array_values( array_filter( array_map( 'absint', (array) $args['post__not_in'] ) ) )
			: array();
		$excluded          = array_values( array_unique( array_merge( $excluded, array( $reference_id ) ) ) );
		$limit             = isset( $args['posts_per_page'] ) ? max( 1, absint( $args['posts_per_page'] ) ) : 10;

		if (
			$reference->post_type === 'product'
			&& $target_post_types === array( 'product' )
			&& function_exists( 'wc_get_related_products' )
			&& apply_filters(
				'wp_posts_carousel_related_content_use_woocommerce',
				true,
				$reference,
				$config,
				$args
			)
		) {
			$woocommerce_ids = array_values(
				array_filter(
					array_map(
						'absint',
						(array) wc_get_related_products( $reference_id, $limit, $excluded )
					)
				)
			);

			if ( ! empty( $woocommerce_ids ) ) {
				$args['post__in']       = $woocommerce_ids;
				$args['post__not_in']   = $excluded;
				$args['orderby']        = 'post__in';
				$args['posts_per_page'] = $limit;

				return apply_filters(
					'wp_posts_carousel_related_content_query_args',
					$args,
					$reference,
					$config
				);
			}
		}

		$clauses = $this->shared_taxonomy_clauses( $reference, $target_post_types );

		if ( empty( $clauses ) ) {
			$args['post__in'] = array( 0 );

			return $args;
		}

		$related_query          = array_merge( array( 'relation' => 'OR' ), $clauses );
		$use_yoast_primary_term = function_exists( 'yoast_get_primary_term_id' )
			&& apply_filters(
				'wp_posts_carousel_related_content_use_yoast_primary_term',
				true,
				$reference,
				$config,
				$args
			);
		$primary_clauses        = $use_yoast_primary_term
			? $this->yoast_primary_term_clauses( $reference, $target_post_types )
			: array();

		if ( ! empty( $primary_clauses ) ) {
			$primary_query = array_merge( array( 'relation' => 'OR' ), $primary_clauses );
			$primary_ids   = $this->query_candidate_ids(
				$args,
				$primary_query,
				$excluded,
				$limit
			);
			$remaining     = max( 0, $limit - count( $primary_ids ) );
			$fallback_ids  = $remaining > 0
				? $this->query_candidate_ids(
					$args,
					$related_query,
					array_values( array_unique( array_merge( $excluded, $primary_ids ) ) ),
					$remaining
				)
				: array();
			$ordered_ids   = array_values( array_unique( array_merge( $primary_ids, $fallback_ids ) ) );

			if ( ! empty( $ordered_ids ) ) {
				$args['post__in']       = $ordered_ids;
				$args['post__not_in']   = $excluded;
				$args['orderby']        = 'post__in';
				$args['posts_per_page'] = $limit;

				return apply_filters(
					'wp_posts_carousel_related_content_query_args',
					$args,
					$reference,
					$config
				);
			}
		}

		if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
			$args['tax_query'] = array(
				'relation' => 'AND',
				$args['tax_query'],
				$related_query,
			);
		} else {
			$args['tax_query'] = $related_query;
		}

		$args['post__not_in'] = $excluded;
		$args['orderby']      = 'date';
		$args['order']        = 'DESC';

		return apply_filters(
			'wp_posts_carousel_related_content_query_args',
			$args,
			$reference,
			$config
		);
	}

	private function shared_taxonomy_clauses( $reference, $target_post_types ) {
		$clauses              = array();
		$reference_taxonomies = get_object_taxonomies( $reference->post_type, 'objects' );

		foreach ( $reference_taxonomies as $taxonomy ) {
			if ( empty( $taxonomy->public ) || empty( $taxonomy->name ) ) {
				continue;
			}

			$supported = false;
			foreach ( $target_post_types as $post_type ) {
				if ( is_object_in_taxonomy( sanitize_key( $post_type ), $taxonomy->name ) ) {
					$supported = true;
					break;
				}
			}

			if ( ! $supported ) {
				continue;
			}

			$term_ids = wp_get_object_terms( $reference->ID, $taxonomy->name, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
				continue;
			}

			$clauses[] = array(
				'taxonomy' => $taxonomy->name,
				'field'    => 'term_id',
				'terms'    => array_values( array_filter( array_map( 'absint', $term_ids ) ) ),
				'operator' => 'IN',
			);
		}

		return $clauses;
	}

	private function yoast_primary_term_clauses( $reference, $target_post_types ) {
		if ( ! function_exists( 'yoast_get_primary_term_id' ) ) {
			return array();
		}

		$clauses    = array();
		$taxonomies = get_object_taxonomies( $reference->post_type, 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			if (
				empty( $taxonomy->public )
				|| empty( $taxonomy->name )
				|| ! $this->taxonomy_supports_post_types( $taxonomy->name, $target_post_types )
			) {
				continue;
			}

			$term_id = absint( yoast_get_primary_term_id( $taxonomy->name, $reference->ID ) );
			if ( ! $term_id || ! has_term( $term_id, $taxonomy->name, $reference ) ) {
				continue;
			}

			$clauses[] = array(
				'taxonomy' => $taxonomy->name,
				'field'    => 'term_id',
				'terms'    => array( $term_id ),
				'operator' => 'IN',
			);
		}

		return $clauses;
	}

	private function taxonomy_supports_post_types( $taxonomy, $post_types ) {
		foreach ( $post_types as $post_type ) {
			if ( is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				return true;
			}
		}

		return false;
	}

	private function query_candidate_ids( $args, $related_query, $excluded, $limit ) {
		$candidate_args = $args;
		unset( $candidate_args['post__in'] );
		$candidate_args['fields']         = 'ids';
		$candidate_args['no_found_rows']  = true;
		$candidate_args['orderby']        = 'date';
		$candidate_args['order']          = 'DESC';
		$candidate_args['posts_per_page'] = max( 1, absint( $limit ) );
		$candidate_args['post__not_in']   = array_values( array_unique( array_filter( array_map( 'absint', $excluded ) ) ) );
		// Keep language, visibility and other site-level query filters active.
		$candidate_args['suppress_filters'] = false;

		if ( ! empty( $candidate_args['tax_query'] ) && is_array( $candidate_args['tax_query'] ) ) {
			$candidate_args['tax_query'] = array(
				'relation' => 'AND',
				$candidate_args['tax_query'],
				$related_query,
			);
		} else {
			$candidate_args['tax_query'] = $related_query;
		}

		return array_values( array_filter( array_map( 'absint', get_posts( $candidate_args ) ) ) );
	}
}
