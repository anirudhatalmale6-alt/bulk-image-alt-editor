<?php
/**
 * The Media > Bulk Alt Editor screen.
 *
 * @package Bulk_Image_Alt_Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPBIAE_Admin_Page
 */
class WPBIAE_Admin_Page {

	/**
	 * Singleton.
	 *
	 * @var WPBIAE_Admin_Page|null
	 */
	private static $instance = null;

	/**
	 * Hook suffix returned by add_submenu_page().
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Notice data carried across the post/redirect/get.
	 *
	 * @var array
	 */
	private $notice = array();

	/**
	 * Singleton accessor.
	 *
	 * @return WPBIAE_Admin_Page
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
		add_filter( 'set_screen_option_wpbiae_per_page', array( $this, 'save_screen_option' ), 10, 3 );
	}

	/**
	 * Add the submenu under Media.
	 */
	public function register_menu() {
		$this->hook = add_submenu_page(
			'upload.php',
			__( 'Bulk Image ALT Editor', 'bulk-image-alt-editor' ),
			__( 'Bulk Alt Editor', 'bulk-image-alt-editor' ),
			WPBIAE_CAP,
			WPBIAE_SLUG,
			array( $this, 'render' )
		);

		if ( ! $this->hook ) {
			return;
		}

		add_action( 'load-' . $this->hook, array( $this, 'on_load' ) );
		add_action( 'admin_print_styles-' . $this->hook, array( $this, 'enqueue' ) );
	}

	/**
	 * Screen setup: handle submissions, register screen options.
	 */
	public function on_load() {
		require_once WPBIAE_DIR . 'includes/class-wpbiae-list-table.php';

		$this->handle_undo();
		$this->handle_apply();
		$this->read_notice();

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Images per page', 'bulk-image-alt-editor' ),
				'default' => 20,
				'option'  => 'wpbiae_per_page',
			)
		);

		get_current_screen()->add_help_tab(
			array(
				'id'      => 'wpbiae-help',
				'title'   => __( 'How this works', 'bulk-image-alt-editor' ),
				'content' =>
					'<p>' . esc_html__( 'Type the ALT text you want in the field at the top, tick the images that should receive it, then press Apply.', 'bulk-image-alt-editor' ) . '</p>' .
					'<p>' . esc_html__( 'The text you type becomes the complete new ALT value. Nothing is added before or after it, and images you did not tick are left alone.', 'bulk-image-alt-editor' ) . '</p>' .
					'<p>' . esc_html__( 'Ticking the box in the table header selects the images on the current page only. To cover every image matching your current search or filter, use the "Select all ... images" link that appears.', 'bulk-image-alt-editor' ) . '</p>',
			)
		);
	}

	/**
	 * Persist the per-page screen option.
	 *
	 * @param mixed  $status Current value.
	 * @param string $option Option name.
	 * @param mixed  $value  Submitted value.
	 * @return mixed
	 */
	public function save_screen_option( $status, $option, $value ) {
		if ( 'wpbiae_per_page' === $option ) {
			$value = (int) $value;

			return ( $value > 0 && $value <= 500 ) ? $value : 20;
		}

		return $status;
	}

	/**
	 * Front-end assets. Two small files, no libraries.
	 */
	public function enqueue() {
		wp_enqueue_style( 'wpbiae-admin', WPBIAE_URL . 'assets/admin.css', array(), WPBIAE_VERSION );

		wp_enqueue_script( 'wpbiae-admin', WPBIAE_URL . 'assets/admin.js', array(), WPBIAE_VERSION, true );

		wp_localize_script(
			'wpbiae-admin',
			'wpbiaeL10n',
			array(
				'confirmEmpty'   => __( 'The ALT text field is empty. Applying it will CLEAR the ALT text on the images you selected. Continue?', 'bulk-image-alt-editor' ),
				/* translators: %s: number of images. */
				'confirmAll'     => __( 'This will replace the ALT text on all %s images matching the current filter. Continue?', 'bulk-image-alt-editor' ),
				'noSelection'    => __( 'Tick at least one image first.', 'bulk-image-alt-editor' ),
				/* translators: %s: number of images. */
				'selectAll'      => __( 'Select all %s images matching this filter', 'bulk-image-alt-editor' ),
				/* translators: %s: number of images. */
				'allSelected'    => __( 'All %s images matching this filter are selected.', 'bulk-image-alt-editor' ),
				'clearSelection' => __( 'Clear selection', 'bulk-image-alt-editor' ),
			)
		);
	}

	/**
	 * Read the current screen filters out of the request.
	 *
	 * @param string $method 'get' or 'post'.
	 * @return array
	 */
	private function filters_from_request( $method = 'get' ) {
		$src = ( 'post' === $method ) ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification

		$alt = isset( $src['alt_filter'] ) ? sanitize_key( wp_unslash( $src['alt_filter'] ) ) : 'all';

		if ( ! in_array( $alt, array( 'all', 'missing', 'has' ), true ) ) {
			$alt = 'all';
		}

		return array(
			'search'  => isset( $src['s'] ) ? sanitize_text_field( wp_unslash( $src['s'] ) ) : '',
			'alt'     => $alt,
			'orderby' => isset( $src['orderby'] ) ? sanitize_key( wp_unslash( $src['orderby'] ) ) : 'date',
			'order'   => isset( $src['order'] ) ? sanitize_key( wp_unslash( $src['order'] ) ) : 'desc',
			'paged'   => isset( $src['paged'] ) ? max( 1, (int) $src['paged'] ) : 1,
		);
	}

	/**
	 * Process an Apply submission, then redirect.
	 */
	private function handle_apply() {
		if ( ! isset( $_POST['wpbiae_apply'] ) ) {
			return;
		}

		check_admin_referer( 'wpbiae_apply', 'wpbiae_nonce' );

		if ( ! current_user_can( WPBIAE_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to edit media on this site.', 'bulk-image-alt-editor' ), 403 );
		}

		$filters = $this->filters_from_request( 'post' );

		// Deliberately not sanitize_text_field(): this mirrors what the Media
		// Library's own ALT field stores, so values written here match values
		// typed there byte for byte.
		$raw_alt = isset( $_POST['wpbiae_alt_text'] ) ? $_POST['wpbiae_alt_text'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$alt     = WPBIAE_Updater::clean( $raw_alt );

		$apply_all = ! empty( $_POST['wpbiae_apply_all'] ) && '1' === (string) $_POST['wpbiae_apply_all'];

		if ( $apply_all ) {
			$ids = WPBIAE_Query::all_ids( $filters );
		} else {
			$ids = isset( $_POST['wpbiae_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['wpbiae_ids'] ) ) : array();
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );

		if ( empty( $ids ) ) {
			$this->redirect( $filters, array( 'wpbiae_msg' => 'noselection' ) );
		}

		$result = WPBIAE_Updater::apply( $ids, $alt );

		$args = array(
			'wpbiae_msg' => 'applied',
			'updated'    => $result['updated'],
			'unchanged'  => $result['unchanged'],
			'skipped'    => $result['skipped'],
			'failed'     => $result['failed'],
		);

		$token = WPBIAE_Updater::store_undo( $result['previous'] );

		if ( $token ) {
			$args['undo'] = $token;
		}

		$this->redirect( $filters, $args );
	}

	/**
	 * Process an undo link, then redirect.
	 */
	private function handle_undo() {
		if ( empty( $_GET['wpbiae_undo'] ) ) {
			return;
		}

		$token = sanitize_key( wp_unslash( $_GET['wpbiae_undo'] ) );

		check_admin_referer( 'wpbiae_undo_' . $token );

		if ( ! current_user_can( WPBIAE_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to edit media on this site.', 'bulk-image-alt-editor' ), 403 );
		}

		$map      = WPBIAE_Updater::take_undo( $token );
		$restored = WPBIAE_Updater::restore( $map );

		$this->redirect(
			$this->filters_from_request( 'get' ),
			array(
				'wpbiae_msg' => $map ? 'undone' : 'undo_expired',
				'restored'   => $restored,
			)
		);
	}

	/**
	 * Redirect back to this screen, preserving the current filter.
	 *
	 * @param array $filters Screen filters.
	 * @param array $extra   Extra query args.
	 */
	private function redirect( array $filters, array $extra = array() ) {
		$args = array(
			'page'       => WPBIAE_SLUG,
			'alt_filter' => $filters['alt'],
			'orderby'    => $filters['orderby'],
			'order'      => $filters['order'],
			'paged'      => $filters['paged'],
		);

		if ( '' !== $filters['search'] ) {
			$args['s'] = $filters['search'];
		}

		wp_safe_redirect( add_query_arg( array_merge( $args, $extra ), admin_url( 'upload.php' ) ) );
		exit;
	}

	/**
	 * Pull notice data off the URL, then strip it so the table's own links
	 * (pagination, sorting, views) do not carry it around.
	 */
	private function read_notice() {
		$keys = array( 'wpbiae_msg', 'updated', 'unchanged', 'skipped', 'failed', 'undo', 'restored', '_wpnonce' );

		if ( isset( $_GET['wpbiae_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->notice = array(
				'msg'       => sanitize_key( wp_unslash( $_GET['wpbiae_msg'] ) ), // phpcs:ignore WordPress.Security.NonceVerification
				'updated'   => isset( $_GET['updated'] ) ? (int) $_GET['updated'] : 0, // phpcs:ignore WordPress.Security.NonceVerification
				'unchanged' => isset( $_GET['unchanged'] ) ? (int) $_GET['unchanged'] : 0, // phpcs:ignore WordPress.Security.NonceVerification
				'skipped'   => isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0, // phpcs:ignore WordPress.Security.NonceVerification
				'failed'    => isset( $_GET['failed'] ) ? (int) $_GET['failed'] : 0, // phpcs:ignore WordPress.Security.NonceVerification
				'restored'  => isset( $_GET['restored'] ) ? (int) $_GET['restored'] : 0, // phpcs:ignore WordPress.Security.NonceVerification
				'undo'      => isset( $_GET['undo'] ) ? sanitize_key( wp_unslash( $_GET['undo'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			);
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = remove_query_arg( $keys, wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore
		}
	}

	/**
	 * Print the admin notice for the last action.
	 */
	private function print_notice() {
		if ( empty( $this->notice['msg'] ) ) {
			return;
		}

		$n = $this->notice;

		if ( 'noselection' === $n['msg'] ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'Nothing was changed: no images were selected.', 'bulk-image-alt-editor' )
			);

			return;
		}

		if ( 'undone' === $n['msg'] ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: number of images. */
						_n( 'Undone. %s image restored to its previous ALT text.', 'Undone. %s images restored to their previous ALT text.', $n['restored'], 'bulk-image-alt-editor' ),
						number_format_i18n( $n['restored'] )
					)
				)
			);

			return;
		}

		if ( 'undo_expired' === $n['msg'] ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'That undo link has already been used or has expired.', 'bulk-image-alt-editor' )
			);

			return;
		}

		if ( 'applied' !== $n['msg'] ) {
			return;
		}

		$parts = array();

		$parts[] = sprintf(
			/* translators: %s: number of images. */
			_n( '%s image updated.', '%s images updated.', $n['updated'], 'bulk-image-alt-editor' ),
			number_format_i18n( $n['updated'] )
		);

		if ( $n['unchanged'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: number of images. */
				_n( '%s already had this exact ALT text.', '%s already had this exact ALT text.', $n['unchanged'], 'bulk-image-alt-editor' ),
				number_format_i18n( $n['unchanged'] )
			);
		}

		if ( $n['skipped'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: number of images. */
				_n( '%s skipped (not an image you can edit).', '%s skipped (not images you can edit).', $n['skipped'], 'bulk-image-alt-editor' ),
				number_format_i18n( $n['skipped'] )
			);
		}

		if ( $n['failed'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %s: number of images. */
				_n( '%s failed to save.', '%s failed to save.', $n['failed'], 'bulk-image-alt-editor' ),
				number_format_i18n( $n['failed'] )
			);
		}

		$class = ( $n['failed'] > 0 ) ? 'notice-error' : 'notice-success';

		$undo = '';

		if ( '' !== $n['undo'] && $n['updated'] > 0 ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'page'        => WPBIAE_SLUG,
						'wpbiae_undo' => $n['undo'],
					),
					admin_url( 'upload.php' )
				),
				'wpbiae_undo_' . $n['undo']
			);

			$undo = ' <a href="' . esc_url( $url ) . '" class="button button-small">' .
				esc_html__( 'Undo this change', 'bulk-image-alt-editor' ) . '</a>';
		}

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s%3$s</p></div>',
			esc_attr( $class ),
			esc_html( implode( ' ', $parts ) ),
			$undo // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url/esc_html above.
		);
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		if ( ! current_user_can( WPBIAE_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to edit media on this site.', 'bulk-image-alt-editor' ), 403 );
		}

		$filters = $this->filters_from_request( 'get' );

		$table = new WPBIAE_List_Table( $filters );
		$table->prepare_items();

		$total = $table->total_items();
		?>
		<div class="wrap wpbiae-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Bulk Image ALT Editor', 'bulk-image-alt-editor' ); ?></h1>
			<hr class="wp-header-end" />

			<?php $this->print_notice(); ?>

			<p class="wpbiae-intro">
				<?php esc_html_e( 'Type the ALT text you want, tick the images that should receive it, then press Apply. The text you type becomes the complete new ALT value - nothing is added before or after it, and images you do not tick are left untouched.', 'bulk-image-alt-editor' ); ?>
			</p>

			<?php $table->views(); ?>

			<form method="get" class="wpbiae-search-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( WPBIAE_SLUG ); ?>" />
				<input type="hidden" name="alt_filter" value="<?php echo esc_attr( $filters['alt'] ); ?>" />
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $filters['orderby'] ); ?>" />
				<input type="hidden" name="order" value="<?php echo esc_attr( $filters['order'] ); ?>" />
				<?php $table->search_box( __( 'Search images', 'bulk-image-alt-editor' ), 'wpbiae-search' ); ?>
			</form>

			<form method="post" id="wpbiae-form" action="<?php echo esc_url( admin_url( 'upload.php?page=' . WPBIAE_SLUG ) ); ?>">
				<?php wp_nonce_field( 'wpbiae_apply', 'wpbiae_nonce' ); ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( WPBIAE_SLUG ); ?>" />
				<input type="hidden" name="alt_filter" value="<?php echo esc_attr( $filters['alt'] ); ?>" />
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $filters['orderby'] ); ?>" />
				<input type="hidden" name="order" value="<?php echo esc_attr( $filters['order'] ); ?>" />
				<input type="hidden" name="paged" value="<?php echo esc_attr( $filters['paged'] ); ?>" />
				<input type="hidden" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" />
				<input type="hidden" name="wpbiae_apply_all" id="wpbiae-apply-all" value="0" />
				<input type="hidden" id="wpbiae-total-matching" value="<?php echo esc_attr( $total ); ?>" />

				<div class="wpbiae-bar">
					<label for="wpbiae-alt-text" class="wpbiae-bar-label">
						<?php esc_html_e( 'New ALT text', 'bulk-image-alt-editor' ); ?>
					</label>
					<input type="text" name="wpbiae_alt_text" id="wpbiae-alt-text" class="regular-text wpbiae-alt-input"
						value="" autocomplete="off"
						placeholder="<?php esc_attr_e( 'e.g. Blue ceramic coffee mug on a wooden table', 'bulk-image-alt-editor' ); ?>" />
					<button type="submit" name="wpbiae_apply" value="1" class="button button-primary wpbiae-apply">
						<?php esc_html_e( 'Apply to selected images', 'bulk-image-alt-editor' ); ?>
					</button>
					<span class="wpbiae-count" id="wpbiae-count" aria-live="polite"></span>
				</div>

				<div class="wpbiae-selectall" id="wpbiae-selectall" hidden>
					<span class="wpbiae-selectall-text"></span>
					<a href="#" class="wpbiae-selectall-link"></a>
				</div>

				<?php $table->display(); ?>

				<div class="wpbiae-bar wpbiae-bar-bottom">
					<button type="submit" name="wpbiae_apply" value="1" class="button button-primary wpbiae-apply">
						<?php esc_html_e( 'Apply to selected images', 'bulk-image-alt-editor' ); ?>
					</button>
					<span class="wpbiae-count-bottom" aria-hidden="true"></span>
				</div>
			</form>
		</div>
		<?php
	}
}
