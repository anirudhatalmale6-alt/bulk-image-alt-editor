<?php
/**
 * Writes ALT text to attachments.
 *
 * Everything that changes data lives here, so the admin screen and the Media
 * Library bulk action share one implementation and one set of safety checks.
 *
 * @package Bulk_Image_Alt_Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPBIAE_Updater
 */
class WPBIAE_Updater {

	/**
	 * How many previous values we are willing to keep for the undo snapshot.
	 */
	const UNDO_LIMIT = 2000;

	/**
	 * How long an undo snapshot survives, in seconds.
	 */
	const UNDO_TTL = DAY_IN_SECONDS;

	/**
	 * Clean an ALT value the same way WordPress cleans it in the media modal.
	 *
	 * Core's wp_ajax_save_attachment() runs wp_strip_all_tags( $alt, true ) and
	 * stores the result slashed. Matching that keeps values written here
	 * byte-identical to values typed into the normal Media Library fields.
	 *
	 * @param string $raw Raw, still-slashed value straight out of $_POST.
	 * @return string Unslashed, cleaned ALT text.
	 */
	public static function clean( $raw ) {
		return wp_strip_all_tags( wp_unslash( (string) $raw ), true );
	}

	/**
	 * Replace the ALT text on a set of attachments.
	 *
	 * The value is written as the complete new ALT: no prefix, no suffix, no
	 * merge with what was there before.
	 *
	 * @param int[]  $ids Attachment IDs.
	 * @param string $alt Cleaned ALT text (already run through self::clean()).
	 * @return array {
	 *     @type int   $updated   Images whose stored ALT now equals $alt and previously did not.
	 *     @type int   $unchanged Images that already held exactly this ALT.
	 *     @type int   $skipped   Images the current user may not edit, or that were not images.
	 *     @type int   $failed    Images where the write did not stick.
	 *     @type array $previous  Map of attachment ID => previous ALT, for undo.
	 * }
	 */
	public static function apply( array $ids, $alt ) {
		$result = array(
			'updated'   => 0,
			'unchanged' => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'previous'  => array(),
		);

		$alt = (string) $alt;

		foreach ( $ids as $id ) {
			$id = (int) $id;

			if ( $id <= 0 || ! self::is_editable_image( $id ) ) {
				$result['skipped']++;
				continue;
			}

			$before = (string) get_post_meta( $id, WPBIAE_META_KEY, true );

			if ( $before === $alt ) {
				$result['unchanged']++;
				continue;
			}

			// update_post_meta() expects a slashed value; it unslashes on the way in.
			update_post_meta( $id, WPBIAE_META_KEY, wp_slash( $alt ) );

			// Read it back rather than trusting the return value: update_post_meta()
			// returns false both for "no change" and for a genuine failure.
			$after = (string) get_post_meta( $id, WPBIAE_META_KEY, true );

			if ( $after !== $alt ) {
				$result['failed']++;
				continue;
			}

			if ( count( $result['previous'] ) < self::UNDO_LIMIT ) {
				$result['previous'][ $id ] = $before;
			}

			$result['updated']++;

			/**
			 * Fires after this plugin has replaced the ALT text of an attachment.
			 *
			 * @param int    $id     Attachment ID.
			 * @param string $alt    The new ALT text.
			 * @param string $before The ALT text that was replaced.
			 */
			do_action( 'wpbiae_alt_updated', $id, $alt, $before );
		}

		return $result;
	}

	/**
	 * Restore a set of previous ALT values.
	 *
	 * @param array $map Attachment ID => ALT text to restore.
	 * @return int Number of images restored.
	 */
	public static function restore( array $map ) {
		$restored = 0;

		foreach ( $map as $id => $alt ) {
			$id  = (int) $id;
			$alt = (string) $alt;

			if ( $id <= 0 || ! self::is_editable_image( $id ) ) {
				continue;
			}

			update_post_meta( $id, WPBIAE_META_KEY, wp_slash( $alt ) );

			if ( (string) get_post_meta( $id, WPBIAE_META_KEY, true ) === $alt ) {
				$restored++;
			}
		}

		return $restored;
	}

	/**
	 * Is this an image attachment the current user is allowed to edit?
	 *
	 * The screen itself only needs upload_files, which Authors hold. Authors
	 * cannot edit other people's attachments, so every write is checked here
	 * against the individual post as well.
	 *
	 * @param int $id Attachment ID.
	 * @return bool
	 */
	public static function is_editable_image( $id ) {
		$post = get_post( $id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}

		if ( ! wp_attachment_is_image( $id ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $id );
	}

	/**
	 * Store an undo snapshot for the current user.
	 *
	 * @param array $previous Attachment ID => previous ALT text.
	 * @return string|false Snapshot token, or false when there is nothing to store.
	 */
	public static function store_undo( array $previous ) {
		if ( empty( $previous ) ) {
			return false;
		}

		$token = wp_generate_password( 12, false, false );

		set_transient( self::undo_key( $token ), $previous, self::UNDO_TTL );

		return $token;
	}

	/**
	 * Read and delete an undo snapshot belonging to the current user.
	 *
	 * @param string $token Snapshot token.
	 * @return array Attachment ID => previous ALT text. Empty when expired or unknown.
	 */
	public static function take_undo( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );

		if ( '' === $token ) {
			return array();
		}

		$key  = self::undo_key( $token );
		$data = get_transient( $key );

		delete_transient( $key );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Transient key for an undo snapshot. Scoped to the user who made the change.
	 *
	 * @param string $token Snapshot token.
	 * @return string
	 */
	private static function undo_key( $token ) {
		return 'wpbiae_undo_' . get_current_user_id() . '_' . $token;
	}
}
