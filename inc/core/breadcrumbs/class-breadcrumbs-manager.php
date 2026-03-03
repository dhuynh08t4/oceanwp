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
		require_once $dir . 'class-breadcrumbs-compatibility.php';
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

		$external = OceanWP_Breadcrumbs_Compatibility::instance()->get_external_breadcrumbs();
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
}
