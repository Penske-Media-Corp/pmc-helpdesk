<?php
/*
Plugin Name:  PMC Helpdesk
Plugin URI:   https://github.com/Penske-Media-Corp/pmc-helpdesk
Description:  Adds a form to the admin bar where authenticated users can ask for help
Version:      1.0
Author:       PMC, mintindeed
Author URI:   http://www.pmc.com
License:      GPLv2 or later

Text Domain:  pmc-helpdesk
Domain Path:  /languages/
*/

/*
 * Defines
 */
define( 'PMC_HELPDESK_BASE_PATH', __FILE__ );

// Allow themes and plugins to override the default form
if ( ! defined('PMC_HELPDESK_USE_DEFAULT_FORM') ) {
	define( 'PMC_HELPDESK_USE_DEFAULT_FORM', true );
}

/*
 * Initialize core plugin
 */
require_once __DIR__ . '/classes/class-pmc-helpdesk.php';
PMC_Helpdesk::get_instance();

/*
 * Default form & form handler
 */
if ( PMC_HELPDESK_USE_DEFAULT_FORM ) {
	require_once __DIR__ . '/classes/class-pmc-helpdesk-default-form.php';
	PMC_Helpdesk_Default_Form::add_defaults();
}

//EOF