<?php
/**
 * Check BP Templates are up to date
 *
 *
 * @package   BuddyPress template checker
 * @author    imath
 * @license   GPL-2.0+
 * @link      http://buddypress.org
 *
 * @buddypress-plugin
 * Plugin Name:       BuddyPress template checker
 * Plugin URI:        https://github.com/imath/bp-template-checker
 * Description:       What if the Activity Post Form was using Backbone?
 * Version:           1.0.0-alpha
 * Author:            imath
 * Author URI:        https://github.com/imath/bp-template-checker
 * Text Domain:       bp-template-checker
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages/
 * GitHub Plugin URI: https://github.com/imath/bp-template-checker
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BP_Template_Checker' ) ) :
/**
 * Main Class
 *
 * @since 1.0.0
 */
class BP_Template_Checker {
	/**
	 * Instance of this class.
	 */
	protected static $instance = null;

	/**
	 * BuddyPress db version
	 */
	public static $bp_db_version_required = 10000;

	/**
	 * Initialize the plugin
	 */
	private function __construct() {
		$this->setup_globals();
		$this->setup_hooks();
	}

	/**
	 * Return an instance of this class.
	 */
	public static function start() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Sets some globals for the plugin
	 */
	private function setup_globals() {
		/** Plugin globals ********************************************/
		$this->version       = '1.0.0-alpha';
		$this->domain        = 'bp-template-checker';
		$this->name          = 'BP Template Checker';
		$this->file          = __FILE__;
		$this->basename      = plugin_basename( $this->file );
		$this->plugin_dir    = plugin_dir_path( $this->file );
		$this->plugin_url    = plugin_dir_url( $this->file );
		$this->lang_dir      = trailingslashit( $this->plugin_dir . 'languages' );
		$this->templates_dir = $this->plugin_dir . 'bp-templates';
		$this->plugin_js     = trailingslashit( $this->plugin_url . 'js' );
		$this->plugin_css    = trailingslashit( $this->plugin_url . 'css' );

		/** Plugin config **********************************/
		$this->config = $this->network_check();
	}

	/**
	 * Checks BuddyPress version
	 */
	public function version_check() {
		// taking no risk
		if ( ! function_exists( 'bp_get_db_version' ) ) {
			return false;
		}

		return self::$bp_db_version_required === bp_get_db_version();
	}

	/**
	 * Checks if current blog is the one where BuddyPress is activated
	 */
	public function root_blog_check() {
		if ( ! function_exists( 'bp_get_root_blog_id' ) ) {
			return false;
		}

		if ( get_current_blog_id() != bp_get_root_blog_id() ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if current blog is the one where BuddyPress is activated
	 */
	public function network_check() {
		/*
		 * network_active : this plugin is activated on the network
		 * network_status : BuddyPress & this plugin share the same network status
		 */
		$config = array( 'network_active' => false, 'network_status' => true );
		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		// No Network plugins
		if ( empty( $network_plugins ) ) {
			return $config;
		}

		$check = array( buddypress()->basename, $this->basename );
		$network_active = array_diff( $check, array_keys( $network_plugins ) );

		if ( count( $network_active ) == 1 )
			$config['network_status'] = false;

		$config['network_active'] = isset( $network_plugins[ $this->basename ] );

		return $config;
	}

	/**
	 * Set hooks
	 */
	private function setup_hooks() {
		// This plugin && BuddyPress share the same config & BuddyPress version is ok
		if ( $this->version_check() && $this->root_blog_check() && $this->config['network_status'] ) {
			// Page
			add_action( bp_core_admin_hook(), array( $this, 'admin_menu' )       );

			add_action( 'admin_head',         array( $this, 'admin_head' ),  999 );

			// Admin Tab
			add_action( 'bp_admin_tabs',      array( $this, 'admin_tab'  ), 1000 );

		} else {
			add_action( $this->config['network_active'] ? 'network_admin_notices' : 'admin_notices', array( $this, 'admin_warning' ) );
		}

		// loads the languages..
		add_action( 'bp_init', array( $this, 'load_textdomain' ), 5 );

	}

	/**
	 * Set the plugin's page
	 */
	public function admin_menu() {
		$this->page  = bp_core_do_network_admin()  ? 'settings.php' : 'options-general.php';

		$hook = add_submenu_page(
			$this->page,
			__( 'Templates', 'bp-template-checker' ),
			__( 'Templates', 'bp-template-checker' ),
			'manage_options',
			'bp-template-checker',
			array( $this, 'admin_display' )
		);

		add_action( "load-$hook",       array( $this, 'admin_load'       ) );
		add_action( "admin_head-$hook", array( $this, 'modify_highlight' ) );
	}

	/**
	 * Hide submenu
	 */
	public function admin_head() {
		remove_submenu_page( $this->page, 'bp-template-checker' );
	}

	/**
	 * Not needed...
	 */
	public function admin_load() {}

	/**
	 * Modify highlighted menu
	 */
	public function modify_highlight() {
		global $plugin_page, $submenu_file;

		// This tweaks the Settings subnav menu to show only one BuddyPress menu item
		if ( $plugin_page == 'bp-template-checker') {
			$submenu_file = 'bp-components';
		}
	}

	/**
	 * Add Admin tab
	 */
	public function admin_tab() {
		$class = false;

		if ( strpos( get_current_screen()->id, 'bp-template-checker' ) !== false ) {
			$class = "nav-tab-active";
		}
		?>
		<a href="<?php echo esc_url( bp_get_admin_url( add_query_arg( array( 'page' => 'bp-template-checker' ), 'admin.php' ) ) );?>" class="nav-tab <?php echo $class;?>" style="margin-left:-6px"><?php esc_html_e( 'Templates', 'bp-template-checker' );?></a>
		<?php
	}

	/**
	 * Display Admin
	 */
	public function admin_display() {
		?>
		<div class="wrap">
			<h2 class="nav-tab-wrapper"><?php bp_core_admin_tabs( __( 'Templates', 'bp-template-checker' ) ); ?></h2>

			<h3><?php esc_html_e( 'Are your custom BuddyPress templates up to date ??', 'bp-template-checker' ); ?></h3>

			<p class="description" style="margin-bottom:25px">
				<?php esc_html_e( 'Since BuddyPress 2.4.0, we are adding a private action to the top of each template.' ) ;?>
				<?php esc_html_e( 'Each time we edit a template, we also edit this action name, so that if it does not match the action of your custom template you are aware of potential troubles.' ) ;?>
				<?php esc_html_e( 'If some troubles are found, make sure to update the template and add the new action name at the top of it.' ) ;?>
			</p>

			<?php
			$changelog = json_decode( file_get_contents( $this->templates_dir . '/changelog.json' ) );

			// Remove legacy from stack
			bp_deregister_template_stack( 'bp_get_theme_compat_dir',  14 );

			$this->uptodate_templates = array();
			$overrides                = array();
			$changes                  = array();

			foreach ( $changelog->templates as $edited ) {
				foreach ( bp_get_template_stack() as $stack ) {
					$this->current_template = trailingslashit( $stack ) . $edited->template;

					if ( file_exists( $this->current_template ) ) {
						$overrides[ $edited->template ] = $this->current_template;
						$changes[ $edited->template ]   = $edited->changes;

						add_action( '_bp_template_' . $edited->hook, array( $this, 'check_template' ) );

						ob_start();
						require( $this->current_template );
						ob_end_clean();
					}
				}
			}

			if ( empty( $overrides ) ) {
				?>
				<div id="message" class="updated">
					<p><?php esc_html_e( 'Your are not overriding any BuddyPress templates, so all are up to date!', 'bp-template-checker' ); ?></p>
				</div>
				<?php
			} else {
				$diff = array_diff( $overrides, $this->uptodate_templates );

				if ( empty( $diff ) ) {
					?>
					<div id="message" class="updated">
						<p><?php esc_html_e( 'All your templates are up to date!', 'bp-template-checker' ); ?></p>
					</div>
					<?php
				} else {
					?>
					<div id="message" class="error">
						<p><?php printf( esc_html__( '%d template(s) outdated, please upgrade the following template(s) !', 'bp-template-checker' ), count( $diff ) );?></p>
					</div>
					<ol>
						<?php foreach ( array_keys( $diff ) as $ot ) :
							echo '<li><strong>' . trim( str_replace( get_theme_root(), '', $overrides[ $ot ] ), '/' ) . '</strong><ul><li>' . join( '</li><li>', $changes[ $ot ] ) . '</li></ul></li>';
						endforeach ;?>
					</ol>
					<?php
				}
			}
			?>
		</div>
		<?php
	}

	public function check_template() {
		$this->uptodate_templates[] = $this->current_template;
	}

	/**
	 * Display a message to admin in case config is not as expected
	 */
	public function admin_warning() {
		$warnings = array();

		if( ! $this->version_check() ) {
			$warnings[] = sprintf( __( '%s requires at least version %s of BuddyPress.', 'bp-template-checker' ), $this->name, '2.4.0-alpha' );
		}

		if ( ! bp_core_do_network_admin() && ! $this->root_blog_check() ) {
			$warnings[] = sprintf( __( '%s requires to be activated on the blog where BuddyPress is activated.', 'bp-template-checker' ), $this->name );
		}

		if ( bp_core_do_network_admin() && ! is_plugin_active_for_network( $this->basename ) ) {
			$warnings[] = sprintf( __( '%s and BuddyPress need to share the same network configuration.', 'bp-template-checker' ), $this->name );
		}

		if ( ! empty( $warnings ) ) :
		?>
		<div id="message" class="error">
			<?php foreach ( $warnings as $warning ) : ?>
				<p><?php echo esc_html( $warning ) ; ?>
			<?php endforeach ; ?>
		</div>
		<?php
		endif;
	}

	/**
	 * Loads the translation files
	 */
	public function load_textdomain() {
		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/bp-template-checker/' . $mofile;

		// Look in global /wp-content/languages/bp-template-checker folder
		load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/bp-template-checker/languages/ folder
		load_textdomain( $this->domain, $mofile_local );
	}

}

// Let's start !
function bp_template_checker() {
	return BP_Template_Checker::start();
}
add_action( 'bp_include', 'bp_template_checker', 9 );

endif;
