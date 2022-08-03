<?php

defined( 'ABSPATH' ) || exit;

class ShaplaWCOrderExporter {
	public function export_filters() {
		?>
        <p><label><input type="radio" name="content" value="shop_order">WooCommerce Order (JSON Export)</label></p>
        <ul id="shop_order-filters" class="export-filters">
            <li>
                <label for="post-start-date" class="label-responsive"><?php _e( 'Start date:' ); ?></label>
                <input type="date" name="start_date" id="post-start-date">
            </li>
            <li>
                <label for="post-end-date" class="label-responsive"><?php _e( 'End date:' ); ?></label>
                <input type="date" name="end_date" id="post-end-date">
            </li>
            <li>
                <label for="order-ids" class="label-responsive"><?php _e( 'Order Ids:' ); ?></label>
                <textarea id="order-ids" name="order_ids"></textarea>
            </li>
        </ul>
		<?php

		add_action( 'admin_footer', [ $this, 'footer_script' ] );
	}


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



	public function export_wp( $args ) {
		if ( isset( $args['content'] ) && 'shop_order' == $args['content'] ) {
			$start_date = $_GET['start_date'] ?? '';
			$end_date   = $_GET['end_date'] ?? '';
			$order_ids  = $_GET['order_ids'] ?? '';
			if ( ! empty( $order_ids ) ) {
				$order_ids = explode( ',', $order_ids );
				$order_ids = array_map( 'intval', $order_ids );
			}

			global $wpdb;
			$data = [];
			if ( count( $order_ids ) ) {
				$sql     = "SELECT * FROM $wpdb->posts WHERE post_type = 'shop_order' AND ID IN(" . implode( ',', $order_ids ) . ")";
				$results = $wpdb->get_results( $sql, ARRAY_A );
				foreach ( $results as $result ) {
					$data[] = $this->get_order_data_to_export( $result );
				}
			}

			$sitename = sanitize_key( get_bloginfo( 'name' ) );
			$date     = gmdate( 'Y-m-d' );
			$filename = $sitename . 'WooCommerceOrders.' . $date . '.json';

			$json = wp_json_encode( $data );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );
			echo $json;
			die;
		}
	}

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
			$sql   = "SELECT * FROM `{$wpdb->prefix}woocommerce_order_itemmeta` WHERE `order_item_id` IN(" . implode( ',', $item_ids ) . ")";
			$items = $wpdb->get_results( $sql, ARRAY_A );

			foreach ( $items as $item ) {
				$data['items_metadata'][ $item['order_item_id'] ][ $item['meta_key'] ] = $item['meta_value'];
			}
		}

		return $data;
	}
}
