<?php
/**
 * Module Name: JSON API
 * Module Description: Allow applications to securely access your content through the cloud.
 * Sort Order: 19
 * First Introduced: 1.9
 * Requires Connection: Yes
 * Auto Activate: Public
 * Module Tags: Writing, Developers
 */

add_action( 'jetpack_activate_module_json-api',   array( Jetpack::init(), 'toggle_module_on_wpcom' ) );
add_action( 'jetpack_deactivate_module_json-api', array( Jetpack::init(), 'toggle_module_on_wpcom' ) );

add_action( 'jetpack_modules_loaded', 'jetpack_json_api_load_module' );
add_action( 'jetpack_notices_update_settings_json-api', 'jetpack_json_api_setting_updated_notice' );
add_action( 'wp_loaded', 'jetpack_json_api_get_site_updates' );

$theme_slug = get_option( 'stylesheet' );

Jetpack_Sync::sync_options( __FILE__,
	'stylesheet',
	"theme_mods_{$theme_slug}",
	'jetpack_json_api_full_management',
	'jetpack_sync_non_public_post_stati',
	'jetpack_wp_updates'
);

if ( Jetpack_Options::get_option( 'sync_non_public_post_stati' ) ) {
	$sync_options = array(
		'post_types' => get_post_types( array( 'public' => true ) ),
		'post_stati' => get_post_stati(),
	);
	Jetpack_Sync::sync_posts( __FILE__, $sync_options );
}

/**
 * On each page load, we'll check to see if new updates for the site are available,
 * and save the updates data to a jetpack option. We skip this check on subsites in
 * a multi-site network.
 */
function jetpack_json_api_get_site_updates() {

	// if we are not on the main site, we don't need to calculate updates
	// we can save a blank array
	if ( ! is_main_site() ) {
		Jetpack_Options::update_option( 'wp_updates', array() );
		return;
	}

	$update_data = wp_get_update_data();
	if ( isset( $update_data['counts'] ) ) {
		$updates = $update_data['counts'];
	}

	$updates['wp_version'] = isset( $wp_version ) ? $wp_version : null;

	if ( ! empty( $updates['wordpress'] ) ) {
		$cur = get_preferred_from_update_core();
		if ( isset( $cur->response ) && $cur->response === 'upgrade' ) {
			$updates['wp_update_version'] = $cur->current;
		}
	}

	$updates['is_vcs'] = (bool) jetpack_json_api_is_vcs();
	Jetpack_Options::update_option( 'wp_updates', $updates );
}

/**
 * Finds out if a site is using a version control system.
 * We'll store that information as a transient with a 24 expiration.
 * We only need to check once per day.
 *
 * @return string ( '1' | '0' )
 */
function jetpack_json_api_is_vcs() {
	$is_vcs = get_transient( 'jetpack_is_vcs' );

	if ( false === $is_vcs ) {
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$context = 'WP_PLUGINS_DIR';
		$updater = new WP_Automatic_Updater();
		$is_vcs  = strval( $updater->is_vcs_checkout( $context ) );
		// we should always store a string value of this
		if ( empty( $is_vcs ) ) {
			$is_vcs = '0';
		}
		set_transient( 'jetpack_is_vcs', $is_vcs, DAY_IN_SECONDS );
	}

	return $is_vcs;
}

function jetpack_json_api_load_module() {
	Jetpack::enable_module_configurable( __FILE__ );
	Jetpack::module_configuration_load( __FILE__, 'jetpack_json_api_configuration_load' );
	Jetpack::module_configuration_screen( __FILE__, 'jetpack_json_api_configuration_screen' );
}

function jetpack_json_api_configuration_load() {
	if ( isset( $_POST['action'] ) && $_POST['action'] == 'save_options' && wp_verify_nonce( $_POST['_wpnonce'], 'json-api' ) ) {
		Jetpack_Options::update_option( 'json_api_full_management', isset( $_POST['json_api_full_management'] ) );
		Jetpack::state( 'message', 'module_configured' );
		wp_safe_redirect( Jetpack::module_configuration_url( 'json-api' ) );
		exit;
	}
}

function jetpack_json_api_configuration_screen() {
	?>
	<div class="narrow">
		<form method="post">
			<input type='hidden' name='action' value='save_options' />
			<?php wp_nonce_field( 'json-api' ); ?>
			<table id="menu" class="form-table">
				<tr valign="top"><th scope="row"><label for="json_api_full_management"><?php _e( 'Allow management' , 'jetpack' ); ?></label></th>
					<td><label><input type='checkbox'<?php checked( Jetpack_Options::get_option( 'json_api_full_management' ) ); ?> name='json_api_full_management' id='json_api_full_management' /> <?php printf( __( 'Allow remote management of themes, plugins, and WordPress via the JSON API. (<a href="%s" title="Learn more about JSON API">More info</a>).', 'jetpack') , '//jetpack.me/support/json-api'  ); ?></label></td></tr>

			</table>
			<p class="submit"><input type='submit' class='button-primary' value='<?php echo esc_attr( __( 'Save configuration', 'jetpack' ) ); ?>' /></p>
		</form>
	</div>
<?php
}
/**
 * Additional notice when saving the JSON API
 * @return
 */
function jetpack_json_api_setting_updated_notice() {

	if ( Jetpack_Options::get_option( 'json_api_full_management' ) ) {
		echo '<h4>' . sprintf( __( 'You are all set! Your site can now be managed from <a href="%s" target="_blank">WordPress.com/Plugins</a>.', 'jetpack' ), 'https://wordpress.com/plugins' ) . '</h4>';
	} else {
		echo '<h4>' . __( '<strong>Centralized Site Management</strong> is now disabled.', 'jetpack' ) . '</h4>';
	}
}