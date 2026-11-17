<?php
declare( strict_types=1 );

namespace WcVariationStudio\Attributes;

use WC_Product_Attribute;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TaxonomyManager {

	public const CUSTOM_TYPES = [
		'wcvs_color'  => 'Color',
		'wcvs_image'  => 'Image',
		'wcvs_button' => 'Button / Label',
		'wcvs_radio'  => 'Radio',
	];

	public function register_hooks(): void {
		add_filter( 'product_attributes_type_selector', [ $this, 'register_attribute_types' ] );
		add_action( 'woocommerce_product_option_terms', [ $this, 'render_product_attribute_term_selector' ], 20, 3 );
	}

	/**
	 * Add swatch types to the Type dropdown on Products -> Attributes.
	 *
	 * @param array<string, string> $types
	 * @return array<string, string>
	 */
	public function register_attribute_types( array $types ): array {
		$types['wcvs_color']  = __( 'Color', 'wc-variation-studio' );
		$types['wcvs_image']  = __( 'Image', 'wc-variation-studio' );
		$types['wcvs_button'] = __( 'Button / Label', 'wc-variation-studio' );
		$types['wcvs_radio']  = __( 'Radio', 'wc-variation-studio' );

		return $types;
	}

	/**
	 * Render the standard WooCommerce term multi-select on Edit Product -> Attributes
	 * when an attribute uses one of our custom swatch types.
	 *
	 * @param object               $attribute_taxonomy
	 * @param int                  $i
	 * @param WC_Product_Attribute $attribute
	 */
	public function render_product_attribute_term_selector( object $attribute_taxonomy, int $i, WC_Product_Attribute $attribute ): void {
		$type = (string) ( $attribute_taxonomy->attribute_type ?? '' );
		if ( ! array_key_exists( $type, self::CUSTOM_TYPES ) ) {
			return;
		}

		$taxonomy = wc_attribute_taxonomy_name( (string) $attribute_taxonomy->attribute_name );
		$options  = $attribute->get_options();
		$orderby  = ! empty( $attribute_taxonomy->attribute_orderby ) ? (string) $attribute_taxonomy->attribute_orderby : 'name';
		?>
		<select multiple="multiple"
			data-placeholder="<?php esc_attr_e( 'Select terms', 'wc-variation-studio' ); ?>"
			data-orderby="<?php echo esc_attr( $orderby ); ?>"
			class="multiselect attribute_values wc-taxonomy-term-search"
			name="attribute_values[<?php echo esc_attr( (string) $i ); ?>][]"
			data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
			<?php
			$selected_terms = ! empty( $options )
				? get_terms(
					[
						'taxonomy'   => $taxonomy,
						'include'    => array_map( 'absint', $options ),
						'hide_empty' => false,
					]
				)
				: [];

			if ( is_array( $selected_terms ) ) {
				foreach ( $selected_terms as $term ) {
					printf(
						'<option value="%1$s" selected="selected">%2$s</option>',
						esc_attr( (string) $term->term_id ),
						esc_html( $term->name )
					);
				}
			}
			?>
		</select>
		<button type="button" class="button plus select_all_attributes"><?php esc_html_e( 'Select all', 'wc-variation-studio' ); ?></button>
		<button type="button" class="button minus select_no_attributes"><?php esc_html_e( 'Select none', 'wc-variation-studio' ); ?></button>
		<button type="button" class="button fr plus add_new_attribute"><?php esc_html_e( 'Add new', 'wc-variation-studio' ); ?></button>
		<?php
	}
}
