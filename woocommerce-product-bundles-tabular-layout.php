<?php
/*
* Plugin Name: WooCommerce Product Bundles - Tabular Layout
* Plugin URI: http://www.woothemes.com/products/composite-products/
* Description: Adds a "Layout" option to your Product Bundles, which can be used to activate a table-based layout, similar to the one found in Grouped Products.
* Version: 1.0.1
* Author: SomewhereWarm
* Author URI: http://somewherewarm.net/
* Developer: Manos Psychogyiopoulos
* Developer URI: http://somewherewarm.net/
*
* Text Domain: woocommerce-product-bundles-tabular-layout
* Domain Path: /languages/
*
* Requires at least: 3.8
* Tested up to: 4.3
*
* Copyright: © 2009-2015 Manos Psychogyiopoulos.
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_PB_Tabular_Layout {

	public static $version        = '1.0.1';
	public static $req_pb_version = '4.12.2';

	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path(__FILE__) ), basename( __FILE__ ) );
	}

	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	public static function init() {

		// Lights on
		add_action( 'plugins_loaded', __CLASS__ . '::load_plugin' );
	}

	/**
	 * Lights on.
	 */

	public static function load_plugin() {

		global $woocommerce_bundles;

		if ( ! empty( $woocommerce_bundles ) && version_compare( $woocommerce_bundles->version, self::$req_pb_version ) < 0 ) {
			add_action( 'admin_notices', __CLASS__ . '::pb_admin_notice' );
			return false;
		}

		// Display layout option in "Bundled Products" tab
		add_action( 'woocommerce_bundled_products_admin_config', __CLASS__ . '::tabular_admin_option' );

		// Save layout option
		add_action( 'woocommerce_process_product_meta_bundle', __CLASS__ . '::process_tabular_meta' );

		// Add tabular styles
		add_action( 'wp_enqueue_scripts', __CLASS__ . '::styles' );

		// Insertion point
		add_action( 'woocommerce_bundle_add_to_cart', __CLASS__ . '::init_tabular' );
		add_action( 'woocommerce_composite_show_composited_product_bundle', __CLASS__ . '::init_tabular' );
	}

	/**
	 * PB version check notice.
	 */

	public static function pb_admin_notice() {
	    echo '<div class="error"><p>' . sprintf( __( '&quot;WooCommerce Product Bundles &ndash; Tabular Layout&quot; requires at least Product Bundles version %s in order to function. Please upgrade WooCommerce Product Bundles.', 'woocommerce-product-bundles' ), self::$req_pb_version ) . '</p></div>';
	}

	/**
	 * Admin tabular setting display / save.
	 */

	public static function tabular_admin_option() {

		?><div class="options_group"><?php
			woocommerce_wp_select( array( 'id' => '_wc_pb_layout_style', 'label' => __( 'Layout', 'woocommerce-product-bundles-tabular-layout' ), 'description' => __( 'Selecting the <strong>Tabular</strong> option will result in a layout similar to that of a Grouped Product. Useful if your Bundle includes products with configurable quantities.', 'woocommerce-product-bundles-tabular-layout' ), 'desc_tip' => true, 'options' => array(
					'default' => __( 'Default', 'woocommerce-product-bundles-tabular-layout' ),
					'tabular' => __( 'Tabular', 'woocommerce-product-bundles-tabular-layout' ),
				) ) );
		?></div><?php
	}

	public static function process_tabular_meta( $post_id ) {

		if ( ! empty( $_POST[ '_wc_pb_layout_style' ] ) && $_POST[ '_wc_pb_layout_style' ] === 'tabular' ) {
			update_post_meta( $post_id, '_wc_pb_layout_style', 'tabular' );
		} else {
			delete_post_meta( $post_id, '_wc_pb_layout_style' );
		}
	}

	/**
	 * Apply tabular template modifications.
	 */

	public static function init_tabular( $the_product = false ) {

		global $product;

		if ( ! $the_product ) {
			$the_product = $product;
		}

		if ( is_object( $the_product ) && $the_product->product_type === 'bundle' ) {

			$layout = get_post_meta( $the_product->id, '_wc_pb_layout_style', true );

			if ( $layout === 'tabular' ) {
				self::template_actions();
			}
		}
	}

	/**
	 * Modify templates.
	 */

	public static function template_actions() {

		// Remove 'bundled_product' div
		remove_action( 'wc_bundles_bundled_item_details', 'wc_bundles_bundled_item_details_wrapper_open', 0, 2 );
		remove_action( 'wc_bundles_bundled_item_details', 'wc_bundles_bundled_item_details_wrapper_close', 100, 2 );

		// Prevent qty template from loading inside the product type templates
		add_action( 'wc_bundles_bundled_item_details', __CLASS__ . '::unload_qty_template_add', 24, 2 );
		add_action( 'wc_bundles_bundled_item_details', __CLASS__ . '::unload_qty_template_remove', 26, 2 );

		// Arrange elements in table
		add_action( 'wc_bundles_bundled_item_details', __CLASS__ . '::bundled_item_details_wrapper_open', 0, 2 );
		add_action( 'wc_bundles_bundled_item_details', __CLASS__ . '::bundled_item_details_wrapper_close', 100, 2 );
		add_action( 'wc_bundles_bundled_item_details', __CLASS__ . '::before_bundled_item_thumbnail', 4, 2 );
		add_action( 'wc_bundles_bundled_item_details', __CLASS__ . '::after_bundled_item_thumbnail', 6, 2 );
		add_action( 'wc_bundles_bundled_item_details', __CLASS__ . '::before_bundled_item_details', 7, 2 );
		add_action( 'wc_bundles_bundled_item_details', __CLASS__ . '::after_bundled_item_details', 31, 2 );

		// Reload qty template in table
		add_action( 'wc_bundles_bundled_item_details', __CLASS__ . '::load_qty_template', 32, 2 );

		// Open and close table
		add_action( 'woocommerce_before_bundled_items', __CLASS__ . '::bundled_items_table_start', 100 );
		add_action( 'woocommerce_after_bundled_items', __CLASS__ . '::bundled_items_table_end', 0 );

		add_action( 'woocommerce_before_composited_bundled_items', __CLASS__ . '::bundled_items_table_start', 100 );
		add_action( 'woocommerce_after_composited_bundled_items', __CLASS__ . '::bundled_items_table_end', 0 );
	}

	/**
	 * Tabular styles.
	 */

	public static function styles() {

		wp_register_style( 'wcpb-tabular-bundle-css', self::plugin_url() . '/assets/css/wcpb-tabular-layout.css', array( 'wc-bundle-css' ), self::$version );
		wp_enqueue_style( 'wcpb-tabular-bundle-css' );
	}

	/**
	 * Arrange elements in table.
	 */

	public static function bundled_item_details_wrapper_open( $bundled_item, $bundle ) {
		?><tr class="bundled_product bundled_product_summary product <?php echo $bundled_item->get_classes(); ?>" style="<?php echo ( ! $bundled_item->is_visible() ? 'display:none;' : '' ); ?>" ><?php
	}

	public static function bundled_item_details_wrapper_close( $bundled_item, $bundle ) {
		?></tr><?php
	}

	public static function bundled_items_table_start() {
		?><table cellspacing="0" class="bundled_products">
			<thead>
				<th class="bundled_item_col bundled_item_images_head"></th>
				<th class="bundled_item_col bundled_item_details_head">Product</th>
				<th class="bundled_item_col bundled_item_qty_head">Qty</th>
			</thead>
			<tbody><?php
	}

	public static function bundled_items_table_end() {
		echo '</tbody></table>';
	}

	public static function before_bundled_item_thumbnail( $bundled_item, $bundle ) {
		echo '<td class="bundled_item_col bundled_item_images_col">';
	}

	public static function after_bundled_item_thumbnail( $bundled_item, $bundle ) {
		echo '</td>';
	}

	public static function before_bundled_item_details( $bundled_item, $bundle ) {
		echo '<td class="bundled_item_col bundled_item_details_col">';
	}

	public static function after_bundled_item_details( $bundled_item, $bundle ) {
		echo '</td>';
	}

	public static function load_qty_template( $bundled_item, $bundle ) {

		echo '<td class="bundled_item_col bundled_item_qty_col">';

		wc_get_template( 'bundled-item-quantity.php', array(
				'bundled_item'         => $bundled_item,
				'bundle_fields_prefix' => apply_filters( 'woocommerce_product_bundle_field_prefix', '', $bundle->id )
			), false, self::plugin_path() . '/templates/'
		);

		echo '</td>';
	}

	/**
	 * Prevent qty template from loading inside the product type templates
	 */

	public static function unload_qty_template_add( $bundled_item, $bundle ) {
		add_filter( 'wc_get_template', __CLASS__ . '::load_dummy_template', 10, 5 );
	}

	public static function unload_qty_template_remove( $bundled_item, $bundle ) {
		remove_filter( 'wc_get_template', __CLASS__ . '::load_dummy_template', 10, 5 );
	}

	public static function load_dummy_template( $located, $template_name, $args, $template_path, $default_path ) {

		if ( $template_name === 'single-product/bundled-item-quantity.php' ) {
			$located = wc_locate_template( 'dummy-quantity.php', false, self::plugin_path() . '/templates/' );
		}

		return $located;
	}
}

WC_PB_Tabular_Layout::init();
