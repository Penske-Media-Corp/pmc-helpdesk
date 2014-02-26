<?php
/**
 * Adds a form to the admin bar where users can ask for help
 *
 */
class PMC_Helpdesk {
	/**
	 * Plugin version
	 * Primarily for passing a cachbuster to scripts and styles.
	 */
	const version = '1.0';

	/**
	 * Holds the current object singleton
	 */
	protected static $_instance = array();

	/**
	 * Prevent direct object creation
	 */
	final private function  __construct() {}

	/**
	 * Prevent object cloning
	 */
	final private function  __clone() {}

	/**
	 * Returns new or existing singleton instance
	 *
	 * @return obj self::$_instance
	 */
	final public static function get_instance() {
		/*
		 * If you extend this class, self::$_instance will be part of the base class.
		 * In the sinlgeton pattern, if you have multiple classes extending this class,
		 * self::$_instance will be overwritten with the most recent class instance
		 * that was instantiated.  Thanks to late static binding we use get_called_class()
		 * to grab the caller's class name, and store a key=>value pair for each
		 * classname=>instance in self::$_instance for each subclass.
		 */
		$class = get_called_class();
		if ( ! isset( self::$_instance[$class] ) ) {
			self::$_instance[$class] = new $class();

			// Run's the class's _init() method, where the class can hook into actions and filters, and do any other initialization it needs
			self::$_instance[$class]->_init();
		}

		return self::$_instance[$class];
	}

	/**
	 * Plugin initializer
	 * Called once, the first time the singleton instance is retrieved.
	 * Essentially a constructor for singleton objects, but allows cleaner code by separating out object initialization from singleton pattern cruft.
	 */
	protected function _init() {
		add_action( 'init', array( $this, 'load_plugin_textdomain') );

		if ( defined('DOING_AJAX') && DOING_AJAX ) {
			add_action( 'wp_ajax_pmc-helpdesk-form', array( $this, 'ajax_process_form' ) );
		} else {
			add_action( 'init', array( $this, 'enqueue') );
			add_action( 'admin_bar_init', array( $this, 'admin_bar_init' ) );
		}

	}

	/**
	 * Helper for loading the plugin's textdomain
	 * The textdomain is used for translation.
	 */
	public function load_plugin_textdomain() {
		$textdomain_path = dirname( plugin_basename( PMC_HELPDESK_BASE_PATH ) ) . '/languages/';
		load_plugin_textdomain( 'pmc-helpdesk', false, $textdomain_path );
	}

	/**
	 * Enqueue plugin styles, scripts, and script localization
	 */
	public function enqueue() {
		wp_enqueue_style( 'pmc-helpdesk', plugins_url( 'css/menu.css', PMC_HELPDESK_BASE_PATH ), array( 'admin-bar' ), self::version );
		wp_enqueue_script( 'pmc-helpdesk', plugins_url( 'js/menu.js', PMC_HELPDESK_BASE_PATH ), array( 'jquery' ), self::version );

		wp_localize_script( 'pmc-helpdesk', 'pmc_helpdesk_vars', array(
			'_ajax_url' => admin_url( 'admin-ajax.php' ),
			'_activity_indicator_url' => admin_url( 'images/wpspin_light.gif' ),
			'affirm' => __( 'Okay', 'pmc-helpdesk' ),
		) );
	}

	/**
	 * Are we on the wp-login.php page?
	 * We can get here while logged in and break the page as the admin bar isn't shown and otherthings the js relies on aren't available.
	 * Shamelessly cribbed from the Debug Bar plugin
	 * @see http://wordpress.org/extend/plugins/debug-bar/
	 * @return bool
	 */
	public function is_wp_login() {
		return ( 'wp-login.php' === basename( $_SERVER['SCRIPT_NAME'] ) );
	}

	/**
	 * Tell the admin bar that we're going to be doing stuff
	 */
	public function admin_bar_init() {
		if ( ! current_user_can( 'edit_posts' ) || ! is_admin_bar_showing() || $this->is_wp_login() ) {
			return;
		}

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 1000 );
	}

	/**
	 * Add the form node to the admin bar
	 * @see PMC_Helpdesk::admin_bar_init()
	 */
	public function admin_bar_menu() {
		$menu_args = array(
			'id' => 'pmc-helpdesk',
			'title' => __('Help Desk', 'pmc-helpdesk'),
			'parent' => 'top-secondary',
			'href' => '#',
			'meta' => array(
				'title' => esc_html__( 'Please help.', 'pmc-helpdesk' ),
				'html' => $this->form_html(),
				'onclick' => 'pmc_helpdesk.toggle_form();',
			),
		);

		$menu_args = apply_filters( 'pmc-helpdesk-menu-args', $menu_args );

		$GLOBALS['wp_admin_bar']->add_node( $menu_args );
	}

	/**
	 * Ajax form handler
	 * Processes the form fields that were rendered via PMC_Helpdesk::form_html()
	 * @see PMC_Helpdesk::form_html()
	 */
	public function ajax_process_form() {
		// Bail early if we don't have expected data
		if ( ! isset($_POST['fields']) ) {
			return;
		}

		check_ajax_referer( 'pmc-helpdesk-form', 'pmc_helpdesk_nonce' );

		parse_str( $_POST['fields'], $fields );

		$success = true;
		$response = __( 'Thank you, the support team has been notified.', 'pmc-helpdesk' );

		// Action observers should not send any output directly (e.g., echo, print, wp_send_json(), die(), etc).  Set 'success' to indicate success or failure, use 'response' to override the response text.
		do_action( 'pmc-helpdesk-form-handler', array(
			'fields' => $fields,
			'success' => &$success,
			// We're passing response text to this action instead of using a filter so that the response text may be modified in response to any processing that happens within action observers.
			'response' => &$response,
		) );

		if ( true === $success ) {
			wp_send_json_success( $response );
		} else {
			wp_send_json_error( $response );
		}
	}

	/**
	 * Outputs form HTML
	 * HTML string is passed to admin bar args.  Form data gets processed by PMC_Helpdesk::ajax_process_form()
	 * @see PMC_Helpdesk::admin_bar_menu()
	 * @see PMC_Helpdesk::ajax_process_form()
	 * @return $output string
	 */
	public function form_html() {
		ob_start();
		?>
		<div id="pmc-helpdesk-form-wrapper" style="display: none;">
			<form name="pmc-helpdesk-form">
				<?php
				wp_nonce_field( 'pmc-helpdesk-form', 'pmc_helpdesk_nonce' );

				echo apply_filters( 'pmc-helpdesk-form-fields', '' );
				?>
				<p><input type="submit" onclick="pmc_helpdesk.send();" class="button button-primary" value="<?php echo esc_attr( __( 'Send', 'pmc-helpdesk' ) ); ?>" />&nbsp;<a onclick="pmc_helpdesk.toggle_form();"><?php echo esc_html( __( 'Cancel', 'pmc-helpdesk' ) ); ?></a></p>
			</form>
			<div id="pmc-helpdesk-background-activity-container" style="display: none;"></div>
		</div>
		<?php
		$output = ob_get_clean();
		return $output;
	}
}

//EOF