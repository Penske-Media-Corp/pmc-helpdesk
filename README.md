PMC Helpdesk
============

Adds a form to the admin bar where authenticated users can ask for help

Overview
--------

The adds a "Help Desk" button to the admin bar, which allows users to contact the site administrator quickly and easily.  The form automatically collects commonly-requested debug information, such as browser version and which page the user was on when requesting help.

Only read further if you're looking to dig into the code to customize or extend this plugin.

Filter reference
----------------

### pmc-helpdesk-menu-args

Override the plugin's menu args.

	$menu_args = apply_filters( 'pmc-helpdesk-menu-args', $menu_args );

### pmc-helpdesk-form-fields

Render form field HTML in the menu node for the user to fill out or interact with.  Form fields rendered via this filter will be processed by actions hooked into `pmc-helpdesk-form-handler`.

	echo apply_filters( 'pmc-helpdesk-form-fields', '' );

### pmc-helpdesk-form-error

The only time we'll show an error is if there's a technical problem — e.g., the ajax response doesn't have all the data or proper permissions.

	$error_response = apply_filters( 'pmc-helpdesk-form-error', __( 'Something went wrong. Please contact support through your normal channels and let them know about this problem.', 'pmc-helpdesk' ) );

Action reference
----------------

### pmc-helpdesk-form-handler

Implement a form handler for intercepting POST requests via ajax.  Used in concert with the `pmc-helpdesk-form-fields` filter.

		do_action( 'pmc-helpdesk-form-handler', array(
			'fields' => $fields,
			'success' => &$success,
			'response' => &$response,
		) );

Notes:

* Action observers should not send any output directly (e.g., echo, print, wp_send_json(), die(), etc).  Set 'success' to indicate success or failure, use 'response' to override the response text.
* We're passing response text to this action instead of using a filter so that the response text may be modified in response to any processing that happens within action observers.
* Response text and success flag are passed by reference so any observers may update the values while still allowing the `ajax_process_form()` method to use them when sending the response to screen.

Default form and form handler
-----------------------------

### Override the default "to" email address:

	add_filter( 'pmc-helpdesk-form-to', function( $default_email_address ) {
		$recipient_list = array(
			'user1@example.com',
			'user2@example.com',
		);

		$to_list = array();
		foreach ( $recipient_list as $email_address ) {
			$user = get_user_by( 'email', $email_address );
			if ( $user ) {
				$to_list[] = $user->display_name . ' <' . $email_address . '>';
			} else {
				$to_list[] = $email_address;
			}
		}

		$to = implode( ',', $to_list );

		return $to;
	} );

### Override the default subject:

	add_filter( 'pmc-helpdesk-form-subject', function( $default_subject ) {
		$subject = 'Help! Help! I'm being repressed!';
		return $subject;
	} );

### Customize the e-mail message body

	add_filter( 'pmc-helpdesk-form-message-body', function( $message, $fields ) {
		if ( 'urgent' === $fields['priority'] ) {
			$message = 'It is I, Arthur, son of Uther Pendragon, from the castle of Camelot. King of the Britons, defeater of the Saxons, Sovereign of all England!' . "\r\n" . '...and this is my trusty servant Patsy. We have ridden the length and breadth of the land in search of knights who will join me in my court at Camelot. I must speak with your lord and master.' . "\r\n" . $message;
		}

		return $message;
	}, 10, 2 );

### Customize the e-mail's headers

`$headers` is passed directly to the `wp_mail()` function.

		add_filter( 'pmc-helpdesk-form-headers', function( $headers ) {
			$headers['X-Accept-Language'] = 'en-us, en';
			return $headers;
		} );

### Add attachments to the e-mail

`$attachments` is passed directly to the `wp_mail()` function.

		add_filter( 'pmc-helpdesk-form-attachments', function( $attachments ) {
			$attachments[] = ABSPATH . '/wp-includes/images/blank.gif';
			return $attachments;
		} );

Handling form errors
--------------------

There's no client-side form validation or errors on purpose.  These are users who have already encountered a problem, we don't want to frustrate them further.

Here's an example of how to indicate an error in processing the form.

	add_action( 'pmc-helpdesk-form-handler', function( $args ) {
		$args['success'] = false;

		// This message is really unhelpful. Don't say something like this for real.
		$args['response'] = 'There was a problem. Please try again.';
	} );

The only real difference between `$success = true` and `$success = false` at this point, is the response text is wrapped in `<p class="success">` or `<p class="error">` respectively.

Adding custom fields while using the default form
-------------------------------------------------

If you want to use the default form and form handler, but need to add some additional form fields:

1) Define `PMC_HELPDESK_USE_DEFAULT_FORM` in your theme (optional; it's enabled by default)

	define( 'PMC_HELPDESK_USE_DEFAULT_FORM', true );

2) Hook into the `pmc-helpdesk-form-fields` filter to output your additional fields

	add_filter( 'pmc-helpdesk-form-fields', function( $form_html ) {
		$form_html .= '<input type="hidden" name="request_time" value="" />';
		return $form_html;
	} );

3) Hook into the `pmc-helpdesk-process-additional-fields` action to process your additional fields. These are then merged with the default fields. Form security (nonce and referrer validation) are handled by the PMC_Helpdesk class.

	add_filter( 'pmc-helpdesk-process-additional-fields', function( $field_data ) {
		if ( isset($_POST['fields']['request_time']) ) {
			$field_data['request_time'] = inval( $_POST['fields']['request_time'] );
		}
		return $field_data;
	} );

Adding a custom form
--------------------

1) Add your custom form fields:

	add_filter( 'pmc-helpdesk-form-fields', 'my_custom_form_field_renderer' );

2) Add a handler for your custom form. A handler does something (writes a log, sends an e-mail) when the form is submitted by a user.

	add_action( 'pmc-helpdesk-form-handler', 'my_custom_form_handlerr' );

3) Disable the default form by adding the following constant definition to your theme:

	define( 'PMC_HELPDESK_USE_DEFAULT_FORM', false );

See the `PMC_Helpdesk_Default_Form` class (included) for implementation examples.
