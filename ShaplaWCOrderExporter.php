<?php

defined( 'ABSPATH' ) || exit;

/**
 * ShaplaWCOrderExporter class
 */
class ShaplaWCOrderExporter {

	/**
	 * Add export filter
	 *
	 * @return void
	 */
	public function export_filters() {
		wp_nonce_field( 'shapla-wc-order-import-export', '_ei_nonce' );
		?>
		<p>
			<label>
				<input type="radio" name="content" value="shop_order">
				<?php esc_html_e( 'WooCommerce Order (JSON Export)', 'shapla-wc-order-import-export' ); ?>
			</label>
		</p>
		<ul id="shop_order-filters" class="export-filters">
			<li>
				<label for="post-start-date" class="label-responsive">
					<?php esc_html_e( 'Start date:', 'shapla-wc-order-import-export' ); ?></label>
				<input type="date" name="start_date" id="post-start-date">
			</li>
			<li>
				<label for="post-end-date" class="label-responsive">
					<?php esc_html_e( 'End date:', 'shapla-wc-order-import-export' ); ?></label>
				<input type="date" name="end_date" id="post-end-date">
			</li>
			<li>
				<label for="order-ids" class="label-responsive">
					<?php esc_html_e( 'Order Ids:', 'shapla-wc-order-import-export' ); ?></label>
				<textarea id="order-ids" name="order_ids"></textarea>
			</li>
		</ul>
		<?php

		add_action( 'admin_footer', [ $this, 'footer_script' ] );
	}


	/**
	 * Add jQuery script to toggle export filter
	 *
	 * @return void
	 */
	public function footer_script() {
		?>
		<script type="text/javascript">
			jQuery(function ($) {
				$('#export-filters').find('input:radio').on('change', function () {
					switch ($(this).val()) {
						case 'shop_order':
							$('#shop_order-filters').slideDown();
							break;
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Export JSON file
	 *
	 * @param array $args The arguments.
	 *
	 * @return void
	 */
	public function export_wp( array $args ) {
		check_admin_referer( 'shapla-wc-order-import-export', '_ei_nonce' );
		if ( isset( $args['content'] ) && 'shop_order' === $args['content'] ) {
			$start_date = $_GET['start_date'] ?? '';
			$end_date   = $_GET['end_date'] ?? '';
			$order_ids  = $_GET['order_ids'] ?? '';
			$order_ids  = array_filter( explode( ',', $order_ids ) );

			$data = [];

			if ( count( $order_ids ) ) {
				$data = $this->get_order_data_by_ids( $order_ids );
			} elseif ( ! empty( $start_date ) ) {
				$data = $this->get_order_data_by_dates( $start_date, $end_date );
			}

			$sitename = sanitize_key( get_bloginfo( 'name' ) );
			$date     = gmdate( 'Y-m-d' );
			$filename = $sitename . 'WooCommerceOrders.' . $date . '.json';

			$json = wp_json_encode( $data );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );
			echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			die;
		}
	}

	/**
	 * Get order data by order ids.
	 *
	 * @param array $order_ids Order ids.
	 *
	 * @return array
	 */
	public function get_order_data_by_ids( array $order_ids ): array {
		global $wpdb;
		$data = [];
		if ( count( $order_ids ) < 1 ) {
			return $data;
		}
		$order_ids = array_map( 'intval', $order_ids );
		$sql       = "SELECT * FROM $wpdb->posts WHERE post_type = 'shop_order' AND ID IN(" . implode( ',', $order_ids ) . ')';
		$results   = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $results as $result ) {
			$data[] = $this->get_order_data_to_export( $result );
		}

		return $data;
	}

	/**
	 * Get order data by dates
	 *
	 * @param string|null $start_date Start date in format YYYY-MM-DD.
	 * @param string|null $end_date End date in format YYYY-MM-DD.
	 *
	 * @return array
	 */
	public function get_order_data_by_dates( ?string $start_date, ?string $end_date = null ): array {
		global $wpdb;
		$data = [];

		$sql = "SELECT * FROM $wpdb->posts WHERE post_type = 'shop_order'";
		if ( $start_date && $end_date ) {
			$sql .= $wpdb->prepare( ' AND post_date_gmt > %s AND post_date_gmt < %s', $start_date, $end_date );
		} elseif ( $start_date ) {
			$sql .= $wpdb->prepare( ' AND post_date_gmt > %s', $start_date );
		} elseif ( $end_date ) {
			$sql .= $wpdb->prepare( ' AND post_date_gmt < %s', $start_date );
		}
		$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $results as $result ) {
			$data[] = $this->get_order_data_to_export( $result );
		}

		return $data;
	}

	/**
	 * Get order data to export for a single post
	 *
	 * @param array $post_data List of data from posts table.
	 *
	 * @return array
	 */
	public function get_order_data_to_export( array $post_data ): array {
		$data = [
			'order'          => $post_data,
			'metadata'       => [],
			'notes'          => [],
			'items'          => [],
			'items_metadata' => [],
		];

		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE `post_id` = %d", intval( $post_data['ID'] ) ),
			ARRAY_A
		);
		foreach ( $results as $result ) {
			$data['metadata'][ $result['meta_key'] ] = $result['meta_value'];
		}

		$data['notes'] = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE `comment_post_ID` = %d", intval( $post_data['ID'] ) ),
			ARRAY_A
		);

		$data['items'] = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_order_items WHERE `order_id` = %d", intval( $post_data['ID'] ) ),
			ARRAY_A
		);

		$item_ids = wp_list_pluck( $data['items'], 'order_item_id' );
		$item_ids = array_map( 'intval', $item_ids );

		if ( $item_ids ) {
			$sql   = "SELECT * FROM `{$wpdb->prefix}woocommerce_order_itemmeta` WHERE `order_item_id` IN(" . implode( ',', $item_ids ) . ')';
			$items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $items as $item ) {
				$data['items_metadata'][ $item['order_item_id'] ][ $item['meta_key'] ] = $item['meta_value'];
			}
		}

		return $data;
	}
}
