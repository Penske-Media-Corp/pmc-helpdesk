<?php
/**
 * Default form and handler for PMC_Helpdesk
 * @see PMC_Helpdesk
 */
class PMC_Helpdesk_Default_Form {
	/**
	 * Holds the "From" name for filtering wp_mail_from_name
	 */
	public static $from_name = null;

	/**
	 * Holds the "From" email address for filtering wp_mail_from
	 */
	public static $from_email = null;

	/**
	 * Adds fields and form handler to PMC_Helpdesk
	 */
	public static function add_defaults() {
		add_filter( 'pmc-helpdesk-form-fields', array( 'PMC_Helpdesk_Default_Form', 'form_field_html' ) );

		add_action( 'pmc-helpdesk-form-handler', array( 'PMC_Helpdesk_Default_Form', 'form_handler' ) );
	}

	/**
	 * Helper method for validating and sanitizing form field data
	 * @see PMC_Helpdesk_Default_Form::form_handler()
	 * @param $fields array Raw field data
	 * @return $data array Cleaned field data
	 */
	public static function process_fields( $fields ) {
		$data = array(
			'priority' => (isset($fields['priority'])) ? sanitize_text_field( $fields['priority'] ) : 'N/A',
			'user_agent' => (isset($fields['user_agent'])) ? sanitize_text_field( $fields['user_agent'] ) : 'N/A',
			'referrer' => (isset($fields['referrer'])) ? esc_url_raw( $fields['referrer'] ) : 'N/A',
			'request_uri' => (isset($fields['request_uri'])) ? esc_url_raw( $fields['request_uri'] ) : 'N/A',
			'ip_address' => (isset($fields['ip_address'])) ? preg_replace( '~[^0-9\.]~', '', $fields['ip_address'] ) : 'N/A',
			'timestamp' => (isset($fields['timestamp'])) ? sanitize_text_field( $fields['timestamp'] ) : 'N/A',
			'message' => (isset($fields['message'])) ? sanitize_text_field( $fields['message'] ) : 'N/A',
			'description' => (isset($fields['description'])) ? sanitize_text_field( $fields['description'] ) : 'N/A',
		);

		if ( isset($fields['user_id']) ) {
			$user_info = get_userdata( intval( $fields['user_id'] ) );

			if ( $user_info ) {
				self::$from_name = $user_info->display_name;
				self::$from_email = $user_info->user_email;

				add_filter( 'wp_mail_from', function( $from_email ) {
					return PMC_Helpdesk_Default_Form::$from_email;
				} );

				add_filter( 'wp_mail_from_name', function( $from_name ) {
					return PMC_Helpdesk_Default_Form::$from_name;
				} );

				$data['username'] = $user_info->user_login;
			}
		}

		if ( isset($fields['current_screen']) ) {
			$data['current_screen_action'] = ($fields['current_screen']['action']) ? sanitize_text_field( $fields['current_screen']['action'] ) : 'N/A';

			$data['current_screen_base'] = ($fields['current_screen']['base']) ? sanitize_text_field( $fields['current_screen']['base'] ) : 'N/A';

			$data['current_screen_id'] = ($fields['current_screen']['id']) ? sanitize_text_field( $fields['current_screen']['id'] ) : 'N/A';

			$data['current_screen_post_type'] = ($fields['current_screen']['post_type']) ? sanitize_text_field( $fields['current_screen']['post_type'] ) : 'N/A';

			$data['current_screen_taxonomy'] = ($fields['current_screen']['taxonomy']) ? sanitize_text_field( $fields['current_screen']['taxonomy'] ) : 'N/A';

			$data['current_screen_is_network'] = ($fields['current_screen']['is_network']) ? sanitize_text_field( $fields['current_screen']['is_network'] ) : 'N/A';

			$data['current_screen_is_user'] = ($fields['current_screen']['is_user']) ? sanitize_text_field( $fields['current_screen']['is_user'] ) : 'N/A';

			$data['current_screen_parent_base'] = ($fields['current_screen']['parent_base']) ? sanitize_text_field( $fields['current_screen']['parent_base'] ) : 'N/A';

			$data['current_screen_parent_file'] = ($fields['current_screen']['parent_file']) ? sanitize_text_field( $fields['current_screen']['parent_file'] ) : 'N/A';
		}

		if ( isset($fields['post_id']) ) {
			$data['post_id'] = intval( $fields['post_id'] );
			$data['post_link'] = site_url( '/?p=' . $data['post_id'] );
			$data['post_admin_link'] = admin_url( 'post.php?post=' . $data['post_id'] . '&action=edit' );
			$post = get_post( $data['post_id'] );
			if ( $post ) {
				$data['post_title'] = $post->post_type;
				$data['post_slug'] = $post->post_name;

				$author_info = get_userdata( $post->post_author );
				$data['post_author'] = ($author_info) ? $author_info->user_login : 'User #' . $post->post_author;

				$data['post_type'] = $post->post_type;
				$data['post_date'] = $post->post_date;
				$data['post_modified'] = $post->post_modified;
				$data['post_status'] = $post->post_status;
			}
		}

		// Allow plugins to process additional fields added via the 'pmc-helpdesk-form-fields' filter
		$data = apply_filters( 'pmc-helpdesk-process-additional-fields', $data );

		return $data;
	}

	public static function _process_user_id( $user_id, $fields ) {

	}

	/**
	 * Helper method for taking form fields and sending them in an e-mail.
	 * @see PMC_Helpdesk_Default_Form::form_handler()
	 * @param $fields array Dictionary of form field data.
	 */
	public static function send_email( $fields ) {
		// Default: Email to the site administrator
		$default_to = get_bloginfo( 'admin_email' );

		// User override
		$to = apply_filters( 'pmc-helpdesk-form-to', $default_to );

		// Format the default $to address.
		// Only do this if the $to address wasn't changed. Otherwise, assume whoever filtered the $to address set the address exactly how they wanted.
		if ( $to === $default_to ) {
			$admin_user = get_user_by( 'email', $to );
			$to = $admin_user->display_name . ' <' . $to . '>';
		}

		$default_subject = sprintf( __( '[%s] Help request - %s priority', 'pmc-helpdesk' ), get_bloginfo('name'), $fields['priority'] );
		$subject = apply_filters( 'pmc-helpdesk-form-subject', $default_subject );

		$message = '';
		foreach ( $fields as $key => $value ) {
			$message .= $key . ': ' . $value . "\r\n\r\n";
		}
		$message = apply_filters( 'pmc-helpdesk-form-message-body', $message, $fields );

		// Default "From" should be the end user's e-mail address.
		// We're going to add a "Sender" header to the e-mail so that sites can use a valid sender to alleviate spam. To that end, we'll get the default Sender the way wp_mail() gets the default "From", then apply filters so plugins can override it with a valid Sender address.  We're maintaining the "wp_mail_from" for the Sender under the assumption that professional WordPress installs will be hooking into that filter already with valid settings.
		// Get the site domain and get rid of www.
		$sitename = strtolower( $_SERVER['SERVER_NAME'] );
		if ( substr( $sitename, 0, 4 ) == 'www.' ) {
			$sitename = substr( $sitename, 4 );
		}
		$default_from = 'wordpress@' . $sitename;

		$headers = array(
			'Sender' => apply_filters( 'wp_mail_from' , $default_from ),
		);

		$headers = apply_filters( 'pmc-helpdesk-form-headers', $headers );

		$attachments = apply_filters( 'pmc-helpdesk-form-attachments', array() );

		wp_mail( $to, $subject, $message, $headers, $attachments );
	}

	/**
	 * Takes form data and does something with it (in this case, sends an e-mail).
	 * @see PMC_Helpdesk::ajax_process_form()
	 * @param $args array Args passed from action "pmc-helpdesk-form-handler"
	 */
	public static function form_handler( $args ) {
		$fields = array();
		if ( isset($args['fields']) ) {
			$fields = self::process_fields( $args['fields'] );
		}

		self::send_email( $fields );
	}

	/**
	 * Adds form fields for default form
	 * @see PMC_Helpdesk::form_html()
	 * @param $form_field_html string Any existing form HTML
	 * @return $form_field_html string Complete form HTML
	 */
	public static function form_field_html( $form_field_html ) {
		ob_start();

		$current_user = wp_get_current_user();
		?>
		<input type="hidden" name="user_id" value="<?php echo esc_attr( $current_user->ID ); ?>" />

		<input type="hidden" name="request_uri" value="<?php echo esc_attr( $_SERVER['REQUEST_URI'] ); ?>" />

		<input type="hidden" name="user_agent" value="<?php echo esc_attr( $_SERVER['HTTP_USER_AGENT'] ); ?>" />

		<?php
		$referrer = ( isset($_SERVER['HTTP_REFERER']) ) ? $_SERVER['HTTP_REFERER'] : '';
		?>
		<input type="hidden" name="referrer" value="<?php echo esc_attr( $referrer ); ?>" />

		<?php
		if ( is_admin() ) {
			$current_screen = get_current_screen();
			if ( is_object($current_screen) ) {
				?>
				<input type="hidden" name="current_screen[action]" value="<?php echo esc_attr( $current_screen->action ); ?>" />

				<input type="hidden" name="current_screen[base]" value="<?php echo esc_attr( $current_screen->base ); ?>" />

				<input type="hidden" name="current_screen[id]" value="<?php echo esc_attr( $current_screen->id ); ?>" />

				<input type="hidden" name="current_screen[post_type]" value="<?php echo esc_attr( $current_screen->post_type ); ?>" />

				<input type="hidden" name="current_screen[taxonomy]" value="<?php echo esc_attr( $current_screen->taxonomy ); ?>" />

				<input type="hidden" name="current_screen[is_network]" value="<?php echo esc_attr( $current_screen->is_network ); ?>" />

				<input type="hidden" name="current_screen[is_user]" value="<?php echo esc_attr( $current_screen->is_user ); ?>" />

				<input type="hidden" name="current_screen[parent_base]" value="<?php echo esc_attr( $current_screen->parent_base ); ?>" />

				<input type="hidden" name="current_screen[parent_file]" value="<?php echo esc_attr( $current_screen->parent_file ); ?>" />
				<?php
			}
		}

		$post_id = 0;
		if ( isset($GLOBALS['post']->ID) ) {
			$post_id = $GLOBALS['post']->ID;
		} elseif ( isset($GLOBALS['post']['ID']) ) {
			$post_id = $GLOBALS['post']['ID'];
		}
		if ( $post_id ) {
			?>
			<input type="hidden" name="post_id" value="<?php echo intval( $post_id ); ?>" />
			<?php
		}
		?>
		<input type="hidden" name="ip_address" value="<?php echo esc_attr( $_SERVER['REMOTE_ADDR'] ); ?>" />

		<input type="hidden" name="timestamp" value="<?php echo esc_attr( date('Y-m-d H:i:s T') ); ?>" />

		<p><?php _e( 'What problem did you experience?', 'pmc-helpdesk' ); ?></p>
		<textarea name="message"></textarea>

		<p><?php _e( 'Steps to reproduce?', 'pmc-helpdesk' ); ?></p>
		<textarea name="description"></textarea>

		<?php
		/*
		 * This uses normal language because people shouldn't be expected to understand the difference between low/normal/high/urgent priorities.
		 * It's a dropdown menu to save space.
		 */
		?>
		<p><select name="priority">
			<option value="low"><?php _e( 'Look into this when you get a chance.', 'pmc-helpdesk' ); ?></option>
			<option value="normal"><?php _e( 'This is impacting my work, but I am able to work around it.', 'pmc-helpdesk' ); ?></option>
			<option value="urgent"><?php _e( 'This needs immediate attention; wake people up if necessary.', 'pmc-helpdesk' ); ?></option>
		</select></p>

		<?php

		$form_field_html .= ob_get_clean();

		return $form_field_html;
	}
}

//EOF