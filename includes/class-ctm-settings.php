<?php
/**
 * Plugin settings page and helpers.
 *
 * @package WooCompleteTheLook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin settings page and stores layout options.
 */
class CTM_Settings {

	/**
	 * Option name in wp_options.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'ctm_settings';

	/**
	 * Singleton instance.
	 *
	 * @var CTM_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return CTM_Settings
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Default settings values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'section_title'       => __( 'Complete the Look', 'woo-complete-the-look' ),
			'button_text'         => __( 'Add selected to cart', 'woo-complete-the-look' ),
			'jump_button_text'    => __( 'Complete the Look', 'woo-complete-the-look' ),
			'layout'              => 'grid',
			'columns_desktop'     => 4,
			'columns_tablet'      => 3,
			'columns_mobile'      => 2,
			'display_position'    => 'after_add_to_cart',
			'text_align'          => 'center',
			'checkboxes_default'  => 'yes',
			'show_price'          => 'yes',
			'show_add_button'     => 'yes',
			'show_checkboxes'     => 'yes',
			'show_look_image'      => 'yes',
			'look_image_position'  => 'left',
			'look_hover_effect'    => 'zoom_bright',
			'include_main_product' => 'yes',
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_all() {
		$saved = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional fallback if key missing.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Add submenu under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Complete the Look', 'woo-complete-the-look' ),
			__( 'Complete the Look', 'woo-complete-the-look' ),
			'manage_woocommerce',
			'woo-complete-the-look',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register setting with sanitization callback.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'ctm_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Enqueue light admin CSS on the settings page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_woo-complete-the-look' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ctm-admin',
			CTM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CTM_VERSION
		);
	}

	/**
	 * Sanitize settings before save.
	 *
	 * @param array $input Raw form input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		$output['section_title'] = isset( $input['section_title'] )
			? sanitize_text_field( $input['section_title'] )
			: $defaults['section_title'];

		$output['button_text'] = isset( $input['button_text'] )
			? sanitize_text_field( $input['button_text'] )
			: $defaults['button_text'];

		$output['jump_button_text'] = isset( $input['jump_button_text'] )
			? sanitize_text_field( $input['jump_button_text'] )
			: $defaults['jump_button_text'];

		$layouts = array( 'grid', 'list', 'compact' );
		$output['layout'] = ( isset( $input['layout'] ) && in_array( $input['layout'], $layouts, true ) )
			? $input['layout']
			: $defaults['layout'];

		$output['columns_desktop'] = $this->sanitize_columns( $input['columns_desktop'] ?? $defaults['columns_desktop'], array( 2, 3, 4, 5, 6 ) );
		$output['columns_tablet']  = $this->sanitize_columns( $input['columns_tablet'] ?? $defaults['columns_tablet'], array( 2, 3, 4 ) );
		$output['columns_mobile']  = $this->sanitize_columns( $input['columns_mobile'] ?? $defaults['columns_mobile'], array( 1, 2 ) );

		$positions = array(
			'after_add_to_cart',
			'after_summary',
			'after_meta',
			'before_related',
		);
		$output['display_position'] = ( isset( $input['display_position'] ) && in_array( $input['display_position'], $positions, true ) )
			? $input['display_position']
			: $defaults['display_position'];

		$alignments = array( 'left', 'center' );
		$output['text_align'] = ( isset( $input['text_align'] ) && in_array( $input['text_align'], $alignments, true ) )
			? $input['text_align']
			: $defaults['text_align'];

		foreach ( array( 'checkboxes_default', 'show_price', 'show_add_button', 'show_checkboxes', 'show_look_image', 'include_main_product' ) as $yes_no_key ) {
			$output[ $yes_no_key ] = ( isset( $input[ $yes_no_key ] ) && 'yes' === $input[ $yes_no_key ] ) ? 'yes' : 'no';
		}

		$look_positions = array( 'above', 'left', 'right' );
		$output['look_image_position'] = ( isset( $input['look_image_position'] ) && in_array( $input['look_image_position'], $look_positions, true ) )
			? $input['look_image_position']
			: $defaults['look_image_position'];

		$hover_effects = array( 'none', 'zoom', 'zoom_bright', 'fade', 'lift', 'colorize', 'soft_pan' );
		$output['look_hover_effect'] = ( isset( $input['look_hover_effect'] ) && in_array( $input['look_hover_effect'], $hover_effects, true ) )
			? $input['look_hover_effect']
			: $defaults['look_hover_effect'];

		return $output;
	}

	/**
	 * Sanitize a column count against an allow-list.
	 *
	 * @param mixed $value   Submitted value.
	 * @param array $allowed Allowed integers.
	 * @return int
	 */
	private function sanitize_columns( $value, $allowed ) {
		$value = absint( $value );

		if ( ! in_array( $value, $allowed, true ) ) {
			return (int) $allowed[0];
		}

		return $value;
	}

	/**
	 * Render the settings page markup.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = self::get_all();
		?>
		<div class="wrap ctm-settings-wrap">
			<h1><?php esc_html_e( 'Complete the Look', 'woo-complete-the-look' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Configure how the Complete the Look section appears on product pages.', 'woo-complete-the-look' ); ?>
			</p>

			<form method="post" action="options.php" class="ctm-settings-form">
				<?php settings_fields( 'ctm_settings_group' ); ?>

				<div class="ctm-settings-card">
					<h2><?php esc_html_e( 'Content', 'woo-complete-the-look' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="ctm_section_title"><?php esc_html_e( 'Section title', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="ctm_section_title"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[section_title]"
									value="<?php echo esc_attr( $settings['section_title'] ); ?>"
									class="regular-text"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ctm_button_text"><?php esc_html_e( 'Add selected button text', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="ctm_button_text"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[button_text]"
									value="<?php echo esc_attr( $settings['button_text'] ); ?>"
									class="regular-text"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ctm_jump_button_text"><?php esc_html_e( 'Jump button text', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="ctm_jump_button_text"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[jump_button_text]"
									value="<?php echo esc_attr( $settings['jump_button_text'] ); ?>"
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'Shown next to Add to Cart when the section is placed lower on the page (e.g. before related products).', 'woo-complete-the-look' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ctm_display_position"><?php esc_html_e( 'Display position', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<select id="ctm_display_position" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[display_position]">
									<option value="after_add_to_cart" <?php selected( $settings['display_position'], 'after_add_to_cart' ); ?>>
										<?php esc_html_e( 'Below Add to Cart', 'woo-complete-the-look' ); ?>
									</option>
									<option value="after_summary" <?php selected( $settings['display_position'], 'after_summary' ); ?>>
										<?php esc_html_e( 'After product summary', 'woo-complete-the-look' ); ?>
									</option>
									<option value="after_meta" <?php selected( $settings['display_position'], 'after_meta' ); ?>>
										<?php esc_html_e( 'After product meta', 'woo-complete-the-look' ); ?>
									</option>
									<option value="before_related" <?php selected( $settings['display_position'], 'before_related' ); ?>>
										<?php esc_html_e( 'Before related / featured products', 'woo-complete-the-look' ); ?>
									</option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div class="ctm-settings-card">
					<h2><?php esc_html_e( 'Layout', 'woo-complete-the-look' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="ctm_layout"><?php esc_html_e( 'Layout style', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<select id="ctm_layout" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[layout]">
									<option value="grid" <?php selected( $settings['layout'], 'grid' ); ?>>
										<?php esc_html_e( 'Grid', 'woo-complete-the-look' ); ?>
									</option>
									<option value="list" <?php selected( $settings['layout'], 'list' ); ?>>
										<?php esc_html_e( 'List (horizontal rows)', 'woo-complete-the-look' ); ?>
									</option>
									<option value="compact" <?php selected( $settings['layout'], 'compact' ); ?>>
										<?php esc_html_e( 'Compact cards', 'woo-complete-the-look' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Grid and Compact use the column settings below. List stacks products in rows.', 'woo-complete-the-look' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ctm_columns_desktop"><?php esc_html_e( 'Columns — desktop', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<select id="ctm_columns_desktop" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[columns_desktop]">
									<?php foreach ( array( 2, 3, 4, 5, 6 ) as $n ) : ?>
										<option value="<?php echo esc_attr( (string) $n ); ?>" <?php selected( (int) $settings['columns_desktop'], $n ); ?>>
											<?php echo esc_html( (string) $n ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ctm_columns_tablet"><?php esc_html_e( 'Columns — tablet', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<select id="ctm_columns_tablet" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[columns_tablet]">
									<?php foreach ( array( 2, 3, 4 ) as $n ) : ?>
										<option value="<?php echo esc_attr( (string) $n ); ?>" <?php selected( (int) $settings['columns_tablet'], $n ); ?>>
											<?php echo esc_html( (string) $n ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ctm_columns_mobile"><?php esc_html_e( 'Columns — mobile', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<select id="ctm_columns_mobile" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[columns_mobile]">
									<?php foreach ( array( 1, 2 ) as $n ) : ?>
										<option value="<?php echo esc_attr( (string) $n ); ?>" <?php selected( (int) $settings['columns_mobile'], $n ); ?>>
											<?php echo esc_html( (string) $n ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ctm_text_align"><?php esc_html_e( 'Text alignment', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<select id="ctm_text_align" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[text_align]">
									<option value="center" <?php selected( $settings['text_align'], 'center' ); ?>>
										<?php esc_html_e( 'Center', 'woo-complete-the-look' ); ?>
									</option>
									<option value="left" <?php selected( $settings['text_align'], 'left' ); ?>>
										<?php esc_html_e( 'Left', 'woo-complete-the-look' ); ?>
									</option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div class="ctm-settings-card">
					<h2><?php esc_html_e( 'Look image', 'woo-complete-the-look' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Show look image', 'woo-complete-the-look' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_look_image]"
										value="yes"
										<?php checked( $settings['show_look_image'], 'yes' ); ?>
									/>
									<?php esc_html_e( 'Display the outfit photo when one is set on the product', 'woo-complete-the-look' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ctm_look_image_position"><?php esc_html_e( 'Look image position', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<select id="ctm_look_image_position" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[look_image_position]">
									<option value="above" <?php selected( $settings['look_image_position'], 'above' ); ?>>
										<?php esc_html_e( 'Above products', 'woo-complete-the-look' ); ?>
									</option>
									<option value="left" <?php selected( $settings['look_image_position'], 'left' ); ?>>
										<?php esc_html_e( 'Left of products', 'woo-complete-the-look' ); ?>
									</option>
									<option value="right" <?php selected( $settings['look_image_position'], 'right' ); ?>>
										<?php esc_html_e( 'Right of products', 'woo-complete-the-look' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Upload the look photo per product under Product Data → Complete the Look.', 'woo-complete-the-look' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ctm_look_hover_effect"><?php esc_html_e( 'Look image hover effect', 'woo-complete-the-look' ); ?></label>
							</th>
							<td>
								<select id="ctm_look_hover_effect" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[look_hover_effect]">
									<option value="none" <?php selected( $settings['look_hover_effect'], 'none' ); ?>>
										<?php esc_html_e( 'None', 'woo-complete-the-look' ); ?>
									</option>
									<option value="zoom" <?php selected( $settings['look_hover_effect'], 'zoom' ); ?>>
										<?php esc_html_e( 'Smooth zoom', 'woo-complete-the-look' ); ?>
									</option>
									<option value="zoom_bright" <?php selected( $settings['look_hover_effect'], 'zoom_bright' ); ?>>
										<?php esc_html_e( 'Zoom + brightness', 'woo-complete-the-look' ); ?>
									</option>
									<option value="fade" <?php selected( $settings['look_hover_effect'], 'fade' ); ?>>
										<?php esc_html_e( 'Soft fade', 'woo-complete-the-look' ); ?>
									</option>
									<option value="lift" <?php selected( $settings['look_hover_effect'], 'lift' ); ?>>
										<?php esc_html_e( 'Lift + shadow', 'woo-complete-the-look' ); ?>
									</option>
									<option value="colorize" <?php selected( $settings['look_hover_effect'], 'colorize' ); ?>>
										<?php esc_html_e( 'Grayscale → color', 'woo-complete-the-look' ); ?>
									</option>
									<option value="soft_pan" <?php selected( $settings['look_hover_effect'], 'soft_pan' ); ?>>
										<?php esc_html_e( 'Soft pan / ken burns', 'woo-complete-the-look' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Desktop only. No hover effects on mobile or tablet.', 'woo-complete-the-look' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="ctm-settings-card">
					<h2><?php esc_html_e( 'Display options', 'woo-complete-the-look' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Include main product', 'woo-complete-the-look' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[include_main_product]"
										value="yes"
										<?php checked( $settings['include_main_product'], 'yes' ); ?>
									/>
									<?php esc_html_e( 'Always show the current product first in the Complete the Look list', 'woo-complete-the-look' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Show checkboxes', 'woo-complete-the-look' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_checkboxes]"
										value="yes"
										<?php checked( $settings['show_checkboxes'], 'yes' ); ?>
									/>
									<?php esc_html_e( 'Allow customers to select products with checkboxes', 'woo-complete-the-look' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Checkboxes checked by default', 'woo-complete-the-look' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[checkboxes_default]"
										value="yes"
										<?php checked( $settings['checkboxes_default'], 'yes' ); ?>
									/>
									<?php esc_html_e( 'Pre-select all addable products', 'woo-complete-the-look' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Show prices', 'woo-complete-the-look' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_price]"
										value="yes"
										<?php checked( $settings['show_price'], 'yes' ); ?>
									/>
									<?php esc_html_e( 'Display product prices', 'woo-complete-the-look' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Show “Add selected” button', 'woo-complete-the-look' ); ?></th>
							<td>
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_add_button]"
										value="yes"
										<?php checked( $settings['show_add_button'], 'yes' ); ?>
									/>
									<?php esc_html_e( 'Show the multi-add button under the products', 'woo-complete-the-look' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Save changes', 'woo-complete-the-look' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Map display_position setting to WooCommerce hook + priority.
	 *
	 * @return array{hook:string,priority:int}
	 */
	public static function get_display_hook() {
		$position = self::get( 'display_position', 'after_add_to_cart' );

		$map = array(
			'after_add_to_cart' => array(
				'hook'     => 'woocommerce_after_add_to_cart_form',
				'priority' => 15,
			),
			'after_summary'     => array(
				'hook'     => 'woocommerce_after_single_product_summary',
				'priority' => 8,
			),
			'after_meta'        => array(
				'hook'     => 'woocommerce_product_meta_end',
				'priority' => 20,
			),
			'before_related'    => array(
				'hook'     => 'woocommerce_after_single_product_summary',
				'priority' => 14,
			),
		);

		return isset( $map[ $position ] ) ? $map[ $position ] : $map['after_add_to_cart'];
	}
}
