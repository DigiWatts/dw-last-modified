<?php

/**
 * DW Last Modified replaces Last Modified Timestamp and Last Modified By, so
 * activating it switches those two off.
 */
class ReplacesOldPluginsTest extends WP_UnitTestCase
{
	const TIMESTAMP = 'last-modified-timestamp/last-modified-timestamp.php';
	const BY        = 'last-modified-by/last-modified-by.php';

	function set_up()
	{
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	function activate_dw_last_modified()
	{
		$file = dirname( dirname( __DIR__ ) ) . '/dw-last-modified.php';

		do_action( 'activate_' . plugin_basename( $file ), false );
	}

	/** @test */
	function deactivates_the_replaced_plugins_on_activation()
	{
		update_option( 'active_plugins', array( self::TIMESTAMP, 'akismet/akismet.php', self::BY ) );

		$this->activate_dw_last_modified();

		$this->assertSame( array( 'akismet/akismet.php' ), array_values( get_option( 'active_plugins' ) ) );
	}

	/** @test */
	function activates_cleanly_when_the_replaced_plugins_are_not_active()
	{
		update_option( 'active_plugins', array( 'akismet/akismet.php' ) );

		$this->activate_dw_last_modified();

		$this->assertSame( array( 'akismet/akismet.php' ), array_values( get_option( 'active_plugins' ) ) );
	}
}
