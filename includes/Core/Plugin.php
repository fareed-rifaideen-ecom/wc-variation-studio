<?php
declare( strict_types=1 );

namespace WcVariationStudio\Core;

use WcVariationStudio\Admin\SettingsPage;
use WcVariationStudio\Admin\VariationGalleryMetabox;
use WcVariationStudio\Attributes\TaxonomyManager;
use WcVariationStudio\Attributes\TermMeta;
use WcVariationStudio\Frontend\SwatchRenderer;
use WcVariationStudio\Gallery\DynamicStockMediaManager;
use WcVariationStudio\Gallery\VariationPayload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?self $instance = null;

	private bool $initialized = false;

	public DynamicStockMediaManager $stock_media;
	public VariationPayload $variation_payload;
	public SwatchRenderer $swatch_renderer;
	public TaxonomyManager $taxonomy_manager;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->stock_media       = new DynamicStockMediaManager();
		$this->variation_payload = new VariationPayload( $this->stock_media );
		$this->swatch_renderer   = new SwatchRenderer();
		$this->taxonomy_manager  = new TaxonomyManager();
	}

	public function init(): void {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		$this->stock_media->register_hooks();
		$this->variation_payload->register_hooks();
		$this->swatch_renderer->register_hooks();
		$this->taxonomy_manager->register_hooks();

		if ( is_admin() ) {
			( new TermMeta() )->register_hooks();
			( new VariationGalleryMetabox( $this->stock_media ) )->register_hooks();

			add_filter(
				'woocommerce_get_settings_pages',
				static function ( array $settings ): array {
					$settings[] = new SettingsPage();
					return $settings;
				}
			);
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	public function enqueue_frontend_assets(): void {
		if ( ! $this->should_enqueue_frontend_assets() ) {
			return;
		}

		wp_enqueue_style(
			'wcvs-frontend',
			WCVS_PLUGIN_URL . 'assets/css/frontend-studio.css',
			[],
			WCVS_VERSION
		);

		wp_enqueue_script(
			'wcvs-frontend',
			WCVS_PLUGIN_URL . 'assets/js/frontend-studio.js',
			[ 'jquery', 'wc-add-to-cart-variation' ],
			WCVS_VERSION,
			true
		);

		$frontend_config = [
			'reverseGallerySync' => 'yes' === get_option( 'wcvs_reverse_gallery_sync', 'yes' ),
			'urlDeepLink'        => 'yes' === get_option( 'wcvs_url_deep_link', 'yes' ),
			'outOfStockBehavior' => sanitize_key( (string) get_option( 'wcvs_oos_behavior', 'cross' ) ),
			'i18n'               => [
				'clearSelection' => __( 'Clear selection', 'wc-variation-studio' ),
				'playVideo'      => __( 'Play product video', 'wc-variation-studio' ),
			],
		];

		wp_add_inline_script(
			'wcvs-frontend',
			'window.wcvsConfig = ' . wp_json_encode( $frontend_config ) . ';',
			'before'
		);
	}

	public function enqueue_admin_assets( string $hook_suffix = '' ): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( 'product' === $screen->id || 'woocommerce_page_wc-settings' === $screen->id ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_style(
				'wcvs-admin',
				WCVS_PLUGIN_URL . 'assets/css/admin-variation-gallery.css',
				[],
				WCVS_VERSION
			);
			wp_enqueue_script(
				'wcvs-admin',
				WCVS_PLUGIN_URL . 'assets/js/admin-variation-gallery.js',
				[ 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ],
				WCVS_VERSION,
				true
			);
		}

		if ( in_array( $screen->base, [ 'edit-tags', 'term' ], true ) && str_starts_with( (string) $screen->taxonomy, 'pa_' ) ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script(
				'wcvs-admin-term-meta',
				WCVS_PLUGIN_URL . 'assets/js/admin-term-meta.js',
				[ 'jquery', 'wp-color-picker' ],
				WCVS_VERSION,
				true
			);
		}
	}

	private function should_enqueue_frontend_assets(): bool {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}

		$should_enqueue = is_woocommerce() || is_cart() || is_checkout();

		if ( ! $should_enqueue && is_singular() ) {
			$post = get_post();
			if ( $post instanceof \WP_Post ) {
				$should_enqueue = has_shortcode( $post->post_content, 'product_page' )
					|| has_shortcode( $post->post_content, 'products' );
			}
		}

		/**
		 * Filter whether WC Variation Studio frontend scripts and styles should be enqueued.
		 *
		 * @param bool $should_enqueue Default detection result.
		 */
		return (bool) apply_filters( 'wcvs_should_enqueue_frontend_assets', $should_enqueue );
	}
}
