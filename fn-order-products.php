<?php
/**
 * Plugin Name: Order Products By Stock
 * Author: Douglas Fanucchi
 * Description: This plugins makes products that does not have stock available to be the last shown.
 * Author URI: https://github.com/douglasfanucchi
 * Text Domain: fn-order-products
 * Domain Path: /languages
 * Version 1.0
 */

namespace Fanucchi;

class Order_By_Stock {
	public function __construct() {
		$this->add_actions();
		$this->add_filters();
	}

	public function add_actions() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'pre_get_posts', array( $this, 'fn_change_products_order' ), 50 );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'fn_render_outofstock_label' ), 1 );
		add_action( 'init', array( $this, 'load_text_domain' ) );
	}

	protected function add_filters() {
		add_filter( 'posts_clauses', array( $this, 'fn_change_posts_clauses'), 50, 2 );
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'fn-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
	}

	public function fn_set_stock_meta_query( \WP_Query $query ) {
		$new_meta_query = [
			'relation' => 'AND',
			[
				'stock_clause' => [
					'key' => '_stock_status',
					'compare' => 'IN',
					'value' => array('instock', 'outofstock', 'onbackorder')
				]
			],
		];
		$prev_meta_query = $query->get( 'meta_query' );
		if ( ! empty( $prev_meta_query ) ) {
			$new_meta_query[1] = $prev_meta_query;
		}
		$query->set( 'meta_query', $new_meta_query );
	}

	public function fn_set_order_by_stock( \WP_Query $query) {
		$prev_order_by = $query->get( 'orderby' );
		$order_value = ! empty( $query->get('order') ) ? $query->get('order') : 'DESC';
		$new_order_by = ['stock_clause' => 'ASC'];

		if ( $query->is_search() ) {
			$new_order_by['date'] = 'DESC';
			$new_order_by['ID'] = 'DESC';
		}

		if ( is_string( $prev_order_by ) ) {
			$prev_order_by = $this->orderby_string_to_array( $prev_order_by, $order_value );
		}
		array_merge( $new_order_by, $prev_order_by );
		$query->set( 'orderby', $new_order_by );
	}

	public function fn_change_products_order( \WP_Query $query ) {
		if ( ! $this->should_change_products_order( $query ) )
			return;
		$this->fn_set_stock_meta_query( $query );
		$this->fn_set_order_by_stock( $query );
	}

	public function fn_change_posts_clauses( array $clauses, \WP_Query $query ) {
		global $wpdb;

		if ( ! $this->should_change_products_order( $query ) )
			return $clauses;

		$order_by_stock = 'CAST(' . $wpdb->prefix . 'postmeta.meta_value AS CHAR) ASC';
		if ( ! empty( $clauses['orderby'] ) )
			$order_by_stock .= ', ';
		$clauses['orderby'] = $order_by_stock . $clauses['orderby'];
		return $clauses;
	}

	public function fn_render_outofstock_label() {
		global $product;

		if ( $product->is_in_stock() )
			return;
		require_once plugin_dir_path( __FILE__ ) . 'templates/out-of-stock.php';
	}

	protected function orderby_string_to_array( string $string_order_by, string $order_value ) {
		$orderby = [];
		$fields_to_order = explode(' ', $string_order_by);

		foreach($fields_to_order as $item) {
			$orderby[ $item ] = $order_value;
		}

		return $orderby;
	}

	protected function should_change_products_order( \WP_Query $query ) {
		$is_in_dashboard = is_admin() && ! wp_doing_ajax();

		if ( $is_in_dashboard )
			return false;

		$conditions = apply_filters( 'fn_conditions_to_change_product_order', [
			$query->get( 'post_type' ) == 'product',
			$query->is_post_type_archive( 'product' ),
			$query->is_tax( 'product_cat' )
		]);

		foreach($conditions as $condition)
			if ($condition == true)
				return true;
		return false;
	}

	public function load_text_domain() {
		load_plugin_textdomain(
			'fn-order-products',
			false,
			plugin_basename( dirname( __FILE__ ) ) . '/languages'
		);
	}
}

new Order_By_Stock;
