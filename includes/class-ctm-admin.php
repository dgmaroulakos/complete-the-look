<?php
/**
 * Admin / Product Data metabox for Complete the Look.
 *
 * @package WooCompleteTheLook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the WooCommerce Product Data tab and product search AJAX.
 */
class CTM_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var CTM_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return CTM_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_ctm_get_product_color_options', array( $this, 'ajax_get_product_color_options' ) );
	}

	/**
	 * Register the "Complete the Look" product data tab.
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['complete_the_look'] = array(
			'label'    => __( 'Complete the Look', 'woo-complete-the-look' ),
			'target'   => 'ctm_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable', 'show_if_external', 'show_if_grouped' ),
			'priority' => 65,
		);

		return $tabs;
	}

	/**
	 * Render the Complete the Look panel content.
	 *
	 * @return void
	 */
	public function render_product_data_panel() {
		global $post;

		$product       = wc_get_product( $post->ID );
		$product_ids   = get_post_meta( $post->ID, CTM_META_KEY, true );
		$product_ids   = is_array( $product_ids ) ? array_map( 'absint', $product_ids ) : array();
		$look_image_id = absint( get_post_meta( $post->ID, CTM_LOOK_IMAGE_META, true ) );
		$preview_url   = $look_image_id ? wp_get_attachment_image_url( $look_image_id, 'medium' ) : '';
		$presets       = get_post_meta( $post->ID, CTM_COLOR_PRESETS_META, true );
		$presets       = CTM_Helpers::sanitize_color_presets( $presets );

		$main_colors   = array();
		$color_attr    = '';

		if ( $product && $product->is_type( 'variable' ) ) {
			$color_attr  = CTM_Helpers::get_color_attribute_name( $product );
			$main_colors = CTM_Helpers::get_color_options( $product );
		}

		?>
		<div id="ctm_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<p class="form-field ctm-look-image-field">
					<label><?php esc_html_e( 'Default look image', 'woo-complete-the-look' ); ?></label>
					<span class="ctm-look-image-wrap">
						<input type="hidden" id="ctm_look_image_id" name="ctm_look_image_id" value="<?php echo esc_attr( (string) $look_image_id ); ?>" />
						<span id="ctm_look_image_preview" class="ctm-look-image-preview"<?php echo $preview_url ? '' : ' style="display:none;"'; ?>>
							<?php if ( $preview_url ) : ?>
								<img src="<?php echo esc_url( $preview_url ); ?>" alt="" />
							<?php endif; ?>
						</span>
						<button type="button" class="button ctm-look-image-upload" data-target="default">
							<?php
							echo $look_image_id
								? esc_html__( 'Change image', 'woo-complete-the-look' )
								: esc_html__( 'Upload image', 'woo-complete-the-look' );
							?>
						</button>
						<button type="button" class="button ctm-look-image-remove" data-target="default"<?php echo $look_image_id ? '' : ' style="display:none;"'; ?>>
							<?php esc_html_e( 'Remove', 'woo-complete-the-look' ); ?>
						</button>
					</span>
					<?php echo wc_help_tip( __( 'Fallback outfit photo. You can override it per color below.', 'woo-complete-the-look' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>

				<p class="form-field">
					<label for="ctm_product_ids"><?php esc_html_e( 'Complete the Look products', 'woo-complete-the-look' ); ?></label>
					<select
						class="wc-product-search"
						multiple="multiple"
						style="width: 50%;"
						id="ctm_product_ids"
						name="ctm_product_ids[]"
						data-placeholder="<?php esc_attr_e( 'Search for products&hellip;', 'woo-complete-the-look' ); ?>"
						data-action="woocommerce_json_search_products_and_variations"
						data-exclude="<?php echo esc_attr( (string) $post->ID ); ?>"
					>
						<?php
						foreach ( $product_ids as $product_id ) {
							$linked = wc_get_product( $product_id );

							if ( ! $linked ) {
								continue;
							}

							printf(
								'<option value="%1$d" selected="selected">%2$s</option>',
								absint( $product_id ),
								esc_html( wp_strip_all_tags( $linked->get_formatted_name() ) )
							);
						}
						?>
					</select>
					<?php echo wc_help_tip( __( 'Select complementary products. Then set a default color for each main-product color below.', 'woo-complete-the-look' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
			</div>

			<?php if ( ! empty( $main_colors ) ) : ?>
				<div class="options_group ctm-color-presets" data-color-attribute="<?php echo esc_attr( $color_attr ); ?>">
					<p class="form-field">
						<label><?php esc_html_e( 'Presets by color', 'woo-complete-the-look' ); ?></label>
						<span class="description" style="display:block;margin:0;">
							<?php esc_html_e( 'When the shopper picks a color on this product, Complete the Look will use these defaults.', 'woo-complete-the-look' ); ?>
						</span>
					</p>

					<div class="ctm-color-presets__list">
						<?php foreach ( $main_colors as $color_slug => $color_label ) : ?>
							<?php
							$preset      = isset( $presets[ $color_slug ] ) ? $presets[ $color_slug ] : array( 'image_id' => 0, 'defaults' => array() );
							$preset_img  = absint( $preset['image_id'] );
							$preset_url  = $preset_img ? wp_get_attachment_image_url( $preset_img, 'thumbnail' ) : '';
							$defaults    = isset( $preset['defaults'] ) && is_array( $preset['defaults'] ) ? $preset['defaults'] : array();
							?>
							<details class="ctm-color-preset" data-color="<?php echo esc_attr( $color_slug ); ?>" <?php echo array_key_first( $main_colors ) === $color_slug ? 'open' : ''; ?>>
								<summary class="ctm-color-preset__summary">
									<?php echo esc_html( $color_label ); ?>
									<code><?php echo esc_html( $color_slug ); ?></code>
								</summary>

								<div class="ctm-color-preset__body">
									<p class="form-field ctm-look-image-field ctm-color-preset__image">
										<label><?php esc_html_e( 'Look image for this color', 'woo-complete-the-look' ); ?></label>
										<span class="ctm-look-image-wrap">
											<input
												type="hidden"
												class="ctm-color-preset-image-id"
												name="ctm_color_presets[<?php echo esc_attr( $color_slug ); ?>][image_id]"
												value="<?php echo esc_attr( (string) $preset_img ); ?>"
											/>
											<span class="ctm-look-image-preview ctm-color-preset-image-preview"<?php echo $preset_url ? '' : ' style="display:none;"'; ?>>
												<?php if ( $preset_url ) : ?>
													<img src="<?php echo esc_url( $preset_url ); ?>" alt="" />
												<?php endif; ?>
											</span>
											<button type="button" class="button ctm-look-image-upload" data-target="preset">
												<?php
												echo $preset_img
													? esc_html__( 'Change image', 'woo-complete-the-look' )
													: esc_html__( 'Upload image', 'woo-complete-the-look' );
												?>
											</button>
											<button type="button" class="button ctm-look-image-remove" data-target="preset"<?php echo $preset_img ? '' : ' style="display:none;"'; ?>>
												<?php esc_html_e( 'Remove', 'woo-complete-the-look' ); ?>
											</button>
										</span>
									</p>

									<div class="ctm-color-preset__products" data-color="<?php echo esc_attr( $color_slug ); ?>">
										<?php
										if ( empty( $product_ids ) ) {
											echo '<p class="ctm-color-preset__empty">' . esc_html__( 'Select Complete the Look products above first.', 'woo-complete-the-look' ) . '</p>';
										} else {
											foreach ( $product_ids as $linked_id ) {
												$this->render_preset_product_row( $color_slug, $linked_id, $defaults );
											}
										}
										?>
									</div>
								</div>
							</details>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else : ?>
				<div class="options_group">
					<p class="form-field">
						<span class="description">
							<?php esc_html_e( 'Color presets appear here when this product is variable and has a Color attribute.', 'woo-complete-the-look' ); ?>
						</span>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * One product row inside a color preset panel.
	 *
	 * @param string $color_slug Main product color slug.
	 * @param int    $linked_id  Linked product ID.
	 * @param array  $defaults   Defaults map product_id => color slug.
	 * @return void
	 */
	private function render_preset_product_row( $color_slug, $linked_id, $defaults ) {
		$linked = wc_get_product( $linked_id );

		if ( ! $linked ) {
			return;
		}

		// If a variation was linked, map against its parent for color options.
		$option_product = $linked;
		$row_id         = $linked_id;

		if ( $linked->is_type( 'variation' ) ) {
			$parent = wc_get_product( $linked->get_parent_id() );
			if ( $parent ) {
				$option_product = $parent;
				$row_id         = $parent->get_id();
			}
		}

		$color_options = CTM_Helpers::get_color_options( $option_product );
		$selected      = isset( $defaults[ (string) $row_id ] ) ? $defaults[ (string) $row_id ] : '';

		// If a specific variation was chosen and no preset yet, prefill its color.
		if ( '' === $selected && $linked->is_type( 'variation' ) ) {
			$attr_name = CTM_Helpers::get_color_attribute_name( $option_product );
			if ( $attr_name ) {
				$selected = $linked->get_attribute( $attr_name );
				if ( taxonomy_exists( $attr_name ) ) {
					$term = get_term_by( 'name', $selected, $attr_name );
					if ( ! $term ) {
						$term = get_term_by( 'slug', $selected, $attr_name );
					}
					if ( $term && ! is_wp_error( $term ) ) {
						$selected = $term->slug;
					}
				}
			}
		}

		$name = wp_strip_all_tags( $option_product->get_formatted_name() );

		echo '<div class="ctm-color-preset__row" data-product-id="' . esc_attr( (string) $row_id ) . '">';
		echo '<span class="ctm-color-preset__product-name">' . esc_html( $name ) . '</span>';

		if ( empty( $color_options ) ) {
			echo '<span class="ctm-color-preset__no-color">' . esc_html__( 'No color attribute', 'woo-complete-the-look' ) . '</span>';
		} else {
			printf(
				'<select name="ctm_color_presets[%1$s][defaults][%2$d]" class="ctm-color-preset__color-select">',
				esc_attr( $color_slug ),
				absint( $row_id )
			);
			echo '<option value="">' . esc_html__( 'Same as main color', 'woo-complete-the-look' ) . '</option>';

			foreach ( $color_options as $opt_slug => $opt_label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $opt_slug ),
					selected( $selected, $opt_slug, false ),
					esc_html( $opt_label )
				);
			}

			echo '</select>';
		}

		echo '</div>';
	}

	/**
	 * Save selected Complete the Look product IDs, look image and color presets.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function save_product_meta( $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product save nonce.

		$look_image_id = isset( $_POST['ctm_look_image_id'] ) ? absint( wp_unslash( $_POST['ctm_look_image_id'] ) ) : 0;

		if ( $look_image_id && wp_attachment_is_image( $look_image_id ) ) {
			update_post_meta( $post_id, CTM_LOOK_IMAGE_META, $look_image_id );
		} else {
			delete_post_meta( $post_id, CTM_LOOK_IMAGE_META );
		}

		if ( ! isset( $_POST['ctm_product_ids'] ) || ! is_array( $_POST['ctm_product_ids'] ) ) {
			delete_post_meta( $post_id, CTM_META_KEY );
			delete_post_meta( $post_id, CTM_COLOR_PRESETS_META );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_ids = wp_unslash( $_POST['ctm_product_ids'] );
		$ids     = array_filter( array_map( 'absint', (array) $raw_ids ) );
		$ids     = array_values( array_diff( $ids, array( absint( $post_id ) ) ) );

		if ( empty( $ids ) ) {
			delete_post_meta( $post_id, CTM_META_KEY );
			delete_post_meta( $post_id, CTM_COLOR_PRESETS_META );
			return;
		}

		update_post_meta( $post_id, CTM_META_KEY, $ids );

		$raw_presets = isset( $_POST['ctm_color_presets'] ) ? wp_unslash( $_POST['ctm_color_presets'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$presets     = CTM_Helpers::sanitize_color_presets( $raw_presets );

		// Keep only products that are still linked (resolve variations to parents).
		$allowed = array();
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$allowed[] = $p->is_type( 'variation' ) ? $p->get_parent_id() : $id;
		}
		$allowed = array_fill_keys( array_map( 'strval', array_unique( array_filter( $allowed ) ) ), true );

		foreach ( $presets as $color_slug => $preset ) {
			$filtered = array();
			foreach ( $preset['defaults'] as $pid => $slug ) {
				if ( isset( $allowed[ (string) $pid ] ) ) {
					$filtered[ (string) $pid ] = $slug;
				}
			}
			$presets[ $color_slug ]['defaults'] = $filtered;
		}

		if ( empty( $presets ) ) {
			delete_post_meta( $post_id, CTM_COLOR_PRESETS_META );
		} else {
			update_post_meta( $post_id, CTM_COLOR_PRESETS_META, $presets );
		}
	}

	/**
	 * AJAX: return color options for selected linked products (admin UI refresh).
	 *
	 * @return void
	 */
	public function ajax_get_product_color_options() {
		check_ajax_referer( 'ctm_admin_product', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['product_ids'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$colors      = isset( $_POST['main_colors'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_POST['main_colors'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$rows = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$row_id = $product_id;
			if ( $product->is_type( 'variation' ) ) {
				$row_id  = $product->get_parent_id();
				$product = wc_get_product( $row_id );
				if ( ! $product ) {
					continue;
				}
			}

			$options = CTM_Helpers::get_color_options( $product );
			$option_html = '';

			if ( empty( $options ) ) {
				$option_html = '<span class="ctm-color-preset__no-color">' . esc_html__( 'No color attribute', 'woo-complete-the-look' ) . '</span>';
			} else {
				$option_html  = '<select class="ctm-color-preset__color-select">';
				$option_html .= '<option value="">' . esc_html__( 'Same as main color', 'woo-complete-the-look' ) . '</option>';
				foreach ( $options as $slug => $label ) {
					$option_html .= sprintf(
						'<option value="%1$s">%2$s</option>',
						esc_attr( $slug ),
						esc_html( $label )
					);
				}
				$option_html .= '</select>';
			}

			$rows[ (string) $row_id ] = array(
				'product_id' => $row_id,
				'name'       => wp_strip_all_tags( $product->get_formatted_name() ),
				'html'       => $option_html,
			);
		}

		wp_send_json_success(
			array(
				'rows'        => array_values( $rows ),
				'main_colors' => array_values( array_filter( $colors ) ),
			)
		);
	}

	/**
	 * Enqueue Select2 / media scripts on the product edit screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );

		wp_enqueue_media();
		wp_enqueue_style(
			'ctm-admin',
			CTM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CTM_VERSION
		);
		wp_enqueue_script(
			'ctm-admin-product',
			CTM_PLUGIN_URL . 'assets/js/admin-product.js',
			array( 'jquery' ),
			CTM_VERSION,
			true
		);
		wp_localize_script(
			'ctm-admin-product',
			'ctmAdminProduct',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ctm_admin_product' ),
				'i18n'    => array(
					'title'   => __( 'Select look image', 'woo-complete-the-look' ),
					'use'     => __( 'Use this image', 'woo-complete-the-look' ),
					'upload'  => __( 'Upload image', 'woo-complete-the-look' ),
					'change'  => __( 'Change image', 'woo-complete-the-look' ),
					'empty'   => __( 'Select Complete the Look products above first.', 'woo-complete-the-look' ),
					'same'    => __( 'Same as main color', 'woo-complete-the-look' ),
					'noColor' => __( 'No color attribute', 'woo-complete-the-look' ),
				),
			)
		);
	}
}
