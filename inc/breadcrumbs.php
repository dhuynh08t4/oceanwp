<?php
/**
 * OceanWP Breadcrumbs Gateway
 *
 * This file serves as a gateway to the new class-based breadcrumb system.
 * It maintains backward compatibility by keeping original function names
 * and providing a shim class for the legacy breadcrumb system.
 *
 * @package OceanWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the Manager from the new core location.
require_once OCEANWP_THEME_DIR . '/inc/core/breadcrumbs/class-breadcrumbs-manager.php';

/**
 * Shows a breadcrumb for all types of pages.
 *
 * @param  array $args Arguments.
 * @return void
 */
function oceanwp_breadcrumb_trail( $args = array() ) {
	echo OceanWP_Breadcrumbs_Manager::instance()->get_breadcrumbs( $args );
}

/**
 * Legacy class wrapper for backward compatibility.
 * Ocean Extra and other plugins might still instantiate this.
 */
class OceanWP_Breadcrumb_Trail {
	protected $args;

	public function __construct( $args = array() ) {
		$this->args = $args;
	}

	public function trail() {
		return OceanWP_Breadcrumbs_Manager::instance()->get_breadcrumbs( $this->args );
	}

	public function get_trail() {
		return OceanWP_Breadcrumbs_Manager::instance()->get_breadcrumbs( $this->args );
	}
}

/**
 * Support for external plugin breadcrumbs.
 * This function is still used by various hooks in the theme.
 */
function oceanwp_breadcrumbs_sources( $options ) {
	$is_enable  = is_callable( 'WPSEO_Options::get' ) ? WPSEO_Options::get( 'breadcrumbs-enable' ) : false;
	$wpseo_data = get_option( 'wpseo_internallinks' ) ? get_option( 'wpseo_internallinks' ) : $is_enable;
	if ( ! is_array( $wpseo_data ) ) {
		$wpseo_data = array(
			'breadcrumbs-enable' => $is_enable,
		);
	}

	if ( function_exists( 'yoast_breadcrumb' ) && true === $wpseo_data['breadcrumbs-enable'] ) {
		$options['yoast-seo'] = 'Yoast SEO Breadcrumbs';
	}

	if ( function_exists( 'seopress_display_breadcrumbs' ) ) {
		$options['seopress'] = 'SEOPress';
	}

	if ( function_exists( 'rank_math_the_breadcrumbs' ) && class_exists( 'RankMath\Helper' ) && RankMath\Helper::get_settings( 'general.breadcrumbs' ) ) {
		$options['rank-math'] = 'Rank Math';
	}

	return $options;
}
add_filter( 'oceanwp_breadcrumbs_source_list', 'oceanwp_breadcrumbs_sources' );

/**
 * Add container to SEOPRess breadcrumbs.
 */
function sp_breadcrumbs_before() {
	$classes = 'site-breadcrumbs clr';
	if ( $breadcrumbs_position = get_theme_mod( 'ocean_breadcrumbs_position' ) ) {
		$classes .= ' position-' . $breadcrumbs_position;
	}

	echo '<div class="' . esc_attr( $classes ) . '">';
}
add_action( 'seopress_breadcrumbs_before_html', 'sp_breadcrumbs_before' );

/**
 * Div closed
 */
function sp_breadcrumbs_after() {
	echo '</div>';
}
add_action( 'seopress_breadcrumbs_after_html', 'sp_breadcrumbs_after' );

/**
 * Add container to Rank Math breadcrumbs.
 */
function rm_breadcrumbs( $args ) {
	$classes = 'site-breadcrumbs clr';
	if ( $breadcrumbs_position = get_theme_mod( 'ocean_breadcrumbs_position' ) ) {
		$classes .= ' position-' . $breadcrumbs_position;
	}
	$args['wrap_before'] = '<div class="' . $classes . '">';
	$args['wrap_after']  = '</div>';
	return $args;
}
add_action( 'rank_math/frontend/breadcrumb/args', 'rm_breadcrumbs' );

/**
 * Add container to WooCommerce breadcrumbs.
 */
function owp_woo_breadcrumbs( $args ) {

	$classes = 'site-breadcrumbs woocommerce-breadcrumbs clr';
	if ( $breadcrumbs_position = get_theme_mod( 'ocean_breadcrumbs_position' ) ) {
		$classes .= ' position-' . $breadcrumbs_position;
	}

	$separator = apply_filters( 'oceanwp_breadcrumb_separator', get_theme_mod( 'ocean_breadcrumb_separator', '>' ) );
	$separator = '<span class="breadcrumb-sep">' . $separator . '</span>';

	$args['wrap_before'] = '<div class="' . $classes . '">';
	$args['wrap_after']  = '</div>';
	$args['delimiter']   = $separator;

	return $args;
}
add_filter( 'woocommerce_breadcrumb_defaults', 'owp_woo_breadcrumbs' );
