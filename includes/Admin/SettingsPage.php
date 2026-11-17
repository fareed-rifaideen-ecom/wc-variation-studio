<?php
declare( strict_types=1 );

namespace WcVariationStudio\Admin;

use WC_Settings_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'variation_studio';
		$this->label = __( 'Variation Studio', 'wc-variation-studio' );
		parent::__construct();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings(): array {
		return [
			[
				'title' => __( 'Dynamic Stock-Driven Main Image & Gallery', 'wc-variation-studio' ),
				'desc'  => __( 'Automatically select the main product image and initial gallery from the highest-stock in-stock variation, hiding out-of-stock variation imagery.', 'wc-variation-studio' ),
				'type'  => 'title',
				'id'    => 'wcvs_dynamic_stock_section',
			],
			[
				'title'   => __( 'Enable Dynamic Stock Media', 'wc-variation-studio' ),
				'desc'    => __( 'Set main image to highest-stock variation and assemble gallery exclusively from in-stock variations', 'wc-variation-studio' ),
				'id'      => 'wcvs_dynamic_stock_media_global',
				'default' => 'yes',
				'type'    => 'checkbox',
			],
			[
				'title'   => __( 'Include Other In-Stock Gallery Slides', 'wc-variation-studio' ),
				'desc'    => __( 'Append secondary gallery images (not just primary images) from other in-stock variations in initial view', 'wc-variation-studio' ),
				'id'      => 'wcvs_dynamic_gallery_include_other_thumbs',
				'default' => 'no',
				'type'    => 'checkbox',
			],
			[
				'title'   => __( 'Unmanaged In-Stock Priority', 'wc-variation-studio' ),
				'id'      => 'wcvs_unmanaged_stock_priority',
				'default' => 'after_managed',
				'type'    => 'select',
				'options' => [
					'after_managed' => __( 'Rank after variations with tracked positive stock quantity', 'wc-variation-studio' ),
					'first'         => __( 'Prioritize unmanaged in-stock variations first', 'wc-variation-studio' ),
				],
			],
			[
				'title'   => __( 'Reverse Thumbnail-to-Swatch Sync', 'wc-variation-studio' ),
				'desc'    => __( 'Clicking an in-stock variation image in the initial gallery automatically selects its matching swatch attribute', 'wc-variation-studio' ),
				'id'      => 'wcvs_reverse_gallery_sync',
				'default' => 'yes',
				'type'    => 'checkbox',
			],
			[
				'type' => 'sectionend',
				'id'   => 'wcvs_dynamic_stock_section',
			],
			[
				'title' => __( 'Swatches & Variation Experience', 'wc-variation-studio' ),
				'type'  => 'title',
				'id'    => 'wcvs_swatches_section',
			],
			[
				'title'   => __( 'Swatch Shape', 'wc-variation-studio' ),
				'id'      => 'wcvs_swatch_shape',
				'default' => 'rounded',
				'type'    => 'select',
				'options' => [
					'rounded' => __( 'Rounded Square', 'wc-variation-studio' ),
					'circle'  => __( 'Circle', 'wc-variation-studio' ),
					'square'  => __( 'Sharp Square', 'wc-variation-studio' ),
				],
			],
			[
				'title'   => __( 'Out-of-Stock Swatch Style', 'wc-variation-studio' ),
				'id'      => 'wcvs_oos_behavior',
				'default' => 'cross',
				'type'    => 'select',
				'options' => [
					'cross' => __( 'Blur + Diagonal Cross', 'wc-variation-studio' ),
					'blur'  => __( 'Dimmed / Blur', 'wc-variation-studio' ),
					'hide'  => __( 'Hide Completely', 'wc-variation-studio' ),
				],
			],
			[
				'title'   => __( 'Shareable Variation Deep Links', 'wc-variation-studio' ),
				'desc'    => __( 'Sync selected variation attributes to the browser URL (?attribute_pa_color=...)', 'wc-variation-studio' ),
				'id'      => 'wcvs_url_deep_link',
				'default' => 'yes',
				'type'    => 'checkbox',
			],
			[
				'type' => 'sectionend',
				'id'   => 'wcvs_swatches_section',
			],
		];
	}
}
