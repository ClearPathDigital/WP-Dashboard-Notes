<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WPDN_Ajax.
 *
 * Class to handle all AJAX calls.
 *
 * @class		WPDN_Ajax
 * @version		1.0.0
 * @package		WP Dashboard Notes
 * @author		Jeroen Sormani
 */
class WPDN_Ajax {


	/**
	 * Constructor.
	 *
	 * Add ajax actions.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Update note
		add_action( 'wp_ajax_wpdn_update_note', array( $this, 'wpdn_update_note' ) );
		add_action( 'wp_ajax_wpdn_toggle_note', array( $this, 'wpdn_toggle_note' ) );

		// Add / Delete note
		add_action( 'wp_ajax_wpdn_add_note', array( $this, 'wpdn_add_note' ) );
		add_action( 'wp_ajax_wpdn_delete_note', array( $this, 'wpdn_delete_note' ) );

	}


	/**
	 * Update note.
	 *
	 * Update note + meta when the jQuery update trigger is triggered.
	 *
	 * @since 1.0.0
	 */
	public function wpdn_update_note() {

		// Bail if user cannot save posts
		if ( ! current_user_can( 'edit_posts' ) ) :
			die();
		endif;

		$post_id			= absint( $_POST['post_id'] );
		$permissions_form	= wp_parse_args( $_POST['permissions_form'] );
		$user_permissions	= (array) $permissions_form['user_permission'];
		array_walk( $user_permissions['user_role'], 'sanitize_text_field' );

		$post = array(
			'ID'			=> $post_id,
			'post_title'	=> sanitize_title( $_POST['post_title'] ),
			'post_author'	=> get_current_user_id(),
			'post_content'	=> sanitize_text_field( $_POST['post_content'] ),
		);
		wp_update_post( $post );

		$note_meta = array(
			'color'			=> sanitize_text_field( $_POST['note_color'] ),
			'color_text'	=> sanitize_text_field( $_POST['note_color_text'] ),
			'visibility'	=> sanitize_text_field( $_POST['note_visibility'] ),
			'note_type'		=> sanitize_text_field( $_POST['note_type'] ),
		);
		update_post_meta( $post_id, '_note', $note_meta );
		update_post_meta( $post_id, '_role_permissions', $user_permissions['user_role'] );

		die();

	}


	/**
	 * Toggle note.
	 *
	 * Toggle note type, from 'regular note' to 'list note' or vice versa.
	 *
	 * @since 1.0.0
	 */
	public function wpdn_toggle_note() {

		$note				= get_post( absint( $_POST['post_id'] ) );
		$role_permissions 	= get_post_meta( $note->ID, '_role_permissions', true );
		$content			= apply_filters( 'wpdn_content', $note->post_content );
		$colors				= apply_filters( 'wpdn_colors', array(
			'white'		=> '#fff',
			'red'		=> '#f7846a',
			'orange'	=> '#ffbd22',
			'yellow'	=> '#eeee22',
			'green'		=> '#bbe535',
			'blue'		=> '#66ccdd',
			'black'		=> '#777777',
		) );
		$note_meta = WP_Dashboard_Notes::wpdn_get_note_meta( $note->ID );

		?><style>
			#note_<?php echo $note->ID; ?>, #note_<?php echo $note->ID; ?> .visibility-settings { background-color: <?php echo $note_meta['color']; ?>; }
			#note_<?php echo $note->ID; ?> .hndle { border: none; }
		</style><?php

		if ( $_POST['note_type'] == 'regular' ) :
			require plugin_dir_path( __FILE__ ) . 'templates/note.php';
		else :
			require plugin_dir_path( __FILE__ ) . 'templates/note-list.php';
		endif;

		die();

	}


	/**
	 * Add new note.
	 *
	 * Create a new note, return two variables (post ID | note content) to jQuery through json_encode.
	 *
	 * @since 1.0.0
	 */
	public function wpdn_add_note() {

		$args = array(
			'post_status'	=> 'publish',
			'post_type'		=> 'note',
			'post_title'	=> __( 'New note', 'wp-dashboard-notes' ),
		);
		$post_id = wp_insert_post( $args );

		$note		= (object) array( 'ID' => $post_id, 'post_content' => '' );
		$note_meta	= apply_filters( 'wpdn_new_note_meta', array(
			'color'			=> '#ffffff',
			'color_text'	=> 'white',
			'visibility'	=> 'Everyone',
			'note_type'		=> 'list',
		) );
		$content	= apply_filters( 'wpdn_content', $note->post_content );
		$colors		= apply_filters( 'wpdn_colors', array(
			'white'		=> '#fff',
			'red'		=> '#f7846a',
			'orange'	=> '#ffbd22',
			'yellow'	=> '#eeee22',
			'green'		=> '#bbe535',
			'blue'		=> '#66ccdd',
			'black'		=> '#777777',
		) );
		$note_meta = apply_filters( 'wpdn_new_note_meta', $note_meta );
		update_post_meta( $post_id, '_note', $note_meta );

		ob_start();

		?><div id='note_<?php echo $post_id; ?>' class='postbox'>
			<div class='handlediv' title='Click to toggle'><br></div>
			<h3 class="hndle">
				<span>
					<span contenteditable="true" class="wpdn-title"><?php _e( 'New note', 'wp-dashboard-notes' ); ?></span>
					<div class="wpdn-edit-title dashicons dashicons-edit"></div>
					<span class="status"></span>
				</span>
			</h3>

			<div class='inside'>

			<style>
				#note_<?php echo $post_id; ?> { background-color: <?php echo $note_meta['color']; ?>; }
				#note_<?php echo $post_id; ?> .hndle { border: none; }
			</style><?php

				if ( 'regular' == $note_meta['note_type'] ) :
					require plugin_dir_path( __FILE__ ) . 'templates/note.php';
				else :
					require plugin_dir_path( __FILE__ ) . 'templates/note-list.php';
				endif;

			?></div> <!-- .inside -->
		</div> <!-- .postbox --><?php

		$return['note']		= ob_get_clean();
		$return['post_id']	= $post_id;

		echo json_encode( $return );

		die();

	}


	/**
	 * Delete note.
	 *
	 * Post is trashed and not permanently deleted.
	 *
	 * @since 1.0.0
	 */
	public function wpdn_delete_note() {

		wp_trash_post( absint( $_POST['post_id'] ) );
		die();

	}


}
