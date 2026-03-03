<?php
/**
 * OceanWP Breadcrumbs Manager
 *
 * @package OceanWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OceanWP_Breadcrumbs_Manager {

	/**
	 * Instance.
	 *
	 * @var OceanWP_Breadcrumbs_Manager
	 */
	private static $instance;

	/**
	 * Cached crumbs.
	 *
	 * @var array
	 */
	private $cached_crumbs = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->includes();
	}

	/**
	 * Get instance.
	 *
	 * @return OceanWP_Breadcrumbs_Manager
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		$dir = OCEANWP_THEME_DIR . '/inc/core/breadcrumbs/';
		
		require_once $dir . 'class-breadcrumbs-crumb.php';
		require_once $dir . 'class-breadcrumbs-renderer.php';
		require_once $dir . 'abstract-breadcrumbs-provider.php';
		
		// Providers
		require_once $dir . 'providers/class-breadcrumbs-singular.php';
		require_once $dir . 'providers/class-breadcrumbs-simple.php';
		require_once $dir . 'providers/class-breadcrumbs-woo.php';
	}

	/**
	 * Display the breadcrumbs.
	 *
	 * @param array $args Arguments.
	 * @return string
	 */
	public function get_breadcrumbs( $args = array() ) {
		// Return if breadcrumbs are disabled or on front page (unless show_on_front is true)
		if ( function_exists( 'oceanwp_has_breadcrumbs' ) && ! oceanwp_has_breadcrumbs() ) {
			return '';
		}

		if ( is_front_page() && empty( $args['show_on_front'] ) ) {
			return '';
		}

		$external = $this->get_external_source_breadcrumbs();
		if ( ! is_null( $external ) ) {
			return $external;
		}

		$defaults = array(
			'container'     => 'nav',
			'before'        => '',
			'after'         => '',
			'show_on_front' => false,
			'network'       => false,
			'show_title'    => get_theme_mod( 'ocean_breadcrumb_show_title', true ),
			'labels'        => array(),
			'post_taxonomy' => array(),
			'echo'          => true,
			'schema'        => get_theme_mod( 'ocean_breadcrumb_schema', true ),
		);

		$args = apply_filters( 'oceanwp_breadcrumb_trail_args', wp_parse_args( $args, $defaults ) );
		
		// Use cache if available
		if ( ! is_null( $this->cached_crumbs ) ) {
			$crumbs = $this->cached_crumbs;
		} else {
			// Set labels
			$args['labels'] = $this->get_labels( $args['labels'] );

			// Determine Provider
			$provider = $this->determine_provider( $args );
			if ( ! $provider ) {
				return '';
			}

			$crumbs = $provider->get_items();

			// Add home crumbs as prefix
			$home_crumbs = $this->get_home_crumbs( $args );
			$crumbs = array_merge( $home_crumbs, $crumbs );

			// Final filter for items
			$crumbs = apply_filters( 'oceanwp_breadcrumb_trail_items', $crumbs, $args );
			
			// Fill cache
			$this->cached_crumbs = $crumbs;
		}

		$renderer = new OceanWP_Breadcrumbs_Renderer( $crumbs, $args );
		return $renderer->render();
	}

	/**
	 * Get breadcrumbs from external sources (SEO plugins).
	 *
	 * @return string|null
	 */
	private function get_external_source_breadcrumbs() {
		$breadcrumb_source = get_theme_mod( 'ocean_breadcrumbs_source' );

		$is_enable  = is_callable( 'WPSEO_Options::get' ) ? WPSEO_Options::get( 'breadcrumbs-enable' ) : false;
		$wpseo_data = get_option( 'wpseo_internallinks' ) ? get_option( 'wpseo_internallinks' ) : $is_enable;
		if ( ! is_array( $wpseo_data ) ) {
			$wpseo_data = array(
				'breadcrumbs-enable' => $is_enable,
			);
		}

		// Yoast breadcrumbs.
		if ( function_exists( 'yoast_breadcrumb' ) && true === $wpseo_data['breadcrumbs-enable'] && $breadcrumb_source && 'yoast-seo' === $breadcrumb_source ) {
			$classes = 'site-breadcrumbs clr';
			if ( $breadcrumbs_position = get_theme_mod( 'ocean_breadcrumbs_position' ) ) {
				$classes .= ' position-' . $breadcrumbs_position;
			}
			return yoast_breadcrumb( '<nav class="' . $classes . '">', '</nav>', false );
		}

		// SEOPress breadcrumbs.
		if ( function_exists( 'seopress_display_breadcrumbs' ) && $breadcrumb_source && 'seopress' === $breadcrumb_source ) {
			return seopress_display_breadcrumbs();
		}

		// Rank Math breadcrumbs.
		if ( function_exists( 'rank_math_the_breadcrumbs' ) && class_exists( 'RankMath\Helper' ) && RankMath\Helper::get_settings( 'general.breadcrumbs' ) && $breadcrumb_source && 'rank-math' === $breadcrumb_source ) {
			return rank_math_the_breadcrumbs();
		}

		return null;
	}

	/**
	 * Determine which provider to use.
	 */
	private function determine_provider( $args ) {
		$provider_class = '';

		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			$provider_class = 'OceanWP_Breadcrumbs_WooCommerce_Provider';
		} elseif ( is_singular() ) {
			$provider_class = 'OceanWP_Breadcrumbs_Singular_Provider';
		} elseif ( is_search() ) {
			$provider_class = 'OceanWP_Breadcrumbs_Search_Provider';
		} elseif ( is_404() ) {
			$provider_class = 'OceanWP_Breadcrumbs_404_Provider';
		} elseif ( is_archive() ) {
			$provider_class = 'OceanWP_Breadcrumbs_Archive_Provider';
		}

		$provider_class = apply_filters( 'oceanwp_breadcrumbs_provider_class', $provider_class, $args );

		if ( ! empty( $provider_class ) && class_exists( $provider_class ) ) {
			return new $provider_class( $args );
		}

		return null;
	}

	/**
	 * Get labels.
	 */
	private function get_labels( $custom_labels ) {
		$defaults = array(
			'aria_label'     => esc_attr_x( 'Breadcrumbs', 'breadcrumbs aria label', 'oceanwp' ),
			'home'           => get_theme_mod( 'ocean_breadcrumb_translation_home', esc_html__( 'Home', 'oceanwp' ) ),
			'error_404'      => get_theme_mod( 'ocean_breadcrumb_translation_error', esc_html__( '404 Not Found', 'oceanwp' ) ),
			'archives'       => esc_html__( 'Archives', 'oceanwp' ),
			'search'         => get_theme_mod( 'ocean_breadcrumb_translation_search', esc_html__( 'Search results for', 'oceanwp' ) ),
			'paged'          => esc_html__( 'Page %s', 'oceanwp' ),
		);

		return apply_filters( 'breadcrumb_trail_labels', wp_parse_args( $custom_labels, $defaults ) );
	}

	/**
	 * Get home crumbs.
	 */
	private function get_home_crumbs( $args ) {
		$home_crumbs = array();
		
		$setting = get_theme_mod( 'ocean_breadcrumb_home_item', 'icon' );
		$label   = $args['labels']['home'];
		$icon    = '';

		if ( 'icon' === $setting || is_customize_preview() ) {
			$icon = function_exists( 'oceanwp_icon' ) ? oceanwp_icon( 'home', false ) : '';
		}

		if ( 'icon' === $setting && ! is_customize_preview() ) {
			$label = $icon;
		} elseif ( is_customize_preview() ) {
			$text_class = ( 'icon' === $setting ) ? ' has-icon' : '';
			$label = $icon . '<span class="breadcrumb-home' . $text_class . '">' . $label . '</span>';
		}

		// Network home
		if ( is_multisite() && ! is_main_site() && ! empty( $args['network'] ) ) {
			$home_crumbs[] = new OceanWP_Breadcrumbs_Crumb( $label, network_home_url() );
		}

		// Site home
		$home_crumbs[] = new OceanWP_Breadcrumbs_Crumb( $label, home_url() );

		return $home_crumbs;
	}

	/**
	 * Get breadcrumb sources.
	 *
	 * @param array $options Sources.
	 * @return array
	 */
	public function get_sources( $options ) {
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

	/**
	 * SEOPress before HTML.
	 */
	public function sp_before() {
		$classes = 'site-breadcrumbs clr';
		if ( $breadcrumbs_position = get_theme_mod( 'ocean_breadcrumbs_position' ) ) {
			$classes .= ' position-' . $breadcrumbs_position;
		}

		echo '<div class="' . esc_attr( $classes ) . '">';
	}

	/**
	 * SEOPress after HTML.
	 */
	public function sp_after() {
		echo '</div>';
	}

	/**
	 * Rank Math breadcrumb args.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public function rm_args( $args ) {
		$classes = 'site-breadcrumbs clr';
		if ( $breadcrumbs_position = get_theme_mod( 'ocean_breadcrumbs_position' ) ) {
			$classes .= ' position-' . $breadcrumbs_position;
		}
		$args['wrap_before'] = '<div class="' . $classes . '">';
		$args['wrap_after']  = '</div>';
		return $args;
	}

	/**
	 * WooCommerce breadcrumb defaults.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public function woo_defaults( $args ) {
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
}
