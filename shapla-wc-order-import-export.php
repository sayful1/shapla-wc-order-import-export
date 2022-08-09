<?php
/**
 * Plugin Name: Shapla WC Order Import/Export
 * Description: A simple WooCommerce plugin to export/import individual order.
 * Version: 1.0.0
 * Author: Sayful Islam
 * Author URI: https://sayfulislam.com
 * Requires at least: 5.5
 * Requires PHP: 7.2
 * Text Domain: shapla-wc-order-import-export
 * Domain Path: /languages
 * WC requires at least: 4.0
 * WC tested up to: 5.2
 *
 * @package ShaplaWCOrderImportExport
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin main class
 */
final class ShaplaWCOrderImportExport {
	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Only one instance of the class can be loaded
	 *
	 * @return ShaplaWCOrderImportExport
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'plugins_loaded', [ self::$instance, 'bootstrap_plugin' ] );
		}

		return self::$instance;
	}

	/**
	 * Bootstrap plugin
	 *
	 * @return void
	 */
	public function bootstrap_plugin() {
		include_once dirname( __FILE__ ) . '/ShaplaWCOrderExporter.php';

		$exporter = new ShaplaWCOrderExporter();

		add_action( 'export_filters', [ $exporter, 'export_filters' ] );
		add_action( 'export_wp', [ $exporter, 'export_wp' ] );

		add_action( 'admin_init', array( $this, 'register_importers' ) );
	}

	/**
	 * Register WordPress based importers.
	 */
	public function register_importers() {
		if ( defined( 'WP_LOAD_IMPORTERS' ) ) {
			register_importer(
				'woocommerce_order_json',
				__( 'WooCommerce orders (JSON)', 'shapla-wc-order-import-export' ),
				__( 'Import <strong>orders</strong> to your store via a json file.', 'shapla-wc-order-import-export' ),
				array( $this, 'order_importer' )
			);
		}
	}

	/**
	 * Handle register_importer
	 *
	 * @return void
	 */
	public function order_importer() {
		require_once ABSPATH . 'wp-admin/includes/import.php';

		if ( ! class_exists( 'WP_Importer' ) ) {
			$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';

			if ( file_exists( $class_wp_importer ) ) {
				require $class_wp_importer;
			}
		}

		include_once dirname( __FILE__ ) . '/ShaplaWCOrderImporter.php';
		$importer = new ShaplaWCOrderImporter();
		$importer->dispatch();
	}
}

/**
 * Begins execution of the plugin.
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
ShaplaWCOrderImportExport::get_instance();
