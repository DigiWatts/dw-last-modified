<?php
/*
	Plugin Name: DW Last Modified
	Version: 1.2.0
	Description: Adds the last modified date, time and modifying user to the admin interface, including custom post types. Use the [dw-last-modified] shortcode in your content!
	Text Domain: dw-last-modified
	Author: Erik Mitchell
	Author URI: https://erikmitchell.net
	Plugin URI: https://github.com/DigiWatts/dw-last-modified
	Update URI: https://github.com/DigiWatts/dw-last-modified
	Requires at least: 4.6
	Requires PHP: 7.2
	License: GPL-2.0-or-later
	License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/*
	DW Last Modified began as a fork of Last Modified Timestamp by Evan Mattson
	(https://github.com/aaemnnosttv/last-modified-timestamp) and has been
	maintained independently by DigiWatts since version 1.1.0, which also merged
	in the Last Modified By plugin (https://github.com/erikdmitchell/last-modified-by).

	Copyright 2011-2013 Evan Mattson (email: me at aaemnnost dot tv)
	Copyright 2026 Erik Mitchell / DigiWatts

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DW_LAST_MODIFIED_VERSION', '1.2.0' );

class DWLastModified
{
	private static $instance;

	protected function __construct()
	{
		/**
		 * Init actions
		 */
		add_action( 'init', array( $this, 'admin_actions' ) );

		add_shortcode( 'dw-last-modified', array( $this, 'shortcode_handler' ) );

		add_action( 'plugins_loaded', array( $this, 'register_legacy_api' ), 5 );
	}

	/**
	 * Registers the pre-rename public API - shortcode, class name and template
	 * tags - so code written against Last Modified Timestamp or Last Modified By
	 * keeps working.
	 *
	 * Deferred to plugins_loaded rather than done at file load because the guards
	 * below have to see every other plugin first. Declaring these eagerly would
	 * fatal on "cannot redeclare" if the upstream plugin happened to load after
	 * this one, which it does whenever its directory sorts later.
	 *
	 * @return void
	 */
	function register_legacy_api()
	{
		if ( ! shortcode_exists( 'last-modified' ) )
			add_shortcode( 'last-modified', array( $this, 'shortcode_handler' ) );

		if ( ! class_exists( 'LastModifiedTimestamp' ) )
			class_alias( 'DWLastModified', 'LastModifiedTimestamp' );

		if ( ! function_exists( 'get_the_last_modified_timestamp' ) )
		{
			function get_the_last_modified_timestamp( $context = null, $override = null )
			{
				return get_the_dw_last_modified( $context, $override );
			}
		}

		if ( ! function_exists( 'the_last_modified_timestamp' ) )
		{
			function the_last_modified_timestamp( $context = null, $override = null )
			{
				the_dw_last_modified( $context, $override );
			}
		}
	}

	function admin_actions()
	{
		add_action( 'admin_print_styles-edit.php',			array( $this, 'print_admin_css' ) );
		add_action( 'admin_print_styles-post.php',			array( $this, 'print_admin_css' ) );
		add_action( 'admin_print_styles-post-new.php',		array( $this, 'print_admin_css' ) );
		add_action( 'post_submitbox_misc_actions',			array( $this, 'publish_box' ), 1 );  // NEW PRIORITY

		add_filter( 'post_updated_messages',				array( $this, 'modify_messages' ) );

		foreach ( get_post_types() as $pt )
		{
			add_filter( "manage_{$pt}_posts_columns",			array( $this, 'column_heading' ), 10, 1 );
			add_action( "manage_{$pt}_posts_custom_column",		array( $this, 'column_content' ), 10, 2 );
			add_action( "manage_edit-{$pt}_sortable_columns",	array( $this, 'column_sort'    ), 10, 2 );
		}
	}

	/**
	 * Applies a filter under its current name, then again under the name it had
	 * before the plugin was renamed, so callbacks written against either keep
	 * working. Extra arguments are passed through to both.
	 * @param  string 	$name 			Current filter name.
	 * @param  string 	$legacy_name 	Pre-1.2.0 filter name.
	 * @param  mixed 	$value 			Value being filtered.
	 * @return mixed 					filtered value
	 */
	protected function apply_filters_with_legacy( $name, $legacy_name, $value )
	{
		$args = array_slice( func_get_args(), 2 );

		$value   = call_user_func_array( 'apply_filters', array_merge( array( $name ), $args ) );
		$args[0] = $value;

		return call_user_func_array( 'apply_filters', array_merge( array( $legacy_name ), $args ) );
	}

	function get_defaults( $context = null )
	{
		$defaults = array(
			// base defaults
			'base'     => array(
				'datef'   => _x( 'M j, Y', 'default date format', 'dw-last-modified' ),
				'timef'   => null,
				'sep'     => _x( '@', 'default separator', 'dw-last-modified' ),
				'authorf' => _x( 'by %author%', 'default author format', 'dw-last-modified' ),
				'format'  => _x( '%date% %sep% %time%', 'default format', 'dw-last-modified' ),
			),
			// extended contextual defaults
			// %author% is only included in the wp-admin contexts, so that the shortcode
			// and template tags don't publish editors' names on the front-end.
			'contexts' => array(
				'messages'    => array(
					'datef'  => _x( 'M j, Y', 'messages date format', 'dw-last-modified' ),
					'sep'    => _x( '@', 'messages separator', 'dw-last-modified' ),
					'format' => _x( '%date% %sep% %time% %author%', 'messages format', 'dw-last-modified' ),
				),
				'publish-box' => array(
					'datef'  => _x( 'M j, Y', 'publish-box date format', 'dw-last-modified' ),
					'sep'    => _x( '@', 'publish-box separator', 'dw-last-modified' ),
					'format' => _x( '%date% %sep% %time% %author%', 'publish-box format', 'dw-last-modified' ),
				),
				'shortcode'   => array(
					'datef' => _x( 'M j, Y', 'shortcode date format', 'dw-last-modified' ),
					'sep'   => _x( '@', 'shortcode separator', 'dw-last-modified' ),
				),
				'wp-table'    => array(
					'datef'  => _x( 'Y/m/d', 'wp-table date format', 'dw-last-modified' ),
					'sep'    => _x( '<br />', 'wp-table separator', 'dw-last-modified' ),
					'format' => _x( '%date% %sep% %time% %author%', 'wp-table format', 'dw-last-modified' ),
				),
			),
		);

		/**
		 * filter 'dw_last_modified_defaults'
		 *
		 * Was 'last_modified_timestamp_defaults' before the rename; both are applied.
		 *
		 * @param mixed (null|string) $context  - the context the timestamp will be used in
		 */
		$defaults = $this->apply_filters_with_legacy( 'dw_last_modified_defaults', 'last_modified_timestamp_defaults', $defaults, $context );

		if ( $context && isset( $defaults['contexts'][ $context ] ) )
			return wp_parse_args( $defaults['contexts'][ $context ], $defaults['base'] );
		else
			return $defaults['base'];
	}

	/**
	 * Returns a formatted timestamp as a string
	 * @param  string 	$context 		Defines what defaults are to be used to build the timestamp.
	 * @param  array  	$override 		Used by shortcode to pass per-instance values
	 * @return string 	$timestramp		timestamp html
	 */
	function construct_timestamp( $context = null, $override = null )
	{
		$data = $this->get_defaults( $context );

		if ( $override && is_array( $override ) )
			$data = wp_parse_args( $override, $data );

		extract( $data );

		// Defaults filtered by code written before %author% existed may not define it.
		if ( ! isset( $authorf ) )
			$authorf = '';

		$author = $this->construct_author( $authorf );

		$timestamp = str_replace(
			array( '%date%','%time%','%sep%','%author%' ),											// search
			array( get_the_modified_date( $datef ), get_the_modified_time( $timef ), $sep, $author ),	// replace
			$format 																				// subject
		);

		// An unknown author leaves the space around its placeholder behind.
		$timestamp = trim( preg_replace( '/[ \t]+/', ' ', $timestamp ) );

		// The pre-rename class is kept alongside the current one so existing CSS
		// targeting last-modified-timestamp still applies.
		$timestamp = '<span class="dw-last-modified last-modified-timestamp">' . $timestamp . '</span>';

		/**
		 * filter 'dw_last_modified_output'
		 *
		 * Was 'last_modified_timestamp_output' before the rename; both are applied.
		 *
		 * @param mixed (null|string) $context  - the context the timestamp will be used in
		 */
		return $this->apply_filters_with_legacy( 'dw_last_modified_output', 'last_modified_timestamp_output', $timestamp, $context );
	}

	/**
	 * Returns the formatted author of the last modification as a string,
	 * or an empty string when the modifying user cannot be determined.
	 * @param  string 	$authorf 		Author format. The placeholder is %author%.
	 * @return string 	$author 		author html
	 */
	function construct_author( $authorf )
	{
		$post = get_post();

		if ( ! $authorf || ! $post )
			return '';

		$author = $this->get_the_modified_author( $post->ID );

		if ( ! $author )
			return '';

		$output = '<span class="dw-last-modified-author last-modified-by">' . str_replace( '%author%', esc_html( $author ), $authorf ) . '</span>';

		/**
		 * filter 'dw_last_modified_author_output'
		 *
		 * Was 'last_modified_by_output' before the rename; both are applied.
		 *
		 * @param int 		$post_id 	- the post the author was resolved for
		 * @param string 	$author 	- the display name of the last modifying user
		 */
		return $this->apply_filters_with_legacy( 'dw_last_modified_author_output', 'last_modified_by_output', $output, $post->ID, $author );
	}

	/**
	 * Returns the display name of the user who last modified the post.
	 * Reads the core _edit_last meta, which WordPress only writes when a post is
	 * saved through wp-admin - see the FAQ in readme.txt.
	 * @param  int 		$post_id 		Defaults to the current post.
	 * @return string 					display name, or an empty string if unknown
	 */
	function get_the_modified_author( $post_id = 0 )
	{
		$post = get_post( $post_id );

		if ( ! $post )
			return '';

		$last_id = get_post_meta( $post->ID, '_edit_last', true );

		if ( ! $last_id )
			return '';

		$user = get_userdata( $last_id );

		// The user may have been deleted since the post was modified.
		if ( ! $user )
			return '';

		/**
		 * filter 'dw_last_modified_author'
		 *
		 * The legacy name 'the_modified_author' collides with the WordPress core
		 * filter of the same name, which is why it was renamed. It is still
		 * applied so code written against the Last Modified By plugin works.
		 *
		 * @param int $post_id  - the post the author was resolved for
		 */
		return $this->apply_filters_with_legacy( 'dw_last_modified_author', 'the_modified_author', $user->display_name, $post->ID );
	}

	/**
	 * Shortcode handler for the [dw-last-modified] shortcode
	 * @param  array 	$atts 	Attributes array. possible attributes are 'datef', 'timef', 'sep', 'authorf' and 'format'.
	 *                       	All attributes are optional. Defaults can also be filtered.
	 * @return string 			timestamp html
	 */
	function shortcode_handler( $atts = array() )
	{
		$atts = shortcode_atts( $this->get_defaults('shortcode'), $atts );
		return $this->construct_timestamp('shortcode', $atts);
	}

	/**
	 * Filters the admin messages at the top of the page on post.php for pages & posts to include the last modified timestamp.
	 * @param  array 	$messages
	 * @return array
	 */
	function modify_messages( $messages )
	{
		$timestamp = $this->construct_timestamp('messages');

		foreach ( $messages as $posttype => &$array )
		{
			foreach ( $array as $index => &$msg )
			{
				if ( false !== $entry_point = strpos( $msg, '.' ) )
				{
					$first_half  = substr( $msg, 0, $entry_point+1 );
					$second_half = substr( $msg, strlen( $first_half ));
					$msg       = "$first_half $timestamp. $second_half";
				}
				else
					$msg = "$timestamp: $msg";
			}
		}

		return $messages;
	}

	// Add the last modified timestamp to the 'Publish' meta box in post.php
	function publish_box()
	{
		$timestamp = sprintf( __('Last modified on: <strong>%1$s</strong>', 'dw-last-modified'), $this->construct_timestamp('publish-box') );
		echo '<div class="misc-pub-section misc-pub-section-last">' . $timestamp . '</div>';
	}

	// Append the new column to the columns array
	function column_heading( $columns )
	{
		$columns['last-modified'] = _x('Last Modified', 'column heading', 'dw-last-modified');
		return $columns;
	}

	// Put the last modified date in the content area
	function column_content( $column_name, $id )
	{
		if ( 'last-modified' == $column_name )
			echo $this->construct_timestamp('wp-table');
	}

	// Register the column as sortable
	function column_sort( $columns )
	{
		$columns['last-modified'] = 'modified';
	 	return $columns;
	}

	// Output CSS for width of new column
	function print_admin_css()
	{
		echo '<style type="text/css">.fixed .column-last-modified{width:12%;}.column-last-modified .dw-last-modified-author{display:block;}#message .dw-last-modified{font-weight:bold;}</style>'."\n";
	}

	public static function get_instance()
	{
		if ( is_null( self::$instance ) )
			self::$instance = new self();

		return self::$instance;
	}

} // DWLastModified

function get_the_dw_last_modified( $context = null, $override = null )
{
	return DWLastModified::get_instance()->construct_timestamp( $context, $override );
}

function the_dw_last_modified( $context = null, $override = null )
{
	echo get_the_dw_last_modified( $context, $override );
}

//	MAKE IT SO.
DWLastModified::get_instance();
