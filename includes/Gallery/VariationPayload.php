<?php
declare( strict_types=1 );

namespace WcVariationStudio\Gallery;

use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VariationPayload {

	public function __construct( private DynamicStockMediaManager $stock_media ) {}

	public function register_hooks(): void {
		add_filter( 'woocommerce_available_variation', [ $this, 'attach_variation_gallery_data' ], 15, 3 );
		add_action( 'woocommerce_before_single_product_summary', [ $this, 'output_initial_gallery_metadata' ], 15 );
	}

	/**
	 * Attach variation-specific gallery images & video data ONLY on single product or explicit variation AJAX requests.
	 *
	 * @param array<string, mixed> $data
	 * @param WC_Product_Variable  $product
	 * @param WC_Product_Variation $variation
	 * @return array<string, mixed>
	 */
	public function attach_variation_gallery_data( array $data, WC_Product_Variable $product, WC_Product_Variation $variation ): array {
		if ( ! is_product() && ! wp_doing_ajax() ) {
			return $data;
		}

		$variation_id = $variation->get_id();
		$primary_id   = (int) $variation->get_image_id( 'edit' );
		$raw_gallery  = get_post_meta( $variation_id, DynamicStockMediaManager::GALLERY_META_KEY, true );
		$gallery_ids  = is_array( $raw_gallery ) ? array_values( array_filter( array_map( 'absint', $raw_gallery ) ) ) : [];

		$ordered_ids = [];
		if ( $primary_id > 0 ) {
			$ordered_ids[] = $primary_id;
		}
		foreach ( $gallery_ids as $gid ) {
			if ( $gid > 0 && $gid !== $primary_id ) {
				$ordered_ids[] = $gid;
			}
		}

		$slides = [];
		foreach ( $ordered_ids as $attachment_id ) {
			$slide = $this->build_image_slide( $attachment_id );
			if ( null !== $slide ) {
				$slides[] = $slide;
			}
		}

		$video = get_post_meta( $variation_id, DynamicStockMediaManager::VIDEO_META_KEY, true );
		if ( is_array( $video ) && ! empty( $video['url'] ) ) {
			$video_slide = $this->build_video_slide( $video );
			if ( null !== $video_slide ) {
				$slides[] = $video_slide;
			}
		}

		$data['wcvs_gallery'] = $slides;
		return $data;
	}

	/**
	 * Output JSON map of dynamic gallery slides + image-to-attribute hints on single product pages.
	 */
	public function output_initial_gallery_metadata(): void {
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		$resolved = $this->stock_media->get_resolved_media( $product );
		$all_ids  = [];

		if ( ! empty( $resolved['main_id'] ) ) {
			$all_ids[] = (int) $resolved['main_id'];
		}
		foreach ( $resolved['gallery_ids'] as $gid ) {
			if ( $gid > 0 && ! in_array( $gid, $all_ids, true ) ) {
				$all_ids[] = (int) $gid;
			}
		}

		$initial_slides = [];
		foreach ( $all_ids as $attachment_id ) {
			$slide = $this->build_image_slide( $attachment_id );
			if ( null !== $slide ) {
				$slide['match_attributes'] = $resolved['image_attribute_map'][ $attachment_id ] ?? new \stdClass();
				$initial_slides[]          = $slide;
			}
		}

		printf(
			'<script type="application/json" id="wcvs-initial-gallery-data">%s</script>',
			wp_json_encode(
				[
					'product_id'           => $product->get_id(),
					'winning_variation_id' => $resolved['winning_variation_id'],
					'slides'               => $initial_slides,
				]
			)
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function build_image_slide( int $attachment_id ): ?array {
		$full = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! $full ) {
			return null;
		}

		$single = wp_get_attachment_image_src( $attachment_id, 'woocommerce_single' ) ?: $full;
		$thumb  = wp_get_attachment_image_src( $attachment_id, 'woocommerce_gallery_thumbnail' ) ?: $single;
		$alt    = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

		return [
			'type'       => 'image',
			'id'         => $attachment_id,
			'src'        => esc_url_raw( $single[0] ),
			'width'      => (int) $single[1],
			'height'     => (int) $single[2],
			'full_src'   => esc_url_raw( $full[0] ),
			'full_w'     => (int) $full[1],
			'full_h'     => (int) $full[2],
			'thumb_src'  => esc_url_raw( $thumb[0] ),
			'srcset'     => (string) wp_get_attachment_image_srcset( $attachment_id, 'woocommerce_single' ),
			'sizes'      => (string) wp_get_attachment_image_sizes( $attachment_id, 'woocommerce_single' ),
			'alt'        => $alt ?: get_the_title( $attachment_id ),
		];
	}

	/**
	 * @param array<string, mixed> $video
	 * @return array<string, mixed>|null
	 */
	private function build_video_slide( array $video ): ?array {
		$url  = esc_url_raw( (string) ( $video['url'] ?? '' ) );
		$type = sanitize_key( (string) ( $video['type'] ?? 'self' ) );
		if ( '' === $url ) {
			return null;
		}

		$poster_id  = absint( $video['poster_id'] ?? 0 );
		$poster_src = $poster_id > 0 ? wp_get_attachment_image_url( $poster_id, 'woocommerce_single' ) : '';
		$thumb_src  = $poster_id > 0 ? wp_get_attachment_image_url( $poster_id, 'woocommerce_gallery_thumbnail' ) : '';

		return [
			'type'       => 'video',
			'provider'   => $type,
			'video_url'  => $url,
			'poster_src' => $poster_src ? esc_url_raw( $poster_src ) : '',
			'thumb_src'  => $thumb_src ? esc_url_raw( $thumb_src ) : '',
		];
	}
}
