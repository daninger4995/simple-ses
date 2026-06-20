<?php
/**
 * Plugin Name: Daninger's SMTP for Amazon SES
 * Version: 1.1.0
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Description: A lightweight plugin that sends all WordPress email through Amazon SES using plain SMTP. No API keys, no other providers, no upsells.
 * Author: daninger4995
 * Author URI: https://github.com/daninger4995
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: daningers-smtp-for-amazon-ses
 *
 * @package Daninger_SMTP_for_Amazon_SES
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'DANINGER_SES_VERSION', '1.1.0' );
define( 'DANINGER_SES_FILE', __FILE__ );
define( 'DANINGER_SES_OPTION', 'daninger_ses_settings' );

/**
 * Main plugin class. Kept intentionally small and self-contained.
 *
 * @since 1.0.0
 */
final class Daninger_SES_Mailer {

	/**
	 * Singleton instance.
	 *
	 * @var Daninger_SES_Mailer|null
	 */
	private static $instance = null;

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Hook suffix of the settings page, used to scope asset loading.
	 *
	 * @var string
	 */
	private $settings_hook = '';

	/**
	 * Amazon SES SMTP endpoints, keyed by AWS region.
	 *
	 * Used to build the host (email-smtp.<region>.amazonaws.com) and to power
	 * the region helper dropdown on the settings screen.
	 *
	 * @return array<string, string>
	 */
	public static function get_regions() {

		return array(
			'us-east-1'      => 'US East (N. Virginia)',
			'us-east-2'      => 'US East (Ohio)',
			'us-west-1'      => 'US West (N. California)',
			'us-west-2'      => 'US West (Oregon)',
			'af-south-1'     => 'Africa (Cape Town)',
			'ap-south-1'     => 'Asia Pacific (Mumbai)',
			'ap-northeast-1' => 'Asia Pacific (Tokyo)',
			'ap-northeast-2' => 'Asia Pacific (Seoul)',
			'ap-northeast-3' => 'Asia Pacific (Osaka)',
			'ap-southeast-1' => 'Asia Pacific (Singapore)',
			'ap-southeast-2' => 'Asia Pacific (Sydney)',
			'ca-central-1'   => 'Canada (Central)',
			'eu-central-1'   => 'Europe (Frankfurt)',
			'eu-west-1'      => 'Europe (Ireland)',
			'eu-west-2'      => 'Europe (London)',
			'eu-west-3'      => 'Europe (Paris)',
			'eu-north-1'     => 'Europe (Stockholm)',
			'eu-south-1'     => 'Europe (Milan)',
			'me-south-1'     => 'Middle East (Bahrain)',
			'sa-east-1'      => 'South America (São Paulo)',
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {

		return array(
			'enabled'    => false,
			'region'     => 'us-east-1',
			'host'       => 'email-smtp.us-east-1.amazonaws.com',
			'port'       => 587,
			'encryption' => 'tls',
			'username'   => '',
			'password'   => '',
			'from_email' => get_option( 'admin_email' ),
			'from_name'  => get_bloginfo( 'name' ),
			'force_from' => true,
		);
	}

	/**
	 * Get the singleton instance and register hooks.
	 *
	 * @return Daninger_SES_Mailer
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}

		return self::$instance;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function hooks() {

		// Core mail integration.
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );

		// Force the From name/email early so other plugins see the correct values.
		if ( $this->is_enabled() ) {
			$settings = $this->get_settings();

			if ( ! empty( $settings['from_email'] ) && ! empty( $settings['force_from'] ) ) {
				add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ), 1000 );
			}
			if ( ! empty( $settings['from_name'] ) && ! empty( $settings['force_from'] ) ) {
				add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ), 1000 );
			}
		}

		// Admin.
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_daninger_ses_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_daninger_ses_test', array( $this, 'handle_test_email' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( DANINGER_SES_FILE ),
			array( $this, 'plugin_action_links' )
		);
	}

	/* --------------------------------------------------------------------- *
	 * Settings storage
	 * --------------------------------------------------------------------- */

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {

		if ( null === $this->settings ) {
			$stored         = get_option( DANINGER_SES_OPTION, array() );
			$this->settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::get_defaults() );
		}

		return $this->settings;
	}

	/**
	 * Whether the plugin is enabled and minimally configured.
	 *
	 * @return bool
	 */
	public function is_enabled() {

		$settings = $this->get_settings();

		return ! empty( $settings['enabled'] ) && ! empty( $settings['host'] );
	}

	/* --------------------------------------------------------------------- *
	 * Mail integration
	 * --------------------------------------------------------------------- */

	/**
	 * Configure PHPMailer to send through Amazon SES SMTP.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer|PHPMailer $phpmailer The PHPMailer instance (by reference).
	 *
	 * @return void
	 */
	public function configure_phpmailer( $phpmailer ) {

		if ( ! $this->is_enabled() ) {
			return;
		}

		$settings = $this->get_settings();

		$phpmailer->isSMTP();
		$phpmailer->Host     = $settings['host'];
		$phpmailer->Port     = (int) $settings['port'];
		$phpmailer->SMTPAuth = true;
		$phpmailer->Username = $settings['username'];
		$phpmailer->Password = $settings['password'];

		// Encryption. Amazon SES supports STARTTLS (tls) and implicit TLS (ssl).
		if ( 'none' === $settings['encryption'] ) {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = false;
		} else {
			$phpmailer->SMTPSecure  = $settings['encryption']; // 'tls' or 'ssl'.
			$phpmailer->SMTPAutoTLS = true;
		}

		// From address handling.
		if ( ! empty( $settings['from_email'] ) && ( ! empty( $settings['force_from'] ) || empty( $phpmailer->From ) ) ) {
			$phpmailer->From = $settings['from_email'];
		}
		if ( ! empty( $settings['from_name'] ) && ( ! empty( $settings['force_from'] ) || empty( $phpmailer->FromName ) ) ) {
			$phpmailer->FromName = $settings['from_name'];
		}
	}

	/**
	 * Filter callback for wp_mail_from.
	 *
	 * @param string $email Original from email.
	 *
	 * @return string
	 */
	public function filter_from_email( $email ) {

		$settings = $this->get_settings();

		return ! empty( $settings['from_email'] ) ? $settings['from_email'] : $email;
	}

	/**
	 * Filter callback for wp_mail_from_name.
	 *
	 * @param string $name Original from name.
	 *
	 * @return string
	 */
	public function filter_from_name( $name ) {

		$settings = $this->get_settings();

		return ! empty( $settings['from_name'] ) ? $settings['from_name'] : $name;
	}

	/* --------------------------------------------------------------------- *
	 * Admin: settings page
	 * --------------------------------------------------------------------- */

	/**
	 * Register the settings page under Settings.
	 *
	 * @return void
	 */
	public function register_settings_page() {

		$this->settings_hook = add_options_page(
			esc_html__( 'Daninger\'s SMTP for Amazon SES', 'daningers-smtp-for-amazon-ses' ),
			esc_html__( 'Daninger\'s SMTP', 'daningers-smtp-for-amazon-ses' ),
			'manage_options',
			'daningers-smtp-for-amazon-ses',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue the region-helper script, only on this plugin's settings page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {

		if ( empty( $this->settings_hook ) || $hook_suffix !== $this->settings_hook ) {
			return;
		}

		// Registered with no source so we can attach an inline script to it.
		wp_register_script( 'daninger-ses-admin', false, array(), DANINGER_SES_VERSION, true );
		wp_enqueue_script( 'daninger-ses-admin' );

		$script = 'document.addEventListener( "DOMContentLoaded", function () {'
			. ' var hosts = ' . wp_json_encode( $this->host_map() ) . ';'
			. ' var region = document.getElementById( "daninger-ses-region" );'
			. ' var host = document.getElementById( "daninger-ses-host" );'
			. ' if ( region && host ) {'
			. ' region.addEventListener( "change", function () {'
			. ' if ( hosts[ this.value ] ) { host.value = hosts[ this.value ]; }'
			. ' } ); } } );';

		wp_add_inline_script( 'daninger-ses-admin', $script );
	}

	/**
	 * Add a Settings link on the Plugins list row.
	 *
	 * @param array $links Existing action links.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {

		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=daningers-smtp-for-amazon-ses' ) ),
			esc_html__( 'Settings', 'daningers-smtp-for-amazon-ses' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'daningers-smtp-for-amazon-ses' ) );
		}

		$settings     = $this->get_settings();
		$regions      = self::get_regions();
		$has_password = ! empty( $settings['password'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Daninger\'s SMTP for Amazon SES', 'daningers-smtp-for-amazon-ses' ); ?></h1>

			<?php $this->render_admin_notices(); ?>

			<p class="description">
				<?php esc_html_e( 'Send all WordPress email through Amazon SES using your SES SMTP credentials. Generate SMTP credentials in the Amazon SES console under "SMTP settings".', 'daningers-smtp-for-amazon-ses' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="daninger_ses_save" />
				<?php wp_nonce_field( 'daninger_ses_save' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable plugin', 'daningers-smtp-for-amazon-ses' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
								<?php esc_html_e( 'Route WordPress email through Amazon SES SMTP', 'daningers-smtp-for-amazon-ses' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="daninger-ses-region"><?php esc_html_e( 'SES Region', 'daningers-smtp-for-amazon-ses' ); ?></label>
						</th>
						<td>
							<select name="region" id="daninger-ses-region">
								<?php foreach ( $regions as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $settings['region'], $code ); ?>>
										<?php echo esc_html( $label . ' — ' . $code ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Choosing a region fills in the SMTP host below. Must match the region where your SES account is verified.', 'daningers-smtp-for-amazon-ses' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="daninger-ses-host"><?php esc_html_e( 'SMTP Host', 'daningers-smtp-for-amazon-ses' ); ?></label>
						</th>
						<td>
							<input type="text" name="host" id="daninger-ses-host" class="regular-text"
								value="<?php echo esc_attr( $settings['host'] ); ?>"
								placeholder="email-smtp.us-east-1.amazonaws.com" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="daninger-ses-port"><?php esc_html_e( 'SMTP Port', 'daningers-smtp-for-amazon-ses' ); ?></label>
						</th>
						<td>
							<input type="number" name="port" id="daninger-ses-port" class="small-text" min="1" max="65535"
								value="<?php echo esc_attr( $settings['port'] ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Amazon SES: 587 or 25 (STARTTLS/TLS), 465 or 2465 (SSL).', 'daningers-smtp-for-amazon-ses' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Encryption', 'daningers-smtp-for-amazon-ses' ); ?></th>
						<td>
							<fieldset>
								<?php
								$encryptions = array(
									'tls'  => __( 'TLS (recommended)', 'daningers-smtp-for-amazon-ses' ),
									'ssl'  => __( 'SSL', 'daningers-smtp-for-amazon-ses' ),
									'none' => __( 'None', 'daningers-smtp-for-amazon-ses' ),
								);
								foreach ( $encryptions as $value => $label ) :
									?>
									<label style="margin-right:16px;">
										<input type="radio" name="encryption" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['encryption'], $value ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="daninger-ses-username"><?php esc_html_e( 'SMTP Username', 'daningers-smtp-for-amazon-ses' ); ?></label>
						</th>
						<td>
							<input type="text" name="username" id="daninger-ses-username" class="regular-text" autocomplete="off"
								value="<?php echo esc_attr( $settings['username'] ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Your Amazon SES SMTP username (not your AWS access key ID).', 'daningers-smtp-for-amazon-ses' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="daninger-ses-password"><?php esc_html_e( 'SMTP Password', 'daningers-smtp-for-amazon-ses' ); ?></label>
						</th>
						<td>
							<input type="password" name="password" id="daninger-ses-password" class="regular-text" autocomplete="new-password"
								value="" placeholder="<?php echo $has_password ? esc_attr__( '••••••••  (leave blank to keep current)', 'daningers-smtp-for-amazon-ses' ) : ''; ?>" />
							<p class="description">
								<?php esc_html_e( 'Your Amazon SES SMTP password. Stored in the database but never displayed again. Leave blank to keep the existing password.', 'daningers-smtp-for-amazon-ses' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="daninger-ses-from-email"><?php esc_html_e( 'From Email', 'daningers-smtp-for-amazon-ses' ); ?></label>
						</th>
						<td>
							<input type="email" name="from_email" id="daninger-ses-from-email" class="regular-text"
								value="<?php echo esc_attr( $settings['from_email'] ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Must be a verified SES identity (email address or domain).', 'daningers-smtp-for-amazon-ses' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="daninger-ses-from-name"><?php esc_html_e( 'From Name', 'daningers-smtp-for-amazon-ses' ); ?></label>
						</th>
						<td>
							<input type="text" name="from_name" id="daninger-ses-from-name" class="regular-text"
								value="<?php echo esc_attr( $settings['from_name'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Force From', 'daningers-smtp-for-amazon-ses' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="force_from" value="1" <?php checked( ! empty( $settings['force_from'] ) ); ?> />
								<?php esc_html_e( 'Always use the From Email and From Name above, overriding values set by other plugins.', 'daningers-smtp-for-amazon-ses' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'daningers-smtp-for-amazon-ses' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Send a Test Email', 'daningers-smtp-for-amazon-ses' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="daninger_ses_test" />
				<?php wp_nonce_field( 'daninger_ses_test' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="daninger-ses-test-to"><?php esc_html_e( 'Send To', 'daningers-smtp-for-amazon-ses' ); ?></label>
						</th>
						<td>
							<input type="email" name="test_to" id="daninger-ses-test-to" class="regular-text" required
								value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" />
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Send Test Email', 'daningers-smtp-for-amazon-ses' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Map of region code => SES SMTP host, for the JS region helper.
	 *
	 * @return array<string, string>
	 */
	private function host_map() {

		$map = array();
		foreach ( array_keys( self::get_regions() ) as $code ) {
			$map[ $code ] = 'email-smtp.' . $code . '.amazonaws.com';
		}

		return $map;
	}

	/**
	 * Render transient-based admin notices after a redirect.
	 *
	 * @return void
	 */
	private function render_admin_notices() {

		$notice = get_transient( 'daninger_ses_notice' );

		if ( empty( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( 'daninger_ses_notice' );

		$class = ( 'error' === $notice['type'] ) ? 'notice-error' : 'notice-success';

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Store a one-time admin notice and redirect back to the settings page.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Notice text.
	 *
	 * @return void
	 */
	private function redirect_with_notice( $type, $message ) {

		set_transient(
			'daninger_ses_notice',
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);

		wp_safe_redirect( admin_url( 'options-general.php?page=daningers-smtp-for-amazon-ses' ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Admin: form handlers
	 * --------------------------------------------------------------------- */

	/**
	 * Handle saving the settings form.
	 *
	 * @return void
	 */
	public function handle_save() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'daningers-smtp-for-amazon-ses' ) );
		}

		check_admin_referer( 'daninger_ses_save' );

		$existing = $this->get_settings();
		$regions  = self::get_regions();

		// Region (validated against the known list).
		$region = isset( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : $existing['region'];
		if ( ! array_key_exists( $region, $regions ) ) {
			$region = $existing['region'];
		}

		// Host (fall back to derived host from region if left empty).
		$host = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';
		if ( '' === $host ) {
			$host = 'email-smtp.' . $region . '.amazonaws.com';
		}

		// Port.
		$port = isset( $_POST['port'] ) ? absint( $_POST['port'] ) : 587;
		if ( $port < 1 || $port > 65535 ) {
			$port = 587;
		}

		// Encryption (whitelisted).
		$encryption = isset( $_POST['encryption'] ) ? sanitize_text_field( wp_unslash( $_POST['encryption'] ) ) : 'tls';
		if ( ! in_array( $encryption, array( 'tls', 'ssl', 'none' ), true ) ) {
			$encryption = 'tls';
		}

		// Password: only overwrite when a new value is submitted.
		$password = $existing['password'];
		if ( isset( $_POST['password'] ) && '' !== $_POST['password'] ) {
			// Amazon SES SMTP passwords are base64 characters, so sanitize_text_field is safe here.
			$password = sanitize_text_field( wp_unslash( $_POST['password'] ) );
		}

		$settings = array(
			'enabled'    => ! empty( $_POST['enabled'] ),
			'region'     => $region,
			'host'       => $host,
			'port'       => $port,
			'encryption' => $encryption,
			'username'   => isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '',
			'password'   => $password,
			'from_email' => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '',
			'from_name'  => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '',
			'force_from' => ! empty( $_POST['force_from'] ),
		);

		update_option( DANINGER_SES_OPTION, $settings );

		$this->redirect_with_notice( 'success', __( 'Settings saved.', 'daningers-smtp-for-amazon-ses' ) );
	}

	/**
	 * Handle the test email form.
	 *
	 * @return void
	 */
	public function handle_test_email() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'daningers-smtp-for-amazon-ses' ) );
		}

		check_admin_referer( 'daninger_ses_test' );

		$to = isset( $_POST['test_to'] ) ? sanitize_email( wp_unslash( $_POST['test_to'] ) ) : '';

		if ( empty( $to ) || ! is_email( $to ) ) {
			$this->redirect_with_notice( 'error', __( 'Please enter a valid recipient email address.', 'daningers-smtp-for-amazon-ses' ) );
		}

		$subject = __( 'Daninger\'s SMTP for Amazon SES test email', 'daningers-smtp-for-amazon-ses' );
		$body    = sprintf(
			/* translators: %s - site URL. */
			__( 'Congratulations! This is a test email sent through Amazon SES SMTP from %s.', 'daningers-smtp-for-amazon-ses' ),
			home_url()
		);

		// Capture PHPMailer errors so we can report them (without exposing the password).
		$error = '';
		$catch = function ( $wp_error ) use ( &$error ) {
			$error = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $catch );

		$sent = wp_mail( $to, $subject, $body );

		remove_action( 'wp_mail_failed', $catch );

		if ( $sent ) {
			$this->redirect_with_notice(
				'success',
				sprintf(
					/* translators: %s - recipient email address. */
					__( 'Test email sent to %s. Check the inbox (and spam folder).', 'daningers-smtp-for-amazon-ses' ),
					$to
				)
			);
		}

		$this->redirect_with_notice(
			'error',
			sprintf(
				/* translators: %s - error detail. */
				__( 'Test email failed to send. %s', 'daningers-smtp-for-amazon-ses' ),
				$error
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Activation
	 * --------------------------------------------------------------------- */

	/**
	 * On activation, seed the default settings if they don't already exist.
	 *
	 * @return void
	 */
	public static function activate() {

		if ( false === get_option( DANINGER_SES_OPTION, false ) ) {
			add_option( DANINGER_SES_OPTION, self::get_defaults() );
		}
	}
}

register_activation_hook( __FILE__, array( 'Daninger_SES_Mailer', 'activate' ) );

/**
 * Boot the plugin.
 *
 * @return Daninger_SES_Mailer
 */
function daninger_ses() {

	return Daninger_SES_Mailer::instance();
}

daninger_ses();
