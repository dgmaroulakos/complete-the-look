<?php
/**
 * GitHub release updates for public repositories.
 *
 * @package WooCompleteTheLook
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers GitHub-based plugin update checks (mirrors Jofme theme updater).
 */
class CTM_Updates {

	/**
	 * Default public GitHub repository (owner/name).
	 *
	 * @var string
	 */
	const DEFAULT_REPO = 'dgmaroulakos/complete-the-look';

	/**
	 * Option key for the configured repository.
	 *
	 * @var string
	 */
	const REPO_OPTION = 'ctm_update_repo';

	/**
	 * Site transient that caches the latest GitHub release payload.
	 *
	 * @var string
	 */
	const RELEASE_TRANSIENT = 'ctm_github_release';

	/**
	 * Installable ZIP asset name attached to each GitHub release.
	 *
	 * @var string
	 */
	const ZIP_ASSET = 'woo-complete-the-look.zip';

	/**
	 * Stable plugin directory / slug expected inside the release ZIP.
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'woo-complete-the-look';

	/**
	 * Singleton instance.
	 *
	 * @var CTM_Updates|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return CTM_Updates
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_force_update_check' ), 1 );
		add_action( 'delete_site_transient_update_plugins', array( $this, 'clear_github_release_on_plugin_check' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_plugin_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 20, 3 );
	}

	/**
	 * Plugin basename used as the WordPress updates key.
	 *
	 * @return string
	 */
	public static function plugin_basename() {
		return plugin_basename( CTM_PLUGIN_FILE );
	}

	/**
	 * Clear cached GitHub release + WordPress plugin update payload.
	 *
	 * @return void
	 */
	public static function clear_update_cache() {
		delete_site_transient( self::RELEASE_TRANSIENT );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * When Dashboard → Updates → Check again runs, also refetch GitHub.
	 *
	 * @return void
	 */
	public function clear_github_release_on_plugin_check() {
		delete_site_transient( self::RELEASE_TRANSIENT );
	}

	/**
	 * Sanitize owner/repository values.
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_repo( $value ) {
		$value = trim( (string) $value );
		$value = preg_replace( '~^https://github\.com/~i', '', $value );
		$value = preg_replace( '~\.git$~', '', rtrim( $value, '/' ) );

		if ( '' !== $value && ! preg_match( '~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $value ) ) {
			add_settings_error(
				self::REPO_OPTION,
				'invalid_repo',
				__( 'Enter a public GitHub repository as owner/repository.', 'woo-complete-the-look' )
			);
			return get_option( self::REPO_OPTION, self::DEFAULT_REPO );
		}

		self::clear_update_cache();

		return $value;
	}

	/**
	 * Register the repository option.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'ctm_updates',
			self::REPO_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_repo' ),
				'default'           => self::DEFAULT_REPO,
			)
		);
	}

	/**
	 * Add Updates screen under WooCommerce (or Plugins when WooCommerce is inactive).
	 *
	 * @return void
	 */
	public function register_menu() {
		$title = __( 'Complete the Look Updates', 'woo-complete-the-look' );
		$label = __( 'CTL Updates', 'woo-complete-the-look' );
		$cap   = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'update_plugins';

		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page(
				'woocommerce',
				$title,
				$label,
				$cap,
				'woo-complete-the-look-updates',
				array( $this, 'render_page' )
			);
			return;
		}

		add_plugins_page(
			$title,
			$label,
			$cap,
			'woo-complete-the-look-updates',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle “Check for updates now” from the updates screen.
	 *
	 * @return void
	 */
	public function handle_force_update_check() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( empty( $_GET['ctm_check_updates'] ) || empty( $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ctm_check_updates' ) ) {
			return;
		}

		self::clear_update_cache();
		wp_clean_update_cache();
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'update-core.php?force-check=1' ) );
		exit;
	}

	/**
	 * Render the GitHub updates admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$basename  = self::plugin_basename();
		$installed = defined( 'CTM_VERSION' ) ? CTM_VERSION : '';
		$release   = self::github_release( true );

		echo '<div class="wrap"><h1>' . esc_html__( 'Complete the Look — GitHub updates', 'woo-complete-the-look' ) . '</h1>';
		settings_errors();
		echo '<p>' . esc_html__( 'Connect a public GitHub repository. Stable releases must include a woo-complete-the-look.zip asset. Updates appear under Dashboard → Updates.', 'woo-complete-the-look' ) . '</p>';

		if ( $installed ) {
			echo '<p><strong>' . esc_html__( 'Installed', 'woo-complete-the-look' ) . ':</strong> ' . esc_html( $installed ) . ' <code>' . esc_html( $basename ) . '</code>';

			if ( is_array( $release ) && ! empty( $release['version'] ) ) {
				echo ' &nbsp;|&nbsp; <strong>' . esc_html__( 'Latest GitHub', 'woo-complete-the-look' ) . ':</strong> ' . esc_html( $release['version'] );

				if ( version_compare( $release['version'], $installed, '>' ) ) {
					echo ' — <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">' . esc_html__( 'Update available', 'woo-complete-the-look' ) . '</a>';
				} elseif ( version_compare( $release['version'], $installed, '==' ) ) {
					echo ' — ' . esc_html__( 'You are up to date.', 'woo-complete-the-look' );
				}
			} else {
				echo ' &nbsp;|&nbsp; ' . esc_html__( 'Could not read the latest GitHub release. Check the repository name and that the site can reach api.github.com.', 'woo-complete-the-look' );
			}

			echo '</p>';
		}

		$check_url = add_query_arg(
			'ctm_check_updates',
			'1',
			menu_page_url( 'woo-complete-the-look-updates', false )
		);
		echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( $check_url, 'ctm_check_updates' ) ) . '">' . esc_html__( 'Check for updates now', 'woo-complete-the-look' ) . '</a></p>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'ctm_updates' );
		echo '<p><label for="ctm-update-repo">' . esc_html__( 'GitHub repository (owner/repository)', 'woo-complete-the-look' ) . '</label></p>';
		echo '<input class="regular-text" id="ctm-update-repo" name="' . esc_attr( self::REPO_OPTION ) . '" value="' . esc_attr( get_option( self::REPO_OPTION, self::DEFAULT_REPO ) ) . '">';
		submit_button();
		echo '</form>';
		echo '<p>' . esc_html__( 'Release checks are cached for one hour. Use “Check for updates now”, or save this setting again, to clear the cache. Then open Dashboard → Updates.', 'woo-complete-the-look' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Install from the release ZIP so the plugin folder is named woo-complete-the-look. Do not use the GitHub “Source code” archive.', 'woo-complete-the-look' ) . '</p></div>';
	}

	/**
	 * Fetch the latest public GitHub release with the installable ZIP asset.
	 *
	 * @param bool $fresh Bypass the one-hour cache.
	 * @return array{version:string,package:string,url:string}|false
	 */
	public static function github_release( $fresh = false ) {
		$repo = get_option( self::REPO_OPTION, self::DEFAULT_REPO );

		if ( ! preg_match( '~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repo ) ) {
			return false;
		}

		if ( ! $fresh ) {
			$cached = get_site_transient( self::RELEASE_TRANSIENT );
			if ( is_array( $cached ) && isset( $cached['repo'] ) && $cached['repo'] === $repo ) {
				return $cached['release'];
			}
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . $repo . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Woo-Complete-The-Look-Updater',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return false;
		}

		$version = ltrim( (string) $data['tag_name'], 'v' );

		if ( ! preg_match( '/^\d+\.\d+\.\d+$/D', $version ) ) {
			return false;
		}

		$release = false;

		foreach ( isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : array() as $asset ) {
			if ( ! isset( $asset['name'], $asset['browser_download_url'] ) || self::ZIP_ASSET !== $asset['name'] ) {
				continue;
			}

			$package = $asset['browser_download_url'];

			if ( 0 !== strpos( $package, 'https://github.com/' . $repo . '/releases/download/' ) ) {
				continue;
			}

			$release = array(
				'version' => $version,
				'package' => esc_url_raw( $package ),
				'url'     => 'https://github.com/' . $repo,
			);
			break;
		}

		set_site_transient(
			self::RELEASE_TRANSIENT,
			array(
				'repo'    => $repo,
				'release' => $release,
			),
			HOUR_IN_SECONDS
		);

		return $release;
	}

	/**
	 * Inject this plugin into the WordPress update transient.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function inject_plugin_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$plugin  = self::plugin_basename();
		$release = self::github_release();

		if ( ! $release ) {
			return $transient;
		}

		$installed = isset( $transient->checked[ $plugin ] )
			? $transient->checked[ $plugin ]
			: ( defined( 'CTM_VERSION' ) ? CTM_VERSION : '' );

		if ( '' === $installed ) {
			return $transient;
		}

		$update = (object) array(
			'slug'         => self::PLUGIN_SLUG,
			'plugin'       => $plugin,
			'new_version'  => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'icons'        => array(),
			'banners'      => array(),
			'tested'       => '',
			'requires'     => '5.8',
			'requires_php' => '7.4',
		);

		if ( version_compare( $release['version'], $installed, '>' ) ) {
			$transient->response[ $plugin ] = $update;
			unset( $transient->no_update[ $plugin ] );
		} else {
			$transient->no_update[ $plugin ] = $update;
			unset( $transient->response[ $plugin ] );
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the “View version details” modal.
	 *
	 * @param false|object|array $result Result object or array.
	 * @param string             $action Action name.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::github_release();

		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Woo Complete the Look',
			'slug'          => self::PLUGIN_SLUG,
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/dgmaroulakos">dgmaroulakos</a>',
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'tested'        => '',
			'sections'      => array(
				'description' => __( 'Link complementary products and display a Complete the Look section on WooCommerce single product pages. Updates are delivered from GitHub Releases.', 'woo-complete-the-look' ),
			),
		);
	}
}
