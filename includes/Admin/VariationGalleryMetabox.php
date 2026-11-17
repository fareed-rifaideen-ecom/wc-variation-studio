<?php
declare( strict_types=1 );

namespace WcVariationStudio\Admin;

use WcVariationStudio\Gallery\DynamicStockMediaManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VariationGalleryMetabox {

	public function __construct( private DynamicStockMediaManager $stock_media ) {}

	public function register_hooks(): void {
		add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'render_variation_fields' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_fields' ], 10, 2 );
	}

	/**
	 * @param int                  $loop
	 * @param array<string, mixed> $variation_data
	 * @param \WP_Post             $variation
	 */
	public function render_variation_fields( int $loop, array $variation_data, \WP_Post $variation ): void {
		$variation_id = (int) $variation->ID;
		$raw_gallery  = get_post_meta( $variation_id, DynamicStockMediaManager::GALLERY_META_KEY, true );
		$gallery_ids  = is_array( $raw_gallery ) ? array_values( array_filter( array_map( 'absint', $raw_gallery ) ) ) : [];
		$video        = get_post_meta( $variation_id, DynamicStockMediaManager::VIDEO_META_KEY, true );
		$video_url    = is_array( $video ) ? (string) ( $video['url'] ?? '' ) : '';
		?>
		<div class="form-row form-row-full wcvs-variation-gallery-box" data-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>">
			<?php wp_nonce_field( 'wcvs_save_variation_' . $variation_id, 'wcvs_variation_nonce_' . $variation_id ); ?>
			<p class="form-field">
				<label><strong><?php esc_html_e( 'Variation Gallery Images (Dynamic Stock Pool)', 'wc-variation-studio' ); ?></strong></label>
				<span class="description"><?php esc_html_e( 'Images uploaded here automatically populate the product gallery when this variation is in stock.', 'wc-variation-studio' ); ?></span>
			</p>
			<ul class="wcvs-admin-gallery-list">
				<?php foreach ( $gallery_ids as $attachment_id ) : ?>
					<?php $thumb = wp_get_attachment_image_url( $attachment_id, 'thumbnail' ); ?>
					<?php if ( $thumb ) : ?>
						<li class="wcvs-admin-gallery-item" data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
							<img src="<?php echo esc_url( $thumb ); ?>" alt="" />
							<button type="button" class="wcvs-remove-gallery-img" aria-label="<?php esc_attr_e( 'Remove image', 'wc-variation-studio' ); ?>">&times;</button>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<input type="hidden"
				class="wcvs-gallery-ids-input"
				name="wcvs_variation_gallery_ids[<?php echo esc_attr( (string) $variation_id ); ?>]"
				value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>" />
			<p>
				<button type="button" class="button wcvs-add-variation-images">
					<?php esc_html_e( 'Add Variation Gallery Images', 'wc-variation-studio' ); ?>
				</button>
			</p>
			<p class="form-field">
				<label for="wcvs_video_<?php echo esc_attr( (string) $variation_id ); ?>">
					<?php esc_html_e( 'Variation Video URL (YouTube, Vimeo, or MP4)', 'wc-variation-studio' ); ?>
				</label>
				<input type="url"
					id="wcvs_video_<?php echo esc_attr( (string) $variation_id ); ?>"
					name="wcvs_variation_video_url[<?php echo esc_attr( (string) $variation_id ); ?>]"
					value="<?php echo esc_url( $video_url ); ?>"
					placeholder="https://www.youtube.com/watch?v=..."
					class="short" />
			</p>
		</div>
		<?php
	}

	public function save_variation_fields( int $variation_id, int $loop ): void {
		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		$nonce_key = 'wcvs_variation_nonce_' . $variation_id;
		$nonce     = isset( $_POST[ $nonce_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wcvs_save_variation_' . $variation_id ) ) {
			return;
		}

		$raw_ids = isset( $_POST['wcvs_variation_gallery_ids'][ $variation_id ] )
			? sanitize_text_field( wp_unslash( $_POST['wcvs_variation_gallery_ids'][ $variation_id ] ) )
			: '';

		$ids = '' !== $raw_ids
			? array_values( array_filter( array_map( 'absint', explode( ',', $raw_ids ) ) ) )
			: [];

		update_post_meta( $variation_id, DynamicStockMediaManager::GALLERY_META_KEY, $ids );

		$raw_video = isset( $_POST['wcvs_variation_video_url'][ $variation_id ] )
			? esc_url_raw( wp_unslash( $_POST['wcvs_variation_video_url'][ $variation_id ] ) )
			: '';

		if ( '' !== $raw_video && $this->is_allowed_video_url( $raw_video ) ) {
			$provider = str_contains( $raw_video, 'vimeo.com' )
				? 'vimeo'
				: ( ( str_contains( $raw_video, 'youtube.com' ) || str_contains( $raw_video, 'youtu.be' ) ) ? 'youtube' : 'self' );

			update_post_meta(
				$variation_id,
				DynamicStockMediaManager::VIDEO_META_KEY,
				[
					'type' => $provider,
					'url'  => $raw_video,
				]
			);
		} else {
			delete_post_meta( $variation_id, DynamicStockMediaManager::VIDEO_META_KEY );
		}

		$parent_id = wp_get_post_parent_id( $variation_id );
		if ( $parent_id > 0 ) {
			$this->stock_media->recalculate_and_cache( $parent_id );
		}
	}

	private function is_allowed_video_url( string $url ): bool {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$site = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$allowed_hosts = [ 'youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com', $site ];

		return in_array( strtolower( $host ), array_map( 'strtolower', $allowed_hosts ), true );
	}
}
