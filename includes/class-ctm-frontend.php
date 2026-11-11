<?php
/**
 * Frontend display for Complete the Look.
 *
 * @package WooCompleteTheLook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Complete the Look section on single product pages.
 */
class CTM_Frontend {

	/**
	 * Singleton instance.
	 *
	 * @var CTM_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Context for the product card currently being rendered.
	 *
	 * @var array
	 */
	private $card_context = array();

	/**
	 * Get the singleton instance.
	 *
	 * @return CTM_Frontend
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp', array( $this, 'register_display_hook' ) );
		add_action( 'wp_ajax_ctm_add_selected_to_cart', array( $this, 'ajax_add_selected_to_cart' ) );
		add_action( 'wp_ajax_nopriv_ctm_add_selected_to_cart', array( $this, 'ajax_add_selected_to_cart' ) );
	}

	/**
	 * Attach render callback to the configured WooCommerce hook.
	 *
	 * @return void
	 */
	public function register_display_hook() {
		if ( ! is_product() ) {
			return;
		}

		$hook_data = CTM_Settings::get_display_hook();

		add_action(
			$hook_data['hook'],
			array( $this, 'render_complete_the_look' ),
			(int) $hook_data['priority']
		);

		// When the section is lower on the page, add a jump button near Add to Cart.
		$position = CTM_Settings::get( 'display_position', 'after_add_to_cart' );
		if ( 'after_add_to_cart' !== $position ) {
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_jump_button' ), 20 );
		}
	}

	/**
	 * Enqueue frontend assets on single product pages.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		$settings = CTM_Settings::get_all();

		wp_enqueue_style(
			'ctm-frontend',
			CTM_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			CTM_VERSION
		);

		$css = sprintf(
			'.ctm-complete-the-look{--ctm-cols-mobile:%1$d;--ctm-cols-tablet:%2$d;--ctm-cols-desktop:%3$d;}',
			absint( $settings['columns_mobile'] ),
			absint( $settings['columns_tablet'] ),
			absint( $settings['columns_desktop'] )
		);
		wp_add_inline_style( 'ctm-frontend', $css );

		wp_enqueue_script(
			'ctm-frontend',
			CTM_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery', 'wc-add-to-cart', 'wc-cart-fragments' ),
			CTM_VERSION,
			true
		);

		$button_text = ! empty( $settings['button_text'] )
			? $settings['button_text']
			: __( 'Add selected to cart', 'woo-complete-the-look' );

		wp_localize_script(
			'ctm-frontend',
			'ctmFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ctm_add_selected' ),
				'i18n'    => array(
					'addSelected'       => $button_text,
					'adding'            => __( 'Adding…', 'woo-complete-the-look' ),
					'noneSelected'      => __( 'Please select at least one product.', 'woo-complete-the-look' ),
					'selectOptions'     => __( 'Please choose options for all selected products.', 'woo-complete-the-look' ),
					'unavailable'       => __( 'This combination is unavailable. Please choose another option.', 'woo-complete-the-look' ),
					'error'             => __( 'Could not add products to the cart. Please try again.', 'woo-complete-the-look' ),
					'chooseOption'      => __( 'Choose an option', 'woo-complete-the-look' ),
				),
			)
		);
	}

	/**
	 * Whether the current product has Complete the Look items to show.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function product_has_look_items( $product_id ) {
		$related_ids = get_post_meta( $product_id, CTM_META_KEY, true );

		if ( empty( $related_ids ) || ! is_array( $related_ids ) ) {
			return false;
		}

		$related_ids = array_filter( array_map( 'absint', $related_ids ) );

		return ! empty( $related_ids );
	}

	/**
	 * Jump button near Add to Cart when the section is placed lower on the page.
	 *
	 * @return void
	 */
	public function render_jump_button() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( ! $this->product_has_look_items( $product->get_id() ) ) {
			return;
		}

		$settings = CTM_Settings::get_all();
		$label    = ! empty( $settings['jump_button_text'] )
			? $settings['jump_button_text']
			: ( ! empty( $settings['section_title'] ) ? $settings['section_title'] : __( 'Complete the Look', 'woo-complete-the-look' ) );

		?>
		<a
			href="#ctm-complete-the-look"
			class="button alt single_add_to_cart_button ctm-complete-the-look__jump"
		>
			<?php echo esc_html( $label ); ?>
		</a>
		<?php
	}

	/**
	 * Output the Complete the Look section.
	 *
	 * @return void
	 */
	public function render_complete_the_look() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$related_ids = get_post_meta( $product->get_id(), CTM_META_KEY, true );

		if ( empty( $related_ids ) || ! is_array( $related_ids ) ) {
			return;
		}

		$related_ids = array_map( 'absint', $related_ids );
		$related_ids = array_filter( $related_ids );

		if ( empty( $related_ids ) ) {
			return;
		}

		$settings              = CTM_Settings::get_all();
		$include_main_product  = ( 'yes' === $settings['include_main_product'] );
		$main_product_id       = $product->get_id();

		// Build ID list: main product first (default), then linked products.
		$display_ids = $related_ids;

		if ( $include_main_product ) {
			$display_ids = array_values(
				array_unique(
					array_merge( array( $main_product_id ), $related_ids )
				)
			);
		}

		$items = array();

		foreach ( $display_ids as $related_id ) {
			$related_product = wc_get_product( $related_id );

			if ( ! $related_product || ! $related_product->is_visible() ) {
				continue;
			}

			if ( ! $related_product->is_in_stock() && ! $related_product->backorders_allowed() ) {
				continue;
			}

			$items[] = $related_product;
		}

		if ( empty( $items ) ) {
			return;
		}

		$show_checkboxes  = ( 'yes' === $settings['show_checkboxes'] );
		$check_default    = ( 'yes' === $settings['checkboxes_default'] );
		$show_price       = ( 'yes' === $settings['show_price'] );
		$show_add_button  = ( 'yes' === $settings['show_add_button'] );
		$show_look_image  = ( 'yes' === $settings['show_look_image'] );
		$look_position    = sanitize_html_class( $settings['look_image_position'] );
		$look_hover       = sanitize_html_class( str_replace( '_', '-', $settings['look_hover_effect'] ) );
		$layout           = sanitize_html_class( $settings['layout'] );
		$text_align       = sanitize_html_class( $settings['text_align'] );
		$section_title    = $settings['section_title'];
		$button_text      = $settings['button_text'];

		$look_image_id   = absint( get_post_meta( $product->get_id(), CTM_LOOK_IMAGE_META, true ) );
		$look_image_html = '';
		$default_look_url = '';

		if ( $show_look_image && $look_image_id && wp_attachment_is_image( $look_image_id ) ) {
			$default_look_url = wp_get_attachment_image_url( $look_image_id, 'large' );
			$look_image_html  = wp_get_attachment_image(
				$look_image_id,
				'large',
				false,
				array(
					'class'            => 'ctm-complete-the-look__look-img',
					'loading'          => 'lazy',
					'data-default-src' => $default_look_url,
				)
			);
		}

		$has_look_image = '' !== $look_image_html;
		$has_selectable = false;

		foreach ( $items as $item ) {
			if ( $this->is_selectable( $item ) ) {
				$has_selectable = true;
				break;
			}
		}

		$main_color_attr = CTM_Helpers::get_color_attribute_name( $product );
		$color_presets   = $this->prepare_color_presets_for_frontend(
			get_post_meta( $product->get_id(), CTM_COLOR_PRESETS_META, true ),
			$show_look_image
		);

		$section_classes = array(
			'ctm-complete-the-look',
			'ctm-layout-' . $layout,
			'ctm-align-' . $text_align,
		);

		if ( CTM_Helpers::is_jofme_theme() ) {
			$section_classes[] = 'ctm-theme-jofme';
		}

		if ( $has_look_image ) {
			$section_classes[] = 'ctm-has-look-image';
			$section_classes[] = 'ctm-look-pos-' . $look_position;
			$section_classes[] = 'ctm-look-hover-' . $look_hover;
		}

		if ( $main_color_attr ) {
			$section_classes[] = 'ctm-syncs-main-color';
		}

		?>
		<section
			id="ctm-complete-the-look"
			class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"
			aria-labelledby="ctm-heading"
			data-main-product-id="<?php echo esc_attr( (string) $main_product_id ); ?>"
			<?php if ( $main_color_attr ) : ?>
				data-main-color-attribute="<?php echo esc_attr( 'attribute_' . sanitize_title( $main_color_attr ) ); ?>"
				data-color-presets="<?php echo esc_attr( wp_json_encode( $color_presets ) ); ?>"
				data-default-look-image="<?php echo esc_attr( $default_look_url ); ?>"
			<?php endif; ?>
		>
			<?php if ( '' !== $section_title ) : ?>
				<h2 id="ctm-heading" class="ctm-complete-the-look__title" tabindex="-1">
					<?php echo esc_html( $section_title ); ?>
				</h2>
			<?php endif; ?>

			<div class="ctm-complete-the-look__body">
				<?php if ( $has_look_image ) : ?>
					<figure class="ctm-complete-the-look__look">
						<?php echo $look_image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</figure>
				<?php endif; ?>

				<div class="ctm-complete-the-look__products">
					<ul class="products columns-<?php echo esc_attr( (string) absint( $settings['columns_desktop'] ) ); ?> ctm-complete-the-look__grid">
						<?php
						foreach ( $items as $item ) {
							$this->render_theme_product_card(
								$item,
								array(
									'main_product_id' => $main_product_id,
									'show_checkboxes' => $show_checkboxes,
									'check_default'   => $check_default,
									'show_price'      => $show_price,
								)
							);
						}
						?>
					</ul>

					<?php if ( $show_add_button && $show_checkboxes && $has_selectable ) : ?>
						<div class="ctm-complete-the-look__footer">
							<button type="button" class="button alt ctm-complete-the-look__add-selected">
								<?php echo esc_html( $button_text ); ?>
							</button>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Temporarily detach loop callbacks that conflict with Complete the Look cards.
	 *
	 * Jofme catalog extras (quick view, card swatches, list descriptions) clash with
	 * CTL selection / variation UI. Media wrapper + category stay intact.
	 *
	 * @return array[] List of [ hook, callback, priority ] to restore.
	 */
	private function pause_theme_card_extras() {
		$removed = array();

		if ( ! CTM_Helpers::is_jofme_theme() ) {
			return $removed;
		}

		$callbacks = array(
			array( 'woocommerce_after_shop_loop_item', 'jofme_card_swatches', 7 ),
			array( 'woocommerce_after_shop_loop_item', 'jofme_card_description', 7 ),
			array( 'woocommerce_after_shop_loop_item', 'jofme_card_actions', 12 ),
		);

		foreach ( $callbacks as $callback ) {
			list( $hook, $function, $priority ) = $callback;

			if ( function_exists( $function ) && false !== has_action( $hook, $function ) ) {
				remove_action( $hook, $function, $priority );
				$removed[] = $callback;
			}
		}

		return $removed;
	}

	/**
	 * Restore previously paused theme card callbacks.
	 *
	 * @param array[] $removed List from pause_theme_card_extras().
	 * @return void
	 */
	private function resume_theme_card_extras( $removed ) {
		foreach ( (array) $removed as $callback ) {
			if ( count( $callback ) < 3 ) {
				continue;
			}

			list( $hook, $function, $priority ) = $callback;

			if ( function_exists( $function ) ) {
				add_action( $hook, $function, $priority );
			}
		}
	}

	/**
	 * Render one product using WooCommerce loop card hooks (theme styles).
	 *
	 * @param WC_Product $item    Product object.
	 * @param array      $context Card context flags.
	 * @return void
	 */
	private function render_theme_product_card( $item, $context ) {
		global $product, $post;

		$previous_product = $product;
		$previous_post    = isset( $post ) ? $post : null;

		$product = $item;
		$post    = get_post( $item->get_id() );

		if ( $post ) {
			setup_postdata( $post );
		}

		$can_select  = $this->is_selectable( $item );
		$is_variable = $item->is_type( 'variable' );
		$item_id     = $item->get_id();
		$is_main     = ( $item_id === (int) $context['main_product_id'] );
		$show_check  = ! empty( $context['show_checkboxes'] ) && $can_select;

		$this->card_context = array(
			'product'         => $item,
			'can_select'      => $can_select,
			'is_variable'     => $is_variable,
			'is_main'         => $is_main,
			'show_check'      => $show_check,
			'check_default'   => ! empty( $context['check_default'] ),
			'show_price'      => ! empty( $context['show_price'] ),
			'checkbox_id'     => 'ctm-product-' . $item_id,
		);

		$classes = array( 'ctm-complete-the-look__item' );
		if ( ! $can_select ) {
			$classes[] = 'ctm-complete-the-look__item--view-only';
		}
		if ( $is_variable ) {
			$classes[] = 'ctm-complete-the-look__item--variable';
		}
		if ( $is_main ) {
			$classes[] = 'ctm-complete-the-look__item--main';
		}

		$variations_json = $is_variable ? wp_json_encode( $this->get_available_variations_data( $item ) ) : '';

		// Use theme/WooCommerce loop card hooks, without the default loop ATC button.
		$had_loop_atc   = false !== has_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
		$had_loop_price = false !== has_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price' );
		$paused_extras  = $this->pause_theme_card_extras();

		if ( $had_loop_atc ) {
			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		}

		if ( empty( $context['show_price'] ) && $had_loop_price ) {
			remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		}

		add_action( 'woocommerce_before_shop_loop_item', array( $this, 'render_card_checkbox' ), 4 );
		add_action( 'woocommerce_shop_loop_item_title', array( $this, 'render_card_badge' ), 6 );
		// After link close (priority 5), so interactive controls are not nested in <a>.
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_card_controls' ), 6 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_card_image_attributes' ), 20, 2 );

		?>
		<li
			<?php wc_product_class( $classes, $item ); ?>
			data-product-id="<?php echo esc_attr( (string) $item_id ); ?>"
			data-product-type="<?php echo esc_attr( $item->get_type() ); ?>"
			<?php if ( $is_variable ) : ?>
				data-product_variations="<?php echo esc_attr( $variations_json ); ?>"
			<?php endif; ?>
		>
			<?php
			/**
			 * Hook: woocommerce_before_shop_loop_item.
			 *
			 * @hooked woocommerce_template_loop_product_link_open - 10
			 */
			do_action( 'woocommerce_before_shop_loop_item' );

			/**
			 * Hook: woocommerce_before_shop_loop_item_title.
			 *
			 * @hooked woocommerce_show_product_loop_sale_flash - 10
			 * @hooked woocommerce_template_loop_product_thumbnail - 10
			 */
			do_action( 'woocommerce_before_shop_loop_item_title' );

			/**
			 * Hook: woocommerce_shop_loop_item_title.
			 *
			 * @hooked woocommerce_template_loop_product_title - 10
			 */
			do_action( 'woocommerce_shop_loop_item_title' );

			/**
			 * Hook: woocommerce_after_shop_loop_item_title.
			 *
			 * @hooked woocommerce_template_loop_rating - 5
			 * @hooked woocommerce_template_loop_price - 10
			 */
			do_action( 'woocommerce_after_shop_loop_item_title' );

			/**
			 * Hook: woocommerce_after_shop_loop_item.
			 *
			 * @hooked woocommerce_template_loop_product_link_close - 5
			 */
			do_action( 'woocommerce_after_shop_loop_item' );
			?>
		</li>
		<?php

		remove_action( 'woocommerce_before_shop_loop_item', array( $this, 'render_card_checkbox' ), 4 );
		remove_action( 'woocommerce_shop_loop_item_title', array( $this, 'render_card_badge' ), 6 );
		remove_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_card_controls' ), 6 );
		remove_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_card_image_attributes' ), 20 );

		if ( $had_loop_atc ) {
			add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		}

		if ( empty( $context['show_price'] ) && $had_loop_price ) {
			add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		}

		$this->resume_theme_card_extras( $paused_extras );

		$this->card_context = array();

		$product = $previous_product;
		$post    = $previous_post;

		if ( $previous_post ) {
			setup_postdata( $previous_post );
		} else {
			wp_reset_postdata();
		}
	}

	/**
	 * Checkbox overlay for the theme product card.
	 *
	 * @return void
	 */
	public function render_card_checkbox() {
		if ( empty( $this->card_context['show_check'] ) || empty( $this->card_context['product'] ) ) {
			return;
		}

		$item        = $this->card_context['product'];
		$checkbox_id = $this->card_context['checkbox_id'];
		?>
		<label class="ctm-complete-the-look__check" for="<?php echo esc_attr( $checkbox_id ); ?>">
			<input
				type="checkbox"
				class="ctm-complete-the-look__checkbox"
				id="<?php echo esc_attr( $checkbox_id ); ?>"
				name="ctm_product_ids[]"
				value="<?php echo esc_attr( (string) $item->get_id() ); ?>"
				data-product-id="<?php echo esc_attr( (string) $item->get_id() ); ?>"
				<?php checked( ! empty( $this->card_context['check_default'] ) ); ?>
			/>
			<span class="ctm-complete-the-look__check-mark" aria-hidden="true"></span>
			<span class="screen-reader-text">
				<?php
				printf(
					/* translators: %s: product name */
					esc_html__( 'Select %s', 'woo-complete-the-look' ),
					esc_html( $item->get_name() )
				);
				?>
			</span>
		</label>
		<?php
	}

	/**
	 * "This product" badge on the main item card.
	 *
	 * @return void
	 */
	public function render_card_badge() {
		if ( empty( $this->card_context['is_main'] ) ) {
			return;
		}
		?>
		<span class="ctm-complete-the-look__badge">
			<?php esc_html_e( 'This product', 'woo-complete-the-look' ); ?>
		</span>
		<?php
	}

	/**
	 * Variation controls / view link under the theme card price.
	 *
	 * @return void
	 */
	public function render_card_controls() {
		if ( empty( $this->card_context['product'] ) ) {
			return;
		}

		$item = $this->card_context['product'];

		if ( ! empty( $this->card_context['is_variable'] ) && ! empty( $this->card_context['can_select'] ) ) {
			$this->render_variation_attributes( $item );
			return;
		}

		if ( empty( $this->card_context['can_select'] ) ) {
			?>
			<div class="ctm-complete-the-look__actions">
				<a class="button ctm-complete-the-look__button" href="<?php echo esc_url( $item->get_permalink() ); ?>">
					<?php esc_html_e( 'View product', 'woo-complete-the-look' ); ?>
				</a>
			</div>
			<?php
		}
	}

	/**
	 * Mark loop thumbnail for smooth variation image swaps.
	 *
	 * @param array        $attr       Image attributes.
	 * @param WP_Post|null $attachment Attachment post.
	 * @return array
	 */
	public function filter_card_image_attributes( $attr, $attachment ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( empty( $this->card_context ) ) {
			return $attr;
		}

		$class = isset( $attr['class'] ) ? (string) $attr['class'] : '';

		// Keep Jofme hover overlay images out of CTL variation swaps.
		if ( false !== strpos( $class, 'jofme-hover-image' ) ) {
			return $attr;
		}

		$attr['class'] = trim( $class . ' ctm-complete-the-look__image' );

		if ( empty( $attr['data-default-src'] ) && ! empty( $attr['src'] ) ) {
			$attr['data-default-src'] = $attr['src'];
		}

		return $attr;
	}

	/**
	 * Whether a product can be selected in the Complete the Look UI.
	 *
	 * @param WC_Product $item Product object.
	 * @return bool
	 */
	private function is_selectable( $item ) {
		if ( ! $item->is_purchasable() ) {
			return false;
		}

		if ( $item->is_type( array( 'grouped', 'external' ) ) ) {
			return false;
		}

		if ( $item->is_type( 'variable' ) ) {
			return $item->is_in_stock() || $item->backorders_allowed();
		}

		return $item->is_in_stock() || $item->backorders_allowed();
	}

	/**
	 * Whether a simple/variation product can be added without options.
	 *
	 * @param WC_Product $item Product object.
	 * @return bool
	 */
	private function is_direct_addable( $item ) {
		if ( ! $this->is_selectable( $item ) ) {
			return false;
		}

		if ( $item->is_type( 'variable' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Render variation attribute controls (color swatches + selects).
	 *
	 * @param WC_Product_Variable $product Variable product.
	 * @return void
	 */
	private function render_variation_attributes( $product ) {
		$attributes = $product->get_variation_attributes();

		if ( empty( $attributes ) ) {
			return;
		}

		echo '<div class="ctm-complete-the-look__variations">';

		foreach ( $attributes as $attribute_name => $options ) {
			if ( empty( $options ) ) {
				continue;
			}

			$taxonomy   = sanitize_title( $attribute_name );
			$field_name = 'attribute_' . $taxonomy;
			$label      = wc_attribute_label( $attribute_name, $product );
			$is_color   = CTM_Helpers::is_color_attribute( $attribute_name, $label );
			$select_id  = 'ctm-attr-' . $product->get_id() . '-' . $taxonomy;

			echo '<div class="ctm-complete-the-look__attribute' . ( $is_color ? ' ctm-complete-the-look__attribute--color' : '' ) . '" data-attribute="' . esc_attr( $field_name ) . '">';
			echo '<label class="ctm-complete-the-look__attribute-label" for="' . esc_attr( $select_id ) . '">' . esc_html( $label ) . '</label>';

			if ( $is_color ) {
				echo '<div class="ctm-complete-the-look__swatches" role="listbox" aria-label="' . esc_attr( $label ) . '">';

				foreach ( $options as $option ) {
					$term_label = $option;
					$swatch_hex = '';

					if ( taxonomy_exists( $attribute_name ) ) {
						$term = get_term_by( 'slug', $option, $attribute_name );
						if ( $term && ! is_wp_error( $term ) ) {
							$term_label = $term->name;
							$swatch_hex = CTM_Helpers::get_term_color_value( $term );
						}
					}

					$swatch_class = 'ctm-complete-the-look__swatch';
					$style_attr   = '';

					if ( $swatch_hex ) {
						$swatch_class .= ' ctm-complete-the-look__swatch--has-color';
						$style_attr    = ' style="--ctm-swatch:' . esc_attr( $swatch_hex ) . ';"';
					} else {
						$swatch_class .= ' ctm-complete-the-look__swatch--text';
					}

					printf(
						'<button type="button" class="%1$s" role="option" aria-selected="false" aria-label="%2$s" data-value="%3$s" title="%2$s"%4$s><span class="ctm-complete-the-look__swatch-label">%5$s</span></button>',
						esc_attr( $swatch_class ),
						esc_attr( $term_label ),
						esc_attr( $option ),
						$style_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
						esc_html( $swatch_hex ? '' : $term_label )
					);
				}

				echo '</div>';
			}

			$select_class = 'ctm-complete-the-look__attr-select';
			if ( $is_color ) {
				$select_class .= ' ctm-complete-the-look__attr-select--color-sync';
			}

			printf(
				'<select class="%1$s" id="%2$s" name="%3$s" data-attribute_name="%3$s"%4$s>',
				esc_attr( $select_class ),
				esc_attr( $select_id ),
				esc_attr( $field_name ),
				$is_color ? ' hidden' : ''
			);
			echo '<option value="">' . esc_html__( 'Choose an option', 'woo-complete-the-look' ) . '</option>';

			foreach ( $options as $option ) {
				$term_label = $option;

				if ( taxonomy_exists( $attribute_name ) ) {
					$term = get_term_by( 'slug', $option, $attribute_name );
					if ( $term && ! is_wp_error( $term ) ) {
						$term_label = $term->name;
					}
				}

				printf(
					'<option value="%1$s">%2$s</option>',
					esc_attr( $option ),
					esc_html( apply_filters( 'woocommerce_variation_option_name', $term_label, null, $attribute_name, $product ) )
				);
			}

			echo '</select>';
			echo '</div>';
		}

		echo '<input type="hidden" class="ctm-complete-the-look__variation-id" value="" />';
		echo '</div>';
	}

	/**
	 * Prepare color presets with image URLs for the frontend.
	 *
	 * @param mixed $raw_presets Raw meta.
	 * @param bool  $include_images Whether look images are enabled.
	 * @return array
	 */
	private function prepare_color_presets_for_frontend( $raw_presets, $include_images ) {
		$presets = CTM_Helpers::sanitize_color_presets( $raw_presets );
		$output  = array();

		foreach ( $presets as $color_slug => $preset ) {
			$image_url = '';

			if ( $include_images && ! empty( $preset['image_id'] ) ) {
				$image_url = wp_get_attachment_image_url( absint( $preset['image_id'] ), 'large' );
				$image_url = $image_url ? $image_url : '';
			}

			$output[ $color_slug ] = array(
				'image_url' => $image_url,
				'defaults'  => $preset['defaults'],
			);
		}

		return $output;
	}

	/**
	 * Compact variation payload for frontend matching.
	 *
	 * @param WC_Product_Variable $product Variable product.
	 * @return array
	 */
	private function get_available_variations_data( $product ) {
		$variations = array();

		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );

			if ( ! $variation || ! $variation->exists() ) {
				continue;
			}

			$available = $variation->is_purchasable() && ( $variation->is_in_stock() || $variation->backorders_allowed() );

			if ( ! $available ) {
				continue;
			}

			$image_id  = $variation->get_image_id() ? $variation->get_image_id() : $product->get_image_id();
			$image_url = $image_id
				? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
				: wc_placeholder_img_src( 'woocommerce_thumbnail' );

			$variations[] = array(
				'variation_id'        => $variation->get_id(),
				'attributes'          => $variation->get_variation_attributes(),
				'image_src'           => $image_url ? $image_url : '',
				'image_id'            => absint( $image_id ),
				'price_html'          => $variation->get_price_html(),
				'display_price'       => wc_get_price_to_display( $variation ),
				'is_in_stock'         => $variation->is_in_stock(),
				'variation_is_active' => true,
			);
		}

		return $variations;
	}

	/**
	 * AJAX: add checked Complete the Look products to the cart.
	 *
	 * @return void
	 */
	public function ajax_add_selected_to_cart() {
		check_ajax_referer( 'ctm_add_selected', 'nonce' );

		$raw_items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Backward compatible fallback: product_ids only.
		if ( empty( $raw_items ) && isset( $_POST['product_ids'] ) ) {
			$product_ids = array_map( 'absint', (array) wp_unslash( $_POST['product_ids'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_items   = array();
			foreach ( $product_ids as $pid ) {
				$raw_items[] = array( 'product_id' => $pid );
			}
		}

		if ( ! is_array( $raw_items ) || empty( $raw_items ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please select at least one product.', 'woo-complete-the-look' ),
				)
			);
		}

		$added   = array();
		$skipped = array();

		foreach ( $raw_items as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}

			$product_id   = isset( $raw_item['product_id'] ) ? absint( $raw_item['product_id'] ) : 0;
			$variation_id = isset( $raw_item['variation_id'] ) ? absint( $raw_item['variation_id'] ) : 0;
			$attributes   = isset( $raw_item['attributes'] ) && is_array( $raw_item['attributes'] ) ? $raw_item['attributes'] : array();

			$product = wc_get_product( $product_id );

			if ( ! $product || ! $this->is_selectable( $product ) ) {
				$skipped[] = $product_id;
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				if ( ! $variation_id ) {
					$skipped[] = $product_id;
					continue;
				}

				$variation = wc_get_product( $variation_id );

				if ( ! $variation || 'variation' !== $variation->get_type() || (int) $variation->get_parent_id() !== $product_id ) {
					$skipped[] = $product_id;
					continue;
				}

				if ( ! $variation->is_purchasable() || ( ! $variation->is_in_stock() && ! $variation->backorders_allowed() ) ) {
					$skipped[] = $product_id;
					continue;
				}

				$variation_attributes = $variation->get_variation_attributes();

				foreach ( $attributes as $attr_key => $attr_value ) {
					$clean_key = (string) $attr_key;
					if ( 0 !== strpos( $clean_key, 'attribute_' ) ) {
						$clean_key = 'attribute_' . sanitize_title( $clean_key );
					}
					$value = wc_clean( wp_unslash( (string) $attr_value ) );
					if ( '' !== $value ) {
						$variation_attributes[ $clean_key ] = $value;
					}
				}

				$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation_attributes );
			} else {
				if ( ! $this->is_direct_addable( $product ) ) {
					$skipped[] = $product_id;
					continue;
				}

				$cart_item_key = WC()->cart->add_to_cart( $product_id, 1 );
			}

			if ( $cart_item_key ) {
				$added[] = $variation_id ? $variation_id : $product_id;
			} else {
				$skipped[] = $product_id;
			}
		}

		if ( empty( $added ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Could not add products to the cart. Please try again.', 'woo-complete-the-look' ),
				)
			);
		}

		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();

		$data = array(
			'added'     => $added,
			'skipped'   => $skipped,
			'cart_hash' => WC()->cart->get_cart_hash(),
			'fragments' => apply_filters(
				'woocommerce_add_to_cart_fragments',
				array(
					'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
				)
			),
		);

		wp_send_json_success( $data );
	}
}
