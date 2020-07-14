<?php
/**
 * Newspack Sponsors setup.
 *
 * Registers Sponsors custom post type and taxonomy, and creates a shadow
 * relationship between them.
 *
 * @package Newspack_Sponsors
 */

namespace Newspack_Sponsors;

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_SPONSORS_PLUGIN_FILE . 'vendor/autoload.php';

/**
 * WP globals used in this class.
 */
use \current_user_can as current_user_can;
use \get_post as get_post;
use \get_term_by as get_term_by;
use \register_post_type as register_post_type;
use \register_taxonomy as register_taxonomy;
use \wp_insert_term as wp_insert_term;
use \wp_update_term as wp_update_term;

/**
 * Main Newspack_Sponsors class.
 * Sets up Sponsors CPT and shadow taxonomy for posts.
 */
final class Newspack_Sponsors {

	const NEWSPACK_SPONSORS_CPT = 'newspack_spnsrs_cpt';
	const NEWSPACK_SPONSORS_TAX = 'newspack_spnsrs_tax';

	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Ads
	 */
	protected static $instance = null;

	/**
	 * Main Newspack_Sponsors instance.
	 * Ensures only one instance of Newspack_Sponsors is loaded or can be loaded.
	 *
	 * @return Newspack_Sponsors - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ __CLASS__, 'init' ] );
	}

	/**
	 * After WP init.
	 */
	public static function init() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		self::register_cpt();
		self::register_tax();
		self::create_shadow_relationship();
	}

	/**
	 * Registers Sponsors custom post type.
	 */
	public static function register_cpt() {
		$labels = [
			'name'               => _x( 'Sponsors', 'post type general name', 'newspack-sponsors' ),
			'singular_name'      => _x( 'Sponsor', 'post type singular name', 'newspack-sponsors' ),
			'menu_name'          => _x( 'Sponsors', 'admin menu', 'newspack-sponsors' ),
			'name_admin_bar'     => _x( 'Sponsor', 'add new on admin bar', 'newspack-sponsors' ),
			'add_new'            => _x( 'Add New', 'popup', 'newspack-sponsors' ),
			'add_new_item'       => __( 'Add New Sponsor', 'newspack-sponsors' ),
			'new_item'           => __( 'New Sponsor', 'newspack-sponsors' ),
			'edit_item'          => __( 'Edit Sponsor', 'newspack-sponsors' ),
			'view_item'          => __( 'View Sponsor', 'newspack-sponsors' ),
			'all_items'          => __( 'All Sponsors', 'newspack-sponsors' ),
			'search_items'       => __( 'Search Sponsors', 'newspack-sponsors' ),
			'parent_item_colon'  => __( 'Parent Sponsors:', 'newspack-sponsors' ),
			'not_found'          => __( 'No sponsors found.', 'newspack-sponsors' ),
			'not_found_in_trash' => __( 'No sponsors found in Trash.', 'newspack-sponsors' ),
		];

		$cpt_args = [
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'supports'     => [ 'editor', 'title', 'custom-fields' ],
			'taxonomies'   => [ 'category', 'post_tag' ], // Regular post categories and tags.
			'menu_icon'    => 'dashicons-money',
		];

		register_post_type( self::NEWSPACK_SPONSORS_CPT, $cpt_args );
	}

	/**
	 * Registers Sponsors taxonomy which can be applied to posts.
	 * Terms in this taxonomy are not created or edited directly, but are linked to Sponsor posts.
	 */
	public static function register_tax() {
		$labels = [
			'name'                  => __( 'Sponsors', 'newspack-sponsors' ),
			'singular_name'         => __( 'Sponsors', 'newspack-sponsors' ),
			'search_items'          => __( 'Search Sponsors', 'newspack-sponsors' ),
			'all_items'             => __( 'Sponsors', 'newspack-sponsors' ),
			'parent_item'           => __( 'Parent Sponsor', 'newspack-sponsors' ),
			'parent_item_colon'     => __( 'Parent Sponsor:', 'newspack-sponsors' ),
			'edit_item'             => __( 'Edit Sponsor', 'newspack-sponsors' ),
			'view_item'             => __( 'View Sponsor', 'newspack-sponsors' ),
			'update_item'           => __( 'Update Sponsor', 'newspack-sponsors' ),
			'add_new_item'          => __( 'Add New Sponsor', 'newspack-sponsors' ),
			'new_item_name'         => __( 'New Sponsor Name', 'newspack-sponsors' ),
			'not_found'             => __( 'No sponsors found.', 'newspack-sponsors' ),
			'no_terms'              => __( 'No sponsors', 'newspack-sponsors' ),
			'items_list_navigation' => __( 'Sponsors list navigation', 'newspack-sponsors' ),
			'items_list'            => __( 'Sponsors list', 'newspack-sponsors' ),
			'back_to_items'         => __( '&larr; Back to Sponsors', 'newspack-sponsors' ),
			'menu_name'             => __( 'Sponsors', 'newspack-sponsors' ),
			'name_admin_bar'        => __( 'Sponsors', 'newspack-sponsors' ),
			'archives'              => __( 'Sponsors', 'newspack-sponsors' ),
		];

		$tax_args = [
			'hierarchical'  => true,
			'labels'        => $labels,
			'public'        => true,
			'rewrite'       => [ 'slug' => self::NEWSPACK_SPONSORS_TAX ],
			'show_in_menu'  => false,
			'show_in_rest'  => true,
			'show_tagcloud' => false,
			'show_ui'       => true,
		];

		register_taxonomy( self::NEWSPACK_SPONSORS_TAX, 'post', $tax_args );
	}

	/**
	 * Create a relationship between the Sponsors CPT and Sponsors tax.
	 */
	public static function create_shadow_relationship() {
		add_action( 'wp_insert_post', [ __CLASS__, 'update_shadow_term' ] );
		add_action( 'before_delete_post', [ __CLASS__, 'delete_shadow_term' ] );
	}

	/**
	 * Creates a new taxonomy term, or updates an existing one.
	 *
	 * @param int $post_id ID for the post being inserted or saved.
	 * @return bool|void Nothing if successful, or false if not.
	 */
	public static function update_shadow_term( $post_id ) {
		$post = get_post( $post_id );

		// Bail if we don't have a valid post or post type.
		if ( empty( $post ) || self::NEWSPACK_SPONSORS_CPT !== $post->post_type ) {
			return false;
		}

		// Bail if post is an auto draft.
		if ( 'auto-draft' === $post->post_status || 'Auto Draft' === $post->post_title ) {
			return false;
		}

		// Check for a shadow term associated with this post.
		$shadow_term = self::get_shadow_term( $post );

		// If there isn't already a shadow term, create it.
		if ( empty( $shadow_term ) ) {
			self::create_shadow_term( $post );
		} else {
			// Otherwise, update the existing term.
			wp_update_term(
				$shadow_term->term_id,
				self::NEWSPACK_SPONSORS_TAX,
				[
					'name' => $post->post_title,
					'slug' => $post->post_name,
				]
			);
		}
	}

	/**
	 * Deletes an existing shadow taxonomy term when the post is being deleted.
	 *
	 * @param int $post_id ID for the post being deleted.
	 * @return bool|void Nothing if successful, or false if not.
	 */
	public static function delete_shadow_term( $post_id ) {
		$post = get_post( $post_id );

		// Bail if we don't have a valid post or post type.
		if ( empty( $post ) || self::NEWSPACK_SPONSORS_CPT !== $post->post_type ) {
			return false;
		}

		// Check for a shadow term associated with this post.
		$shadow_term = self::get_shadow_term( $post );

		if ( empty( $shadow_term ) ) {
			return false;
		}

		wp_delete_term( $shadow_term->term_id, self::NEWSPACK_SPONSORS_TAX );
	}

	/**
	 * Looks up a shadow taxonomy term linked to a given post.
	 *
	 * @param array $post Post object to look up.
	 * @return array|bool Term object of the linked term, if any, or false.
	 */
	public static function get_shadow_term( $post ) {
		if ( empty( $post ) || empty( $post->post_title ) ) {
			return false;
		}

		$shadow_term = get_term_by( 'name', $post->post_title, self::NEWSPACK_SPONSORS_TAX );

		if ( empty( $shadow_term ) ) {
			return false;
		}

		return $shadow_term;
	}

	/**
	 * Creates a shadow taxonomy term linked to the given post.
	 *
	 * @param array $post Post object for which to create a shadow term.
	 * @return array|bool Term object if successful, false if not.
	 */
	public static function create_shadow_term( $post ) {
		$new_term = wp_insert_term(
			$post->post_title,
			self::NEWSPACK_SPONSORS_TAX,
			[
				'slug' => $post->post_name,
			]
		);

		if ( is_wp_error( $new_term ) ) {
			return false;
		}

		return $new_term;
	}
}

Newspack_Sponsors::instance();
