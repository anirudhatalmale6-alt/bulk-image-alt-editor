<?php
/**
 * "Set ALT text" bulk action on the standard Media Library list view.
 *
 * A second way into the same engine, for people who already work in
 * Media > Library (list mode).
 *
 * @package Bulk_Image_Alt_Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPBIAE_Media_Bulk_Action
 */
class WPBIAE_Media_Bulk_Action {

	/**
	 * The bulk action key.
	 */
	const ACTION = 'wpbiae_set_alt';

	/**
	 * Same thing, but the attachment Title is set as well.
	 */
	const ACTION_WITH_TITLE = 'wpbiae_set_alt_title';

	/**
	 * Singleton.
	 *
	 * @var WPBIAE_Media_Bulk_Action|null
	 */
	private static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return WPBIAE_Media_Bulk_Action
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_filter( 'bulk_actions-upload', array( $this, 'register_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Add the action to the Media Library dropdown.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_action( $actions ) {
		if ( current_user_can( WPBIAE_CAP ) ) {
			$actions[ self::ACTION ]            = __( 'Set ALT text', 'bulk-image-alt-editor' );
			$actions[ self::ACTION_WITH_TITLE ] = __( 'Set ALT text and Title', 'bulk-image-alt-editor' );
		}

		return $actions;
	}

	/**
	 * Apply the ALT text to the selected attachments.
	 *
	 * @param string $location Redirect URL.
	 * @param string $doaction Action key.
	 * @param array  $post_ids Selected attachment IDs.
	 * @return string
	 */
	public function handle( $location, $doaction, $post_ids ) {
		if ( self::ACTION !== $doaction && self::ACTION_WITH_TITLE !== $doaction ) {
			return $location;
		}

		$also_title = ( self::ACTION_WITH_TITLE === $doaction );

		// upload.php has already run check_admin_referer( 'bulk-media' ).
		if ( ! current_user_can( WPBIAE_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to edit media on this site.', 'bulk-image-alt-editor' ), 403 );
		}

		$location = remove_query_arg(
			array( 'wpbiae_bulk_msg', 'wpbiae_bulk_updated', 'wpbiae_bulk_unchanged', 'wpbiae_bulk_skipped', 'wpbiae_bulk_failed', 'wpbiae_bulk_titles' ),
			$location
		);

		if ( ! isset( $_REQUEST['wpbiae_bulk_alt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return add_query_arg( 'wpbiae_bulk_msg', 'notext', $location );
		}

		// Cleaned by WPBIAE_Updater::clean(), which mirrors what core stores.
		$alt = WPBIAE_Updater::clean( $_REQUEST['wpbiae_bulk_alt'] ); // phpcs:ignore WordPress.Security

		$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $post_ids ) ) ) );

		if ( empty( $ids ) ) {
			return add_query_arg( 'wpbiae_bulk_msg', 'noselection', $location );
		}

		$result = WPBIAE_Updater::apply( $ids, $alt, $also_title );

		return add_query_arg(
			array(
				'wpbiae_bulk_msg'       => 'applied',
				'wpbiae_bulk_updated'   => (int) $result['updated'],
				'wpbiae_bulk_unchanged' => (int) $result['unchanged'],
				'wpbiae_bulk_skipped'   => (int) $result['skipped'],
				'wpbiae_bulk_failed'    => (int) $result['failed'],
				'wpbiae_bulk_titles'    => (int) $result['titles'],
			),
			$location
		);
	}

	/**
	 * Load the inline field script on the Media Library list view.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue( $hook ) {
		if ( 'upload.php' !== $hook || ! current_user_can( WPBIAE_CAP ) ) {
			return;
		}

		wp_enqueue_style( 'wpbiae-admin', WPBIAE_URL . 'assets/admin.css', array(), WPBIAE_VERSION );

		wp_enqueue_script( 'wpbiae-media', WPBIAE_URL . 'assets/media-bulk.js', array(), WPBIAE_VERSION, true );

		wp_localize_script(
			'wpbiae-media',
			'wpbiaeMediaL10n',
			array(
				'actions'      => array( self::ACTION, self::ACTION_WITH_TITLE ),
				'label'        => __( 'New ALT text:', 'bulk-image-alt-editor' ),
				'placeholder'  => __( 'Replaces the existing ALT entirely', 'bulk-image-alt-editor' ),
				'confirmEmpty' => __( 'The ALT text field is empty. Applying it will CLEAR the ALT text on the images you selected. Continue?', 'bulk-image-alt-editor' ),
				'noSelection'  => __( 'Tick at least one image first.', 'bulk-image-alt-editor' ),
			)
		);
	}

	/**
	 * Result notice on the Media Library screen.
	 */
	public function notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}

		if ( empty( $_GET['wpbiae_bulk_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$msg = sanitize_key( wp_unslash( $_GET['wpbiae_bulk_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'noselection' === $msg || 'notext' === $msg ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'Nothing was changed: no images were selected, or no ALT text was submitted.', 'bulk-image-alt-editor' )
			);

			return;
		}

		if ( 'applied' !== $msg ) {
			return;
		}

		$updated   = isset( $_GET['wpbiae_bulk_updated'] ) ? (int) $_GET['wpbiae_bulk_updated'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$unchanged = isset( $_GET['wpbiae_bulk_unchanged'] ) ? (int) $_GET['wpbiae_bulk_unchanged'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$skipped   = isset( $_GET['wpbiae_bulk_skipped'] ) ? (int) $_GET['wpbiae_bulk_skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$failed    = isset( $_GET['wpbiae_bulk_failed'] ) ? (int) $_GET['wpbiae_bulk_failed'] : 0; // phpcs:ignore WordPress.Security.NonceVerification

		$parts = array();

		$parts[] = sprintf(
			/* translators: %s: number of images. */
			_n( '%s image updated.', '%s images updated.', $updated, 'bulk-image-alt-editor' ),
			number_format_i18n( $updated )
		);

		$titles = isset( $_GET['wpbiae_bulk_titles'] ) ? (int) $_GET['wpbiae_bulk_titles'] : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $titles > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: number of images. */
				_n( 'Title also changed on %s image.', 'Title also changed on %s images.', $titles, 'bulk-image-alt-editor' ),
				number_format_i18n( $titles )
			);
		}

		if ( $unchanged > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: number of images. */
				_n( '%s already had this exact ALT text.', '%s already had this exact ALT text.', $unchanged, 'bulk-image-alt-editor' ),
				number_format_i18n( $unchanged )
			);
		}

		if ( $skipped > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: number of items. */
				_n( '%s skipped (not an image you can edit).', '%s skipped (not images you can edit).', $skipped, 'bulk-image-alt-editor' ),
				number_format_i18n( $skipped )
			);
		}

		if ( $failed > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: number of images. */
				_n( '%s failed to save.', '%s failed to save.', $failed, 'bulk-image-alt-editor' ),
				number_format_i18n( $failed )
			);
		}

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $failed > 0 ? 'notice-error' : 'notice-success' ),
			esc_html( implode( ' ', $parts ) )
		);
	}
}
