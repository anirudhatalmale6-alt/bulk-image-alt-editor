<?php
/**
 * Builds the image queries behind the picker.
 *
 * Kept separate so the on-screen list and the "select every matching image"
 * shortcut are guaranteed to run the same filter.
 *
 * @package Bulk_Image_Alt_Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPBIAE_Query
 */
class WPBIAE_Query {

	/**
	 * Build WP_Query arguments from a set of screen filters.
	 *
	 * @param array $filters {
	 *     @type string $search  Free text search.
	 *     @type string $alt     One of 'all', 'missing', 'has'.
	 *     @type string $orderby One of 'title', 'date'.
	 *     @type string $order   'asc' or 'desc'.
	 *     @type int    $paged   Page number. Ignored when $all is true.
	 *     @type int    $per_page Rows per page. Ignored when $all is true.
	 * }
	 * @param bool  $all When true, return every match with no pagination, IDs only.
	 * @return array WP_Query arguments.
	 */
	public static function args( array $filters, $all = false ) {
		$filters = wp_parse_args(
			$filters,
			array(
				'search'   => '',
				'alt'      => 'all',
				'orderby'  => 'date',
				'order'    => 'desc',
				'paged'    => 1,
				'per_page' => 20,
			)
		);

		$args = array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'post_mime_type'         => 'image',
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		);

		$orderby = in_array( $filters['orderby'], array( 'title', 'date' ), true ) ? $filters['orderby'] : 'date';
		$order   = ( 'asc' === strtolower( $filters['order'] ) ) ? 'ASC' : 'DESC';

		$args['orderby'] = $orderby;
		$args['order']   = $order;

		if ( '' !== trim( (string) $filters['search'] ) ) {
			$args['s'] = trim( (string) $filters['search'] );
		}

		if ( 'missing' === $filters['alt'] ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => WPBIAE_META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => WPBIAE_META_KEY,
					'value'   => '',
					'compare' => '=',
				),
			);
		} elseif ( 'has' === $filters['alt'] ) {
			$args['meta_query'] = array(
				array(
					'key'     => WPBIAE_META_KEY,
					'value'   => '',
					'compare' => '!=',
				),
			);
		}

		if ( $all ) {
			$args['nopaging'] = true;
			$args['fields']   = 'ids';
		} else {
			$args['posts_per_page'] = max( 1, (int) $filters['per_page'] );
			$args['paged']          = max( 1, (int) $filters['paged'] );
		}

		return $args;
	}

	/**
	 * Run a query with attachment filename search switched on.
	 *
	 * Core only searches attachment filenames when a query opts in through
	 * wp_allow_query_attachment_by_filename. Without it, searching for
	 * "hero-banner.jpg" finds nothing unless that string is also in the title.
	 *
	 * @param array $args WP_Query arguments.
	 * @return WP_Query
	 */
	public static function run( array $args ) {
		add_filter( 'wp_allow_query_attachment_by_filename', '__return_true' );

		$query = new WP_Query( $args );

		remove_filter( 'wp_allow_query_attachment_by_filename', '__return_true' );

		return $query;
	}

	/**
	 * Every attachment ID matching a filter, ignoring pagination.
	 *
	 * @param array $filters See self::args().
	 * @return int[]
	 */
	public static function all_ids( array $filters ) {
		$query = self::run( self::args( $filters, true ) );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Count images in each of the three ALT views.
	 *
	 * @param string $search Free text search to apply to every count.
	 * @return array {
	 *     @type int $all
	 *     @type int $missing
	 *     @type int $has
	 * }
	 */
	public static function view_counts( $search = '' ) {
		$counts = array();

		foreach ( array( 'all', 'missing', 'has' ) as $view ) {
			$args                   = self::args(
				array(
					'search'   => $search,
					'alt'      => $view,
					'per_page' => 1,
					'paged'    => 1,
				)
			);
			$args['fields']         = 'ids';
			$args['no_found_rows']  = false;
			$counts[ $view ]        = (int) self::run( $args )->found_posts;
		}

		return $counts;
	}
}
