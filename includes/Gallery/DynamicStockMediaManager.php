<?php
declare( strict_types=1 );

namespace WcVariationStudio\Gallery;

use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DynamicStockMediaManager {

	public const CACHE_META_KEY      = '_wcvs_dynamic_media_cache';
	public const GALLERY_META_KEY    = '_wcvs_variation_gallery_ids';
	public const VIDEO_META_KEY      = '_wcvs_variation_video';
	public const OVERRIDE_ENABLE_KEY = '_wcvs_enable_dynamic_stock_media';

	/** @var array<int, bool> Recursion guard per product ID. */
	private array $resolving = [];

	public function register_hooks(): void {
		// Core WooCommerce product image & gallery getters (covers Shop Loop, Single Product, Cart, Schema, Feeds).
		add_filter( 'woocommerce_product_get_image_id', [ $this, 'filter_product_image_id' ], 20, 2 );
		add_filter( 'woocommerce_product_get_gallery_image_ids', [ $this, 'filter_product_gallery_ids' ], 20, 2 );

		// Write-time recalculation triggers on stock updates and product/variation saves.
		add_action( 'woocommerce_variation_set_stock', [ $this, 'on_variation_stock_changed' ], 10, 1 );
		add_action( 'woocommerce_variation_set_stock_status', [ $this, 'on_variation_stock_status_changed' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'on_variation_saved' ], 20, 2 );
		add_action( 'woocommerce_delete_product_variation', [ $this, 'on_variation_deleted' ], 10, 1 );
		add_action( 'woocommerce_update_product', [ $this, 'on_parent_product_updated' ], 20, 1 );
	}

	/**
	 * Determine whether Dynamic Stock Media is active for a given variable product.
	 */
	public function is_enabled_for_product( WC_Product $product ): bool {
		if ( ! $product->is_type( 'variable' ) ) {
			return false;
		}

		$product_override = $product->get_meta( self::OVERRIDE_ENABLE_KEY, true );
		if ( 'yes' === $product_override ) {
			return true;
		}
		if ( 'no' === $product_override ) {
			return false;
		}

		return 'yes' === get_option( 'wcvs_dynamic_stock_media_global', 'yes' );
	}

	/**
	 * Filter main product featured image ID ($product->get_image_id()).
	 *
	 * @param mixed      $image_id
	 * @param WC_Product $product
	 * @return mixed
	 */
	public function filter_product_image_id( $image_id, WC_Product $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $image_id;
		}

		$product_id = $product->get_id();
		if ( isset( $this->resolving[ $product_id ] ) || ! $this->is_enabled_for_product( $product ) ) {
			return $image_id;
		}

		$media = $this->get_resolved_media( $product );
		if ( ! empty( $media['main_id'] ) ) {
			return (int) $media['main_id'];
		}

		return $image_id;
	}

	/**
	 * Filter product gallery attachment IDs ($product->get_gallery_image_ids()).
	 *
	 * @param array<int, int> $gallery_ids
	 * @param WC_Product      $product
	 * @return array<int, int>
	 */
	public function filter_product_gallery_ids( array $gallery_ids, WC_Product $product ): array {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $gallery_ids;
		}

		$product_id = $product->get_id();
		if ( isset( $this->resolving[ $product_id ] ) || ! $this->is_enabled_for_product( $product ) ) {
			return $gallery_ids;
		}

		$media = $this->get_resolved_media( $product );
		if ( isset( $media['gallery_ids'] ) && is_array( $media['gallery_ids'] ) && ! empty( $media['gallery_ids'] ) ) {
			return array_map( 'intval', $media['gallery_ids'] );
		}

		return $gallery_ids;
	}

	/**
	 * Retrieve cached dynamic media map or compute & store on cache miss.
	 *
	 * @return array{
	 *   main_id: int,
	 *   gallery_ids: array<int, int>,
	 *   winning_variation_id: int,
	 *   image_attribute_map: array<int, array<string, string>>
	 * }
	 */
	public function get_resolved_media( WC_Product $product ): array {
		$product_id = $product->get_id();
		$cached     = get_post_meta( $product_id, self::CACHE_META_KEY, true );

		if ( is_array( $cached ) && isset( $cached['main_id'], $cached['gallery_ids'] ) ) {
			return $cached;
		}

		return $this->recalculate_and_cache( $product_id );
	}

	/**
	 * Compute highest-stock variation main image + deduplicated in-stock gallery.
	 *
	 * @return array{
	 *   main_id: int,
	 *   gallery_ids: array<int, int>,
	 *   winning_variation_id: int,
	 *   image_attribute_map: array<int, array<string, string>>
	 * }
	 */
	public function recalculate_and_cache( int $product_id ): array {
		$empty = [
			'main_id'              => 0,
			'gallery_ids'          => [],
			'winning_variation_id' => 0,
			'image_attribute_map'  => [],
		];

		if ( isset( $this->resolving[ $product_id ] ) ) {
			return $empty;
		}

		$this->resolving[ $product_id ] = true;

		try {
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product_Variable ) {
				return $empty;
			}

			$include_secondary_from_others = 'yes' === get_option( 'wcvs_dynamic_gallery_include_other_thumbs', 'no' );
			$unmanaged_policy              = sanitize_key( (string) get_option( 'wcvs_unmanaged_stock_priority', 'after_managed' ) );
			$allow_backorders              = 'yes' === get_option( 'wcvs_dynamic_include_backorders', 'no' );

			$variation_ids = $product->get_children();
			$candidates    = [];

			foreach ( $variation_ids as $vid ) {
				$variation = wc_get_product( $vid );
				if ( ! $variation instanceof WC_Product_Variation || 'publish' !== $variation->get_status() ) {
					continue;
				}

				$score = $this->calculate_variation_stock_score( $variation, $unmanaged_policy, $allow_backorders );
				if ( $score < 0 ) {
					// Out of stock -> strictly excluded.
					continue;
				}

				$primary_image_id = (int) $variation->get_image_id( 'edit' );
				$raw_gallery      = get_post_meta( $vid, self::GALLERY_META_KEY, true );
				$gallery_ids      = is_array( $raw_gallery ) ? array_values( array_filter( array_map( 'absint', $raw_gallery ) ) ) : [];

				// Skip variations that have neither a primary image nor gallery images.
				if ( $primary_image_id <= 0 && empty( $gallery_ids ) ) {
					continue;
				}

				$candidates[] = [
					'variation_id' => $vid,
					'score'        => $score,
					'menu_order'   => (int) $variation->get_menu_order(),
					'primary_id'   => $primary_image_id,
					'gallery_ids'  => $gallery_ids,
					'attributes'   => $variation->get_variation_attributes(),
				];
			}

			if ( empty( $candidates ) ) {
				// All variations out of stock or without images: fall back to raw parent meta.
				$fallback_main    = (int) get_post_meta( $product_id, '_thumbnail_id', true );
				$raw_parent_gal   = (string) get_post_meta( $product_id, '_product_image_gallery', true );
				$fallback_gallery = array_values( array_filter( array_map( 'absint', explode( ',', $raw_parent_gal ) ) ) );

				$result = [
					'main_id'              => $fallback_main,
					'gallery_ids'          => $fallback_gallery,
					'winning_variation_id' => 0,
					'image_attribute_map'  => [],
				];
				update_post_meta( $product_id, self::CACHE_META_KEY, $result );
				return $result;
			}

			// Sort descending by stock score, then ascending by menu_order, then ascending by variation_id.
			usort(
				$candidates,
				static function ( array $a, array $b ): int {
					if ( $a['score'] !== $b['score'] ) {
						return $b['score'] <=> $a['score'];
					}
					if ( $a['menu_order'] !== $b['menu_order'] ) {
						return $a['menu_order'] <=> $b['menu_order'];
					}
					return $a['variation_id'] <=> $b['variation_id'];
				}
			);

			$winner      = $candidates[0];
			$main_id     = $winner['primary_id'] > 0 ? $winner['primary_id'] : ( $winner['gallery_ids'][0] ?? 0 );
			$gallery_out = [];
			$attr_groups = [];

			// 1. Add winning variation's gallery images first (excluding main_id).
			foreach ( $winner['gallery_ids'] as $gid ) {
				if ( $gid > 0 && $gid !== $main_id ) {
					$gallery_out[ $gid ] = $gid;
				}
			}

			// 2. Append images from all other in-stock variations ordered by stock score.
			foreach ( $candidates as $index => $candidate ) {
				$cid = $candidate['primary_id'];
				if ( $cid > 0 ) {
					$attr_groups[ $cid ][] = $candidate['attributes'];
					if ( $cid !== $main_id ) {
						$gallery_out[ $cid ] = $cid;
					}
				}

				if ( $index > 0 && $include_secondary_from_others ) {
					foreach ( $candidate['gallery_ids'] as $gid ) {
						if ( $gid > 0 ) {
							$attr_groups[ $gid ][] = $candidate['attributes'];
							if ( $gid !== $main_id ) {
								$gallery_out[ $gid ] = $gid;
							}
						}
					}
				}
			}

			// Build unambiguous attribute hints per image (e.g., if all variations using #502 share pa_color=white).
			$image_attribute_map = $this->build_unambiguous_image_attributes( $attr_groups );

			$result = [
				'main_id'              => $main_id,
				'gallery_ids'          => array_values( $gallery_out ),
				'winning_variation_id' => $winner['variation_id'],
				'image_attribute_map'  => $image_attribute_map,
			];

			update_post_meta( $product_id, self::CACHE_META_KEY, $result );

			return $result;
		} finally {
			unset( $this->resolving[ $product_id ] );
		}
	}

	/**
	 * Score a variation's stock availability. Returns -1 when excluded/out of stock.
	 */
	private function calculate_variation_stock_score(
		WC_Product_Variation $variation,
		string $unmanaged_policy,
		bool $allow_backorders
	): int {
		if ( ! $variation->is_in_stock() ) {
			return -1;
		}

		if ( $variation->managing_stock() ) {
			$qty = (int) $variation->get_stock_quantity();
			if ( $qty > 0 ) {
				return $qty;
			}
			return ( $allow_backorders && $variation->backorders_allowed() ) ? 0 : -1;
		}

		// Stock is not numerically managed, and status is 'instock'.
		return 'first' === $unmanaged_policy ? 999999 : 1;
	}

	/**
	 * Find common attributes shared by all in-stock variations using a given attachment ID.
	 *
	 * @param array<int, array<int, array<string, string>>> $attr_groups
	 * @return array<int, array<string, string>>
	 */
	private function build_unambiguous_image_attributes( array $attr_groups ): array {
		$map = [];
		foreach ( $attr_groups as $attachment_id => $variation_attr_lists ) {
			if ( empty( $variation_attr_lists ) ) {
				continue;
			}
			$common = $variation_attr_lists[0];
			foreach ( $variation_attr_lists as $attrs ) {
				$common = array_intersect_assoc( $common, $attrs );
			}
			$common = array_filter( $common, static fn( string $v ): bool => '' !== $v );
			if ( ! empty( $common ) ) {
				$map[ $attachment_id ] = $common;
			}
		}
		return $map;
	}

	public function on_variation_stock_changed( WC_Product $product ): void {
		$parent_id = $product->get_parent_id();
		if ( $parent_id > 0 ) {
			$this->recalculate_and_cache( $parent_id );
		}
	}

	public function on_variation_stock_status_changed( int $variation_id, string $status, WC_Product $variation ): void {
		$parent_id = $variation->get_parent_id();
		if ( $parent_id > 0 ) {
			$this->recalculate_and_cache( $parent_id );
		}
	}

	public function on_variation_saved( int $variation_id, int $loop ): void {
		$parent_id = wp_get_post_parent_id( $variation_id );
		if ( $parent_id > 0 ) {
			$this->recalculate_and_cache( $parent_id );
		}
	}

	public function on_variation_deleted( int $variation_id ): void {
		$parent_id = wp_get_post_parent_id( $variation_id );
		if ( $parent_id > 0 ) {
			$this->recalculate_and_cache( $parent_id );
		}
	}

	public function on_parent_product_updated( int $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( $product instanceof WC_Product_Variable ) {
			$this->recalculate_and_cache( $product_id );
		}
	}
}
