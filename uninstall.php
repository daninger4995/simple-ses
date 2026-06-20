<?php
/**
 * Uninstall handler for Daninger's SMTP for Amazon SES.
 *
 * Removes the plugin's only stored option. Runs when the user deletes the
 * plugin from the WordPress admin.
 *
 * @package Daninger_SMTP_for_Amazon_SES
 */

// Exit if accessed directly or not during an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'daninger_ses_settings' );
delete_transient( 'daninger_ses_notice' );

// Multisite: clean up on every site.
if ( is_multisite() ) {
	$daninger_ses_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $daninger_ses_site_ids as $daninger_ses_site_id ) {
		switch_to_blog( $daninger_ses_site_id );
		delete_option( 'daninger_ses_settings' );
		delete_transient( 'daninger_ses_notice' );
		restore_current_blog();
	}
}
