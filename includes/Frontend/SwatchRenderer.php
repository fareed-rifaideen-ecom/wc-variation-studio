<?php
declare( strict_types=1 );

namespace WcVariationStudio\Frontend;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SwatchRenderer {

	public function register_hooks(): void {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', [ $this, 'render_swatches_html' ], 20, 2 );
	}

	/**
	 * @param string               $html
	 * @param array<string, mixed> $args
	 */
	public function render_swatches_html( string $html, array $args ): string {
		$product   = $args['product'] ?? null;
		$attribute = (string) ( $args['attribute'] ?? '' );
		$options   = (array) ( $args['options'] ?? [] );
		$selected  = (string) ( $args['selected'] ?? '' );

		if ( ! $product instanceof WC_Product || '' === $attribute || empty( $options ) ) {
			return $html;
		}

		$taxonomy = taxonomy_exists( $attribute ) ? $attribute : '';
		$type     = $this->resolve_attribute_swatch_type( $taxonomy );
		$shape    = sanitize_key( (string) get_option( 'wcvs_swatch_shape', 'rounded' ) );

		if ( 'select' === $type ) {
			return $html;
		}

		$terms = $taxonomy
			? wc_get_product_terms( $product->get_id(), $taxonomy, [ 'fields' => 'all' ] )
			: [];

		$term_map = [];
		foreach ( $terms as $term ) {
			$term_map[ $term->slug ] = $term;
		}

		ob_start();
		?>
		<div class="wcvs-swatch-wrapper wcvs-shape-<?php echo esc_attr( $shape ); ?> wcvs-type-<?php echo esc_attr( $type ); ?>"
			data-attribute-name="<?php echo esc_attr( 'attribute_' . sanitize_title( $attribute ) ); ?>">
			<div class="wcvs-hidden-select"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core WC dropdown HTML. ?></div>
			<ul class="wcvs-swatch-list" role="radiogroup" aria-label="<?php echo esc_attr( wc_attribute_label( $attribute, $product ) ); ?>">
				<?php foreach ( $options as $option_value ) : ?>
					<?php
					$term_obj   = $term_map[ $option_value ] ?? null;
					$label      = $term_obj ? $term_obj->name : $option_value;
					$is_checked = sanitize_title( $selected ) === sanitize_title( $option_value );
					$meta       = $term_obj ? $this->get_term_visual_meta( (int) $term_obj->term_id ) : [];
					?>
					<li class="wcvs-swatch-item <?php echo $is_checked ? 'is-selected' : ''; ?>"
						role="radio"
						tabindex="0"
						aria-checked="<?php echo $is_checked ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( $label ); ?>"
						data-value="<?php echo esc_attr( $option_value ); ?>"
						data-tooltip="<?php echo esc_attr( $label ); ?>">
						<?php echo $this->render_swatch_inner( $type, $label, $meta ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside helper. ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function resolve_attribute_swatch_type( string $taxonomy ): string {
		if ( '' === $taxonomy ) {
			return 'button';
		}

		$attr_taxonomies = wc_get_attribute_taxonomies();
		$attr_name       = wc_attribute_taxonomy_slug( $taxonomy );

		foreach ( $attr_taxonomies as $tax ) {
			if ( $tax->attribute_name === $attr_name ) {
				return match ( $tax->attribute_type ) {
					'color', 'wcvs_color'   => 'color',
					'image', 'wcvs_image'   => 'image',
					'radio', 'wcvs_radio'   => 'radio',
					'button', 'wcvs_button' => 'button',
					default                 => sanitize_key( (string) get_option( 'wcvs_default_dropdown_to_button', 'button' ) ),
				};
			}
		}

		return 'button';
	}

	/**
	 * @return array{color1: string, color2: string, image_id: int}
	 */
	private function get_term_visual_meta( int $term_id ): array {
		return [
			'color1'   => (string) sanitize_hex_color( (string) get_term_meta( $term_id, '_wcvs_color_primary', true ) ),
			'color2'   => (string) sanitize_hex_color( (string) get_term_meta( $term_id, '_wcvs_color_secondary', true ) ),
			'image_id' => absint( get_term_meta( $term_id, '_wcvs_image_id', true ) ),
		];
	}

	/**
	 * @param array{color1: string, color2: string, image_id: int} $meta
	 */
	private function render_swatch_inner( string $type, string $label, array $meta ): string {
		if ( 'color' === $type && '' !== $meta['color1'] ) {
			$bg = '' !== $meta['color2']
				? sprintf( 'linear-gradient(135deg, %1$s 50%%, %2$s 50%%)', esc_attr( $meta['color1'] ), esc_attr( $meta['color2'] ) )
				: esc_attr( $meta['color1'] );

			return sprintf(
				'<span class="wcvs-swatch-color" style="background:%s;"></span><span class="screen-reader-text">%s</span>',
				$bg,
				esc_html( $label )
			);
		}

		if ( 'image' === $type && $meta['image_id'] > 0 ) {
			$img = wp_get_attachment_image(
				$meta['image_id'],
				'woocommerce_gallery_thumbnail',
				false,
				[ 'class' => 'wcvs-swatch-img', 'alt' => $label ]
			);
			if ( '' !== $img ) {
				return $img;
			}
		}

		if ( 'radio' === $type ) {
			return sprintf(
				'<span class="wcvs-radio-dot"></span><span class="wcvs-radio-label">%s</span>',
				esc_html( $label )
			);
		}

		return sprintf( '<span class="wcvs-swatch-label">%s</span>', esc_html( $label ) );
	}
}
