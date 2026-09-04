<?php
/**
 * The image picker, built on WordPress's own WP_List_Table.
 *
 * @package Bulk_Image_Alt_Editor
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class WPBIAE_List_Table
 */
class WPBIAE_List_Table extends WP_List_Table {

	/**
	 * Current screen filters.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Counts for the view links.
	 *
	 * @var array
	 */
	protected $counts = array();

	/**
	 * Constructor.
	 *
	 * @param array $filters Screen filters. See WPBIAE_Query::args().
	 */
	public function __construct( array $filters ) {
		parent::__construct(
			array(
				'singular' => 'image',
				'plural'   => 'images',
				'ajax'     => false,
				'screen'   => 'media_page_' . WPBIAE_SLUG,
			)
		);

		$this->filters = $filters;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'    => '<input type="checkbox" />',
			'thumb' => __( 'Image', 'bulk-image-alt-editor' ),
			'title' => __( 'File', 'bulk-image-alt-editor' ),
			'alt'   => __( 'Current ALT text', 'bulk-image-alt-editor' ),
			'date'  => __( 'Uploaded', 'bulk-image-alt-editor' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
			'date'  => array( 'date', true ),
		);
	}

	/**
	 * No WordPress bulk-actions dropdown: this screen has its own Apply control.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array();
	}

	/**
	 * The All / Missing ALT / Has ALT links.
	 *
	 * @return array
	 */
	protected function get_views() {
		$base = remove_query_arg( array( 'paged', 'wpbiae_notice', 'updated', 'unchanged', 'skipped', 'failed', 'undo' ) );

		$labels = array(
			'all'     => __( 'All images', 'bulk-image-alt-editor' ),
			'missing' => __( 'Missing ALT', 'bulk-image-alt-editor' ),
			'has'     => __( 'Has ALT', 'bulk-image-alt-editor' ),
		);

		$views = array();

		foreach ( $labels as $key => $label ) {
			$url   = ( 'all' === $key ) ? remove_query_arg( 'alt_filter', $base ) : add_query_arg( 'alt_filter', $key, $base );
			$count = isset( $this->counts[ $key ] ) ? (int) $this->counts[ $key ] : 0;

			$views[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $url ),
				( $this->filters['alt'] === $key ) ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		return $views;
	}

	/**
	 * Load the rows for the current page.
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'wpbiae_per_page', 20 );

		$this->filters['per_page'] = $per_page;

		$query = WPBIAE_Query::run( WPBIAE_Query::args( $this->filters ) );

		$this->items  = $query->posts;
		$this->counts = WPBIAE_Query::view_counts( $this->filters['search'] );

		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => (int) $query->max_num_pages,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'title' );
	}

	/**
	 * Total rows matching the current filter, across all pages.
	 *
	 * @return int
	 */
	public function total_items() {
		$args = $this->_pagination_args;

		return isset( $args['total_items'] ) ? (int) $args['total_items'] : 0;
	}

	/**
	 * Rows shown on one page.
	 *
	 * @return int
	 */
	public function per_page() {
		$args = $this->_pagination_args;

		return isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No images match this filter.', 'bulk-image-alt-editor' );
	}

	/**
	 * Checkbox column.
	 *
	 * @param WP_Post $item Attachment.
	 * @return string
	 */
	public function column_cb( $item ) {
		$editable = WPBIAE_Updater::is_editable_image( $item->ID );

		if ( ! $editable ) {
			return '<span class="dashicons dashicons-lock" title="' .
				esc_attr__( 'You cannot edit this image.', 'bulk-image-alt-editor' ) . '"></span>';
		}

		return sprintf(
			'<label class="screen-reader-text" for="wpbiae-cb-%1$d">%2$s</label>' .
			'<input type="checkbox" id="wpbiae-cb-%1$d" name="wpbiae_ids[]" value="%1$d" />',
			(int) $item->ID,
			/* translators: %s: image title. */
			esc_html( sprintf( __( 'Select %s', 'bulk-image-alt-editor' ), get_the_title( $item->ID ) ) )
		);
	}

	/**
	 * Thumbnail column.
	 *
	 * @param WP_Post $item Attachment.
	 * @return string
	 */
	public function column_thumb( $item ) {
		$img = wp_get_attachment_image( $item->ID, array( 60, 60 ), true, array( 'class' => 'wpbiae-thumb' ) );

		return $img ? $img : '&mdash;';
	}

	/**
	 * File column: title, filename and an edit link.
	 *
	 * @param WP_Post $item Attachment.
	 * @return string
	 */
	public function column_title( $item ) {
		$title = get_the_title( $item->ID );

		if ( '' === trim( $title ) ) {
			$title = __( '(no title)', 'bulk-image-alt-editor' );
		}

		$file     = get_attached_file( $item->ID );
		$basename = $file ? wp_basename( $file ) : '';

		$out = '<strong>' . esc_html( $title ) . '</strong>';

		if ( '' !== $basename ) {
			$out .= '<div class="wpbiae-filename">' . esc_html( $basename ) . '</div>';
		}

		$actions = array();

		if ( current_user_can( 'edit_post', $item->ID ) ) {
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_edit_post_link( $item->ID ) ),
				esc_html__( 'Edit', 'bulk-image-alt-editor' )
			);
		}

		$src = wp_get_attachment_url( $item->ID );

		if ( $src ) {
			$actions['view'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $src ),
				esc_html__( 'View file', 'bulk-image-alt-editor' )
			);
		}

		return $out . $this->row_actions( $actions );
	}

	/**
	 * Current ALT column.
	 *
	 * @param WP_Post $item Attachment.
	 * @return string
	 */
	public function column_alt( $item ) {
		$alt = (string) get_post_meta( $item->ID, WPBIAE_META_KEY, true );

		if ( '' === $alt ) {
			return '<span class="wpbiae-empty-alt">' . esc_html__( '(empty)', 'bulk-image-alt-editor' ) . '</span>';
		}

		return '<span class="wpbiae-current-alt">' . esc_html( $alt ) . '</span>';
	}

	/**
	 * Uploaded column.
	 *
	 * @param WP_Post $item Attachment.
	 * @return string
	 */
	public function column_date( $item ) {
		return esc_html( get_the_date( get_option( 'date_format' ), $item ) );
	}

	/**
	 * Fallback for any column without a handler.
	 *
	 * @param WP_Post $item        Attachment.
	 * @param string  $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return '';
	}
}
