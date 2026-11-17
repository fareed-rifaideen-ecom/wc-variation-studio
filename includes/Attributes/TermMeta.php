<?php
declare( strict_types=1 );

namespace WcVariationStudio\Attributes;

use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TermMeta {

	public const META_COLOR_PRIMARY   = '_wcvs_color_primary';
	public const META_COLOR_SECONDARY = '_wcvs_color_secondary';
	public const META_IMAGE_ID        = '_wcvs_image_id';

	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'bind_attribute_taxonomy_hooks' ] );
	}

	public function bind_attribute_taxonomy_hooks(): void {
		$taxonomies = wc_get_attribute_taxonomies();
		if ( empty( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $tax ) {
			$type = (string) $tax->attribute_type;
			if ( ! in_array( $type, [ 'wcvs_color', 'color', 'wcvs_image', 'image' ], true ) ) {
				continue;
			}

			$taxonomy = wc_attribute_taxonomy_name( (string) $tax->attribute_name );

			if ( in_array( $type, [ 'wcvs_color', 'color' ], true ) ) {
				add_action( "{$taxonomy}_add_form_fields", [ $this, 'render_add_color_fields' ] );
				add_action( "{$taxonomy}_edit_form_fields", [ $this, 'render_edit_color_fields' ], 10, 1 );
			} else {
				add_action( "{$taxonomy}_add_form_fields", [ $this, 'render_add_image_field' ] );
				add_action( "{$taxonomy}_edit_form_fields", [ $this, 'render_edit_image_field' ], 10, 1 );
			}

			add_action( "created_{$taxonomy}", [ $this, 'save_term_meta' ], 10, 1 );
			add_action( "edited_{$taxonomy}", [ $this, 'save_term_meta' ], 10, 1 );

			add_filter( "manage_edit-{$taxonomy}_columns", [ $this, 'add_preview_column' ] );
			add_filter( "manage_{$taxonomy}_custom_column", [ $this, 'render_preview_column' ], 10, 3 );
		}
	}

	public function render_add_color_fields(): void {
		wp_nonce_field( 'wcvs_save_term_meta', 'wcvs_term_meta_nonce' );
		?>
		<div class="form-field wcvs-term-field">
			<label for="wcvs_color_primary"><?php esc_html_e( 'Primary Swatch Color', 'wc-variation-studio' ); ?></label>
			<input type="text" id="wcvs_color_primary" name="wcvs_color_primary" class="wcvs-color-picker" value="" />
			<p class="description"><?php esc_html_e( 'Choose the main color displayed on the swatch.', 'wc-variation-studio' ); ?></p>
		</div>
		<div class="form-field wcvs-term-field">
			<label for="wcvs_color_secondary"><?php esc_html_e( 'Secondary Swatch Color (Optional Dual-Tone)', 'wc-variation-studio' ); ?></label>
			<input type="text" id="wcvs_color_secondary" name="wcvs_color_secondary" class="wcvs-color-picker" value="" />
			<p class="description"><?php esc_html_e( 'Optional second color for diagonal two-tone split swatches.', 'wc-variation-studio' ); ?></p>
		</div>
		<?php
	}

	public function render_edit_color_fields( WP_Term $term ): void {
		$primary   = (string) sanitize_hex_color( (string) get_term_meta( $term->term_id, self::META_COLOR_PRIMARY, true ) );
		$secondary = (string) sanitize_hex_color( (string) get_term_meta( $term->term_id, self::META_COLOR_SECONDARY, true ) );
		wp_nonce_field( 'wcvs_save_term_meta', 'wcvs_term_meta_nonce' );
		?>
		<tr class="form-field wcvs-term-field">
			<th scope="row"><label for="wcvs_color_primary"><?php esc_html_e( 'Primary Swatch Color', 'wc-variation-studio' ); ?></label></th>
			<td>
				<input type="text" id="wcvs_color_primary" name="wcvs_color_primary" class="wcvs-color-picker" value="<?php echo esc_attr( $primary ); ?>" />
			</td>
		</tr>
		<tr class="form-field wcvs-term-field">
			<th scope="row"><label for="wcvs_color_secondary"><?php esc_html_e( 'Secondary Swatch Color (Optional Dual-Tone)', 'wc-variation-studio' ); ?></label></th>
			<td>
				<input type="text" id="wcvs_color_secondary" name="wcvs_color_secondary" class="wcvs-color-picker" value="<?php echo esc_attr( $secondary ); ?>" />
			</td>
		</tr>
		<?php
	}

	public function render_add_image_field(): void {
		wp_nonce_field( 'wcvs_save_term_meta', 'wcvs_term_meta_nonce' );
		?>
		<div class="form-field wcvs-term-field wcvs-term-image-wrap">
			<label><?php esc_html_e( 'Swatch Image', 'wc-variation-studio' ); ?></label>
			<div class="wcvs-term-image-preview" style="margin-bottom:8px;"></div>
			<input type="hidden" name="wcvs_image_id" class="wcvs-term-image-id" value="" />
			<button type="button" class="button wcvs-upload-term-image"><?php esc_html_e( 'Upload / Choose Image', 'wc-variation-studio' ); ?></button>
			<button type="button" class="button wcvs-remove-term-image" style="display:none;"><?php esc_html_e( 'Remove', 'wc-variation-studio' ); ?></button>
		</div>
		<?php
	}

	public function render_edit_image_field( WP_Term $term ): void {
		$image_id = absint( get_term_meta( $term->term_id, self::META_IMAGE_ID, true ) );
		$thumb    = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		wp_nonce_field( 'wcvs_save_term_meta', 'wcvs_term_meta_nonce' );
		?>
		<tr class="form-field wcvs-term-field wcvs-term-image-wrap">
			<th scope="row"><label><?php esc_html_e( 'Swatch Image', 'wc-variation-studio' ); ?></label></th>
			<td>
				<div class="wcvs-term-image-preview" style="margin-bottom:8px;">
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:4px;" />
					<?php endif; ?>
				</div>
				<input type="hidden" name="wcvs_image_id" class="wcvs-term-image-id" value="<?php echo esc_attr( $image_id > 0 ? (string) $image_id : '' ); ?>" />
				<button type="button" class="button wcvs-upload-term-image"><?php esc_html_e( 'Upload / Choose Image', 'wc-variation-studio' ); ?></button>
				<button type="button" class="button wcvs-remove-term-image" <?php echo $thumb ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'wc-variation-studio' ); ?></button>
			</td>
		</tr>
		<?php
	}

	public function save_term_meta( int $term_id ): void {
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		$nonce = isset( $_POST['wcvs_term_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wcvs_term_meta_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wcvs_save_term_meta' ) ) {
			return;
		}

		if ( isset( $_POST['wcvs_color_primary'] ) ) {
			$primary = sanitize_hex_color( wp_unslash( (string) $_POST['wcvs_color_primary'] ) );
			if ( $primary ) {
				update_term_meta( $term_id, self::META_COLOR_PRIMARY, $primary );
			} else {
				delete_term_meta( $term_id, self::META_COLOR_PRIMARY );
			}
		}

		if ( isset( $_POST['wcvs_color_secondary'] ) ) {
			$secondary = sanitize_hex_color( wp_unslash( (string) $_POST['wcvs_color_secondary'] ) );
			if ( $secondary ) {
				update_term_meta( $term_id, self::META_COLOR_SECONDARY, $secondary );
			} else {
				delete_term_meta( $term_id, self::META_COLOR_SECONDARY );
			}
		}

		if ( isset( $_POST['wcvs_image_id'] ) ) {
			$image_id = absint( $_POST['wcvs_image_id'] );
			if ( $image_id > 0 ) {
				update_term_meta( $term_id, self::META_IMAGE_ID, $image_id );
			} else {
				delete_term_meta( $term_id, self::META_IMAGE_ID );
			}
		}
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function add_preview_column( array $columns ): array {
		$updated = [];
		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$updated['wcvs_swatch'] = __( 'Swatch', 'wc-variation-studio' );
			}
			$updated[ $key ] = $label;
		}
		return $updated;
	}

	public function render_preview_column( string $content, string $column_name, int $term_id ): string {
		if ( 'wcvs_swatch' !== $column_name ) {
			return $content;
		}

		$image_id = absint( get_term_meta( $term_id, self::META_IMAGE_ID, true ) );
		if ( $image_id > 0 ) {
			$thumb = wp_get_attachment_image_url( $image_id, 'thumbnail' );
			if ( $thumb ) {
				return sprintf(
					'<span style="display:inline-block;width:28px;height:28px;border-radius:4px;overflow:hidden;border:1px solid #ccd0d4;"><img src="%s" alt="" style="width:100%%;height:100%%;object-fit:cover;" /></span>',
					esc_url( $thumb )
				);
			}
		}

		$c1 = (string) sanitize_hex_color( (string) get_term_meta( $term_id, self::META_COLOR_PRIMARY, true ) );
		$c2 = (string) sanitize_hex_color( (string) get_term_meta( $term_id, self::META_COLOR_SECONDARY, true ) );
		if ( '' !== $c1 ) {
			$bg = '' !== $c2
				? sprintf( 'linear-gradient(135deg, %1$s 50%%, %2$s 50%%)', esc_attr( $c1 ), esc_attr( $c2 ) )
				: esc_attr( $c1 );

			return sprintf(
				'<span style="display:inline-block;width:28px;height:28px;border-radius:4px;border:1px solid #ccd0d4;background:%s;"></span>',
				$bg
			);
		}

		return '&mdash;';
	}
}
