<?php
declare( strict_types=1 );

namespace WcVariationStudio\Core;

use WcVariationStudio\Admin\SettingsPage;
use WcVariationStudio\Admin\VariationGalleryMetabox;
use WcVariationStudio\Frontend\SwatchRenderer;
use WcVariationStudio\Gallery\DynamicStockMediaManager;
use WcVariationStudio\Gallery\VariationPayload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?self $instance = null;

	public DynamicStockMediaManager $stock_media;
	public VariationPayload $variation_payload;
	public SwatchRenderer $swatch_renderer;

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
	}

	public function init(): void {
		$this->stock_media->register_hooks();
		$this->variation_payload->register_hooks();
		$this->swatch_renderer->register_hooks();

		if ( is_admin() ) {
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
		if ( ! function_exists( 'is_woocommerce' ) || ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) ) {
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

		wp_localize_script(
			'wcvs-frontend',
			'wcvsConfig',
			[
				'reverseGallerySync' => 'yes' === get_option( 'wcvs_reverse_gallery_sync', 'yes' ),
				'urlDeepLink'        => 'yes' === get_option( 'wcvs_url_deep_link', 'yes' ),
				'outOfStockBehavior' => sanitize_key( (string) get_option( 'wcvs_oos_behavior', 'cross' ) ),
				'i18n'               => [
					'clearSelection' => esc_html__( 'Clear selection', 'wc-variation-studio' ),
					'playVideo'      => esc_html__( 'Play product video', 'wc-variation-studio' ),
				],
			]
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
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
	}
}
