<?php

defined( 'ABSPATH' ) || exit;

class ShaplaWCOrderImporter extends \WP_Importer {
	private $id = 0;
	private $file_url = '';


	/**
	 * Registered callback function for the WordPress Importer.
	 *
	 * Manages the three separate stages of the CSV import process.
	 */
	public function dispatch() {

		$this->header();

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];

		switch ( $step ) {

			case 0:
				$this->greet();
				break;

			case 1:
				check_admin_referer( 'import-upload' );

				if ( $this->handle_upload() ) {

					if ( $this->id ) {
						$file = get_attached_file( $this->id );
					} else {
						$file = ABSPATH . $this->file_url;
					}

					add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

					$this->import( $file );
				}
				break;
		}

		$this->footer();
	}

	private function handle_upload() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in WC_Tax_Rate_Importer::dispatch()
		$file_url = isset( $_POST['file_url'] ) ? sanitize_text_field( wp_unslash( $_POST['file_url'] ) ) : '';

		if ( empty( $file_url ) ) {
			$file = wp_import_handle_upload();

			if ( isset( $file['error'] ) ) {
				$this->import_error( $file['error'] );
			}

			if ( ! self::is_file_valid_json( $file['file'] ) ) {
				// Remove file if not valid.
				wp_delete_attachment( $file['id'], true );

				$this->import_error( __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'woocommerce' ) );
			}

			$this->id = absint( $file['id'] );
		} elseif ( file_exists( ABSPATH . $file_url ) ) {
			if ( ! self::is_file_valid_json( ABSPATH . $file_url ) ) {
				$this->import_error( __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'woocommerce' ) );
			}

			$this->file_url = esc_attr( $file_url );
		} else {
			$this->import_error();
		}

		return true;
	}

	private function import( string $file ) {
		if ( ! is_file( $file ) ) {
			$this->import_error( __( 'The file does not exist, please try again.', 'woocommerce' ) );
		}

		$content = wp_json_file_decode( $file, array( 'associative' => true ) );
		if ( ! is_array( $content ) ) {
			$this->import_error( 'Invalid json file.' );

			return;
		}

		$new_orders = [];
		foreach ( $content as $order_data ) {
			$new_orders[] = $this->import_single_order( $order_data );
		}

		// Show Result.
		echo '<div class="updated settings-error"><p>';
		printf(
		/* translators: %s: tax rates count */
			esc_html__( 'Import complete - imported %s orders.', 'woocommerce' ),
			'<strong>' . count( $new_orders ) . '</strong>'
		);
		echo '</p></div>';
	}

	private function import_single_order( $order_data ) {
		$id = wp_insert_post( $order_data['order'] );
		if ( is_wp_error( $id ) ) {
			$this->import_error();

			return 0;
		}

		foreach ( $order_data['metadata'] as $key => $value ) {
			update_post_meta( $id, $key, maybe_unserialize( $value ) );
		}

		global $wpdb;

		foreach ( $order_data['notes'] as $note ) {
			$wpdb->insert( $wpdb->comments, $note );
		}

		foreach ( $order_data['items'] as $order_item ) {
			$wpdb->insert( $wpdb->prefix . 'woocommerce_order_items', [
				'order_item_name' => $order_item['order_item_name'],
				'order_item_type' => $order_item['order_item_type'],
				'order_id'        => $id,
			] );
			$order_item_id = $wpdb->insert_id;
			$metadata      = $order_data['items_metadata'][ $order_item['order_item_id'] ];
			if ( is_array( $metadata ) ) {
				foreach ( $metadata as $key => $value ) {
					$wpdb->insert( $wpdb->prefix . 'woocommerce_order_itemmeta', [
						'order_item_id' => $order_item_id,
						'meta_key'      => $key,
						'meta_value'    => $value,
					] );
				}
			}
		}

		return $id;
	}

	/**
	 * Output information about the uploading process.
	 */
	public function greet() {

		echo '<div class="narrow">';
		echo '<p>' . esc_html__( 'Hi there! Upload a JSON file containing orders to import the contents into your shop. Choose a .json file to upload, then click "Upload file and import".', 'woocommerce' ) . '</p>';

		$action = 'admin.php?import=woocommerce_order_json&step=1';

		$bytes      = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size       = size_format( $bytes );
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) :
			?>
            <div class="error">
                <p><?php esc_html_e( 'Before you can upload your import file, you will need to fix the following error:', 'woocommerce' ); ?></p>
                <p><strong><?php echo esc_html( $upload_dir['error'] ); ?></strong></p>
            </div>
		<?php else : ?>
            <form enctype="multipart/form-data" id="import-upload-form" method="post"
                  action="<?php echo esc_attr( wp_nonce_url( $action, 'import-upload' ) ); ?>">
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th>
                            <label for="upload"><?php esc_html_e( 'Choose a file from your computer:', 'woocommerce' ); ?></label>
                        </th>
                        <td>
                            <input type="file" id="upload" name="import" size="25"/>
                            <input type="hidden" name="action" value="save"/>
                            <input type="hidden" name="max_file_size" value="<?php echo absint( $bytes ); ?>"/>
                            <small>
								<?php
								printf(
								/* translators: %s: maximum upload size */
									esc_html__( 'Maximum size: %s', 'woocommerce' ),
									esc_attr( $size )
								);
								?>
                            </small>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="file_url"><?php esc_html_e( 'OR enter path to file:', 'woocommerce' ); ?></label>
                        </th>
                        <td>
							<?php echo ' ' . esc_html( ABSPATH ) . ' '; ?><input type="text" id="file_url"
                                                                                 name="file_url" size="25"/>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <button type="submit" class="button"
                            value="<?php esc_attr_e( 'Upload file and import', 'woocommerce' ); ?>"><?php esc_html_e( 'Upload file and import', 'woocommerce' ); ?></button>
                </p>
            </form>
		<?php
		endif;

		echo '</div>';
	}

	/**
	 * Show import error and quit.
	 *
	 * @param string $message Error message.
	 */
	private function import_error( string $message = '' ) {
		echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'woocommerce' ) . '</strong><br />';
		if ( $message ) {
			echo esc_html( $message );
		}
		echo '</p>';
		$this->footer();
		die();
	}

	private function header() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Import order', 'woocommerce' ) . '</h1>';
	}

	private function footer() {
		echo '</div>';
	}

	/**
	 * @param $file_path_or_url
	 *
	 * @return bool
	 */
	private static function is_file_valid_json( $file_path_or_url ): bool {
		return true;
		$valid_filetypes = [
			'json' => 'application/json'
		];
		$filetype        = wp_check_filetype( $file_path_or_url, $valid_filetypes );

		return in_array( $filetype['type'], $valid_filetypes, true );
	}
}