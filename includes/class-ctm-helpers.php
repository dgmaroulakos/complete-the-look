<?php
/**
 * Shared helpers for Complete the Look.
 *
 * @package WooCompleteTheLook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Utility helpers shared by admin and frontend.
 */
class CTM_Helpers {

	/**
	 * Detect color-like attributes (color / colour / χρώμα).
	 *
	 * @param string $attribute_name Attribute taxonomy or name.
	 * @param string $label          Attribute label.
	 * @return bool
	 */
	public static function is_color_attribute( $attribute_name, $label = '' ) {
		$slug = sanitize_title( $attribute_name );
		$slug = preg_replace( '/^pa_/', '', $slug );
		$hay  = strtolower( $slug . ' ' . $label );

		$needles = apply_filters(
			'ctm_color_attribute_needles',
			array( 'color', 'colour', 'xroma', 'chroma', 'χρωμα', 'χρώμα' )
		);

		$hay_plain = function_exists( 'remove_accents' ) ? remove_accents( $hay ) : $hay;

		foreach ( $needles as $needle ) {
			$needle       = (string) $needle;
			$needle_plain = function_exists( 'remove_accents' ) ? remove_accents( $needle ) : $needle;

			if ( false !== stripos( $hay, $needle ) || false !== stripos( $hay_plain, $needle_plain ) ) {
				return true;
			}
		}

		return (bool) apply_filters( 'ctm_is_color_attribute', false, $attribute_name, $label );
	}

	/**
	 * Find the color attribute key on a variable product.
	 *
	 * @param WC_Product $product Product object.
	 * @return string Attribute name (e.g. pa_color) or empty.
	 */
	public static function get_color_attribute_name( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			return $parent ? self::get_color_attribute_name( $parent ) : '';
		}

		if ( ! $product->is_type( 'variable' ) ) {
			return '';
		}

		$attributes = $product->get_variation_attributes();

		foreach ( $attributes as $attribute_name => $options ) {
			$label = wc_attribute_label( $attribute_name, $product );
			if ( self::is_color_attribute( $attribute_name, $label ) ) {
				return $attribute_name;
			}
		}

		return '';
	}

	/**
	 * Color options for a variable product: slug => label.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	public static function get_color_options( $product ) {
		$attribute_name = self::get_color_attribute_name( $product );

		if ( ! $attribute_name || ! $product->is_type( 'variable' ) ) {
			return array();
		}

		$attributes = $product->get_variation_attributes();
		$options    = isset( $attributes[ $attribute_name ] ) ? (array) $attributes[ $attribute_name ] : array();
		$output     = array();

		foreach ( $options as $option ) {
			$label = $option;

			if ( taxonomy_exists( $attribute_name ) ) {
				$term = get_term_by( 'slug', $option, $attribute_name );
				if ( $term && ! is_wp_error( $term ) ) {
					$label = $term->name;
				}
			}

			$output[ $option ] = $label;
		}

		return $output;
	}

	/**
	 * Resolve a hex/color value from attribute term meta when available.
	 *
	 * Prefers WooCommerce native visual attribute color meta (`color`),
	 * matching the theme/product attribute swatches.
	 *
	 * @param WP_Term $term Attribute term.
	 * @return string
	 */
	public static function get_term_color_value( $term ) {
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		// WooCommerce native visual attributes (Color / image type).
		if ( class_exists( '\Automattic\WooCommerce\Internal\ProductAttributes\VisualAttributeTermMeta' ) ) {
			$visual = \Automattic\WooCommerce\Internal\ProductAttributes\VisualAttributeTermMeta::get_term_visual( (int) $term->term_id );

			if ( ! empty( $visual['type'] ) && 'color' === $visual['type'] && ! empty( $visual['value'] ) ) {
				$hex = sanitize_hex_color( $visual['value'] );
				if ( $hex ) {
					return $hex;
				}
			}
		}

		$meta_keys = apply_filters(
			'ctm_color_term_meta_keys',
			array(
				'color',
				'product_attribute_color',
				'cfvsw_color',
				'wt_bg_color',
				'attribute_color',
			)
		);

		foreach ( $meta_keys as $meta_key ) {
			$value = get_term_meta( $term->term_id, $meta_key, true );
			$hex   = is_string( $value ) ? sanitize_hex_color( trim( $value ) ) : '';
			if ( $hex ) {
				return $hex;
			}
		}

		$named     = sanitize_title( $term->slug );
		$css_named = array(
			'black'  => '#111111',
			'white'  => '#ffffff',
			'red'    => '#c62828',
			'blue'   => '#1565c0',
			'green'  => '#2e7d32',
			'navy'   => '#1a237e',
			'beige'  => '#d7ccc8',
			'brown'  => '#6d4c41',
			'grey'   => '#9e9e9e',
			'gray'   => '#9e9e9e',
			'pink'   => '#ec407a',
			'purple' => '#7b1fa2',
			'yellow' => '#fbc02d',
			'orange' => '#ef6c00',
			'cream'  => '#fff8e1',
			'khaki'  => '#c3b091',
			'gold'   => '#c9a227',
			'silver' => '#b0bec5',
		);

		return isset( $css_named[ $named ] ) ? $css_named[ $named ] : '';
	}

	/**
	 * Sanitize color presets meta structure.
	 *
	 * @param mixed $raw Raw presets.
	 * @return array
	 */
	public static function sanitize_color_presets( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();

		foreach ( $raw as $color_slug => $preset ) {
			$color_slug = sanitize_title( (string) $color_slug );

			if ( '' === $color_slug || ! is_array( $preset ) ) {
				continue;
			}

			$defaults = array();
			if ( ! empty( $preset['defaults'] ) && is_array( $preset['defaults'] ) ) {
				foreach ( $preset['defaults'] as $product_id => $option_slug ) {
					$product_id  = absint( $product_id );
					$option_slug = sanitize_title( (string) $option_slug );
					if ( $product_id && '' !== $option_slug ) {
						$defaults[ (string) $product_id ] = $option_slug;
					}
				}
			}

			$image_id = isset( $preset['image_id'] ) ? absint( $preset['image_id'] ) : 0;
			if ( $image_id && ! wp_attachment_is_image( $image_id ) ) {
				$image_id = 0;
			}

			$clean[ $color_slug ] = array(
				'image_id' => $image_id,
				'defaults' => $defaults,
			);
		}

		return $clean;
	}
}
