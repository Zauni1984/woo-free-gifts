<?php
/**
 * Custom (non-catalog) gift products.
 *
 * Custom gifts are stored as real, hidden WooCommerce products so that the cart,
 * checkout, order, stock, e-mail and shipping pipeline works natively.
 * This class keeps them invisible everywhere else and blocks direct purchase.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Gift_Products
 */
final class WFG_Gift_Products {

	const META_KEY   = '_wfg_custom_gift';
	const OPTION_IDS = 'wfg_custom_gift_ids';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'hide_from_queries' ), 20 );
		add_action( 'template_redirect', array( $this, 'block_single_view' ), 1 );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'not_visible' ), 99, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'block_direct_add' ), 1, 3 );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( $this, 'exclude_from_yoast_sitemap' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_from_core_sitemap' ), 10, 2 );
		add_filter( 'woocommerce_related_products', array( $this, 'exclude_from_related' ), 99 );
		add_filter( 'woocommerce_product_get_catalog_visibility', array( $this, 'force_hidden_visibility' ), 99, 2 );
	}

	/**
	 * Is the given product a custom gift?
	 *
	 * @param WC_Product|int $product Product or id.
	 * @return bool
	 */
	public static function is_custom_gift( $product ) {
		$id = $product instanceof WC_Product ? $product->get_id() : absint( $product );
		if ( ! $id ) {
			return false;
		}
		return in_array( $id, self::ids(), true );
	}

	/**
	 * Cached list of custom gift product ids.
	 *
	 * @return int[]
	 */
	public static function ids() {
		$ids = get_option( self::OPTION_IDS, array() );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	/**
	 * Create or update a hidden gift product.
	 *
	 * @param array $data        name, description, image_id, weight, virtual.
	 * @param int   $existing_id Product id to update (0 = create).
	 * @return int|WP_Error Product id.
	 */
	public function save_custom( array $data, $existing_id = 0 ) {
		$existing_id = absint( $existing_id );
		$product     = null;

		if ( $existing_id && self::is_custom_gift( $existing_id ) ) {
			$product = wc_get_product( $existing_id );
		}
		if ( ! $product instanceof WC_Product_Simple ) {
			$product = new WC_Product_Simple();
		}

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'wfg_missing_name', __( 'A custom gift needs a name.', 'woo-free-gifts' ) );
		}

		$product->set_name( $name );
		$product->set_description( isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '' );
		$product->set_short_description( '' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_featured( false );
		$product->set_regular_price( '0' );
		$product->set_sale_price( '' );
		$product->set_price( '0' );
		$product->set_tax_status( 'none' );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );
		$product->set_sold_individually( false );
		$product->set_reviews_allowed( false );
		$product->set_virtual( ! empty( $data['virtual'] ) );
		$product->set_downloadable( false );

		$weight = isset( $data['weight'] ) ? wc_format_decimal( $data['weight'] ) : '';
		$product->set_weight( is_numeric( $weight ) && $weight > 0 ? $weight : '' );

		$image_id = isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0;
		if ( $image_id && 'attachment' === get_post_type( $image_id ) ) {
			$product->set_image_id( $image_id );
		} else {
			$product->set_image_id( 0 );
		}

		$product->update_meta_data( self::META_KEY, 'yes' );

		try {
			$id = $product->save();
		} catch ( Exception $e ) {
			return new WP_Error( 'wfg_save_failed', $e->getMessage() );
		}

		if ( ! $id ) {
			return new WP_Error( 'wfg_save_failed', __( 'The gift product could not be saved.', 'woo-free-gifts' ) );
		}

		$this->remember_id( $id );
		return (int) $id;
	}

	/**
	 * Trash a custom gift product (keeps order history intact, product stays recoverable).
	 *
	 * @param int $product_id Product id.
	 */
	public function remove_custom( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || ! self::is_custom_gift( $product_id ) ) {
			return;
		}
		$product = wc_get_product( $product_id );
		if ( $product ) {
			$product->delete( false );
		}
		$this->forget_id( $product_id );
	}

	/**
	 * Permanently delete all custom gift products (uninstall).
	 */
	public static function purge_all() {
		foreach ( self::ids() as $id ) {
			wp_delete_post( $id, true );
		}
		delete_option( self::OPTION_IDS );
	}

	/**
	 * Register an id in the cache option.
	 *
	 * @param int $id Product id.
	 */
	private function remember_id( $id ) {
		$ids = self::ids();
		if ( ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
			update_option( self::OPTION_IDS, $ids, 'yes' );
		}
	}

	/**
	 * Remove an id from the cache option.
	 *
	 * @param int $id Product id.
	 */
	private function forget_id( $id ) {
		$ids = array_values( array_diff( self::ids(), array( $id ) ) );
		update_option( self::OPTION_IDS, $ids, 'yes' );
	}

	// --- Protections ---

	/**
	 * Exclude custom gifts from every product listing query (shop, search, REST, admin list, feeds).
	 *
	 * Queries that explicitly set `wfg_include_gifts` are left alone.
	 *
	 * @param WP_Query $query Query.
	 */
	public function hide_from_queries( $query ) {
		if ( ! $query instanceof WP_Query || $query->get( 'wfg_include_gifts' ) ) {
			return;
		}

		$ids = self::ids();
		if ( empty( $ids ) ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		$types     = is_array( $post_type ) ? $post_type : array( $post_type );
		$is_shop   = ! is_admin() && $query->is_main_query() && ( $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) );

		if ( ! in_array( 'product', $types, true ) && ! in_array( 'any', $types, true ) && ! $is_shop && ! $query->is_search() ) {
			return;
		}

		// Admins can reveal them on the product list with ?wfg_show_gifts=1.
		if ( is_admin() && ! wp_doing_ajax() && isset( $_GET['wfg_show_gifts'] ) && current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view toggle.
			return;
		}

		$not_in = (array) $query->get( 'post__not_in' );
		$query->set( 'post__not_in', array_values( array_unique( array_merge( array_map( 'absint', $not_in ), $ids ) ) ) );
	}

	/**
	 * A custom gift product has no public page.
	 */
	public function block_single_view() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$id = get_queried_object_id();
		if ( $id && self::is_custom_gift( $id ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Never visible in loops.
	 *
	 * @param bool $visible    Visible.
	 * @param int  $product_id Product id.
	 * @return bool
	 */
	public function not_visible( $visible, $product_id ) {
		return self::is_custom_gift( $product_id ) ? false : $visible;
	}

	/**
	 * Custom gifts are always catalog-hidden, even if edited by hand.
	 *
	 * @param string     $visibility Visibility.
	 * @param WC_Product $product    Product.
	 * @return string
	 */
	public function force_hidden_visibility( $visibility, $product ) {
		return self::is_custom_gift( $product ) ? 'hidden' : $visibility;
	}

	/**
	 * Block adding a custom gift unless the gift engine is doing it.
	 *
	 * @param bool $passed     Validation state.
	 * @param int  $product_id Product id.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public function block_direct_add( $passed, $product_id, $quantity ) {
		unset( $quantity );
		if ( WFG_Cart::is_adding_gift() ) {
			return $passed;
		}
		if ( self::is_custom_gift( $product_id ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'This product is a free gift and cannot be purchased separately.', 'woo-free-gifts' ), 'error' );
			}
			return false;
		}
		return $passed;
	}

	/**
	 * Yoast sitemap exclusion.
	 *
	 * @param array $ids Excluded ids.
	 * @return array
	 */
	public function exclude_from_yoast_sitemap( $ids ) {
		return array_merge( is_array( $ids ) ? $ids : array(), self::ids() );
	}

	/**
	 * Core sitemap exclusion.
	 *
	 * @param array  $args      Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function exclude_from_core_sitemap( $args, $post_type ) {
		if ( 'product' === $post_type ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), self::ids() );
		}
		return $args;
	}

	/**
	 * Keep custom gifts out of "related products".
	 *
	 * @param int[] $related Related product ids.
	 * @return int[]
	 */
	public function exclude_from_related( $related ) {
		return is_array( $related ) ? array_values( array_diff( array_map( 'absint', $related ), self::ids() ) ) : $related;
	}
}
