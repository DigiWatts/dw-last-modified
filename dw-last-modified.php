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
	Requires PHP: 7.4
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

/**
 * Serves plugin updates from the project's GitHub releases.
 *
 * The `Update URI` header at the top of this file stops wordpress.org from
 * answering update checks for this slug, and makes core fire the
 * `update_plugins_github.com` filter on every check instead. Nothing is hooked
 * to that filter by default, so without this class the plugin reports no
 * updates at all.
 *
 * The repository is public, so the releases API is queried unauthenticated -
 * there is no token, and so nothing that needs a server-side proxy to hold one.
 */
class DWLastModifiedUpdater
{
	/**
	 * The repository releases are published from.
	 */
	const REPO = 'DigiWatts/dw-last-modified';

	/**
	 * The release asset to install from.
	 *
	 * bin/build.sh zips the plugin inside a top-level dw-last-modified/ folder,
	 * which is the layout core needs in order to replace an installed plugin in
	 * place. GitHub's auto-generated source archives use a
	 * dw-last-modified-{version}/ folder instead, which would install a second
	 * copy alongside the current one rather than over it - so a release that is
	 * missing this asset is deliberately treated as no release at all.
	 */
	const ASSET = 'dw-last-modified.zip';

	const CACHE_KEY = 'dw_last_modified_release';

	/**
	 * How long a successful lookup is cached, in seconds.
	 */
	const CACHE_TTL = 43200; // 12 hours

	/**
	 * How long a failed lookup is cached, in seconds.
	 *
	 * Unauthenticated GitHub allows 60 requests an hour per IP, which on shared
	 * hosting is an IP this site does not have to itself. Caching failures keeps
	 * a rate-limited or unreachable API from being re-queried on every load of
	 * the plugins screen.
	 */
	const FAILURE_TTL = 3600; // 1 hour

	/**
	 * @var string Absolute path to the main plugin file.
	 */
	private $file;

	/**
	 * @var string Plugin basename, e.g. dw-last-modified/dw-last-modified.php
	 */
	private $basename;

	/**
	 * @var string Plugin slug, e.g. dw-last-modified
	 */
	private $slug;

	/**
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct( $plugin_file )
	{
		$this->file = $plugin_file;

		// Matches the keys core uses for installed plugins, which is what the
		// update filter is handed to identify which plugin it is asking about.
		$this->basename = plugin_basename( $plugin_file );

		// Taken from the plugin's own directory rather than from the basename, so
		// that it stays correct when the directory is symlinked and
		// plugin_basename() cannot resolve it against WP_PLUGIN_DIR.
		$parent = dirname( $plugin_file );

		$this->slug = ( wp_normalize_path( $parent ) === wp_normalize_path( WP_PLUGIN_DIR ) )
			// A copy dropped straight into wp-content/plugins has no directory of
			// its own, so the file name is the slug. The zip built by
			// bin/build.sh always installs into a directory.
			? basename( $plugin_file, '.php' )
			: basename( $parent );

		add_filter( 'update_plugins_github.com', array( $this, 'check_for_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'flush_cache_after_update' ), 10, 2 );
	}

	/**
	 * Answers core's update check for this plugin.
	 * @param  array|false 	$update 		Update data from an earlier callback, or false.
	 * @param  array 		$plugin_data 	Headers of the plugin being checked.
	 * @param  string 		$plugin_file 	Basename of the plugin being checked.
	 * @return array|false 					Release data, or $update untouched.
	 */
	public function check_for_update( $update, $plugin_data, $plugin_file )
	{
		// The filter fires for every installed plugin whose Update URI points at
		// github.com, not only for this one.
		if ( $plugin_file !== $this->basename )
			return $update;

		$release = $this->get_release();

		if ( ! $release )
			return $update;

		// Returned whether or not it is newer than what is installed: core runs
		// the version comparison itself and files the result under either
		// `response` or `no_update`, and it is the `no_update` entry that makes
		// the auto-update toggle appear for the plugin.
		return array(
			'id'           => 'github.com/' . self::REPO,
			'slug'         => $this->slug,
			'plugin'       => $this->basename,
			'version'      => $release['version'],
			'url'          => isset( $plugin_data['PluginURI'] ) ? $plugin_data['PluginURI'] : '',
			'package'      => $release['package'],
			'tested'       => $this->readme_field( 'Tested up to' ),
			'requires'     => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
			'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
		);
	}

	/**
	 * Supplies the "View details" modal, which core would otherwise request from
	 * wordpress.org - where this plugin is not published.
	 * @param  false|object|array 	$result 	Result from an earlier callback.
	 * @param  string 				$action 	The API action being performed.
	 * @param  object 				$args 		Arguments for the request.
	 * @return false|object|array
	 */
	public function plugin_information( $result, $action, $args )
	{
		if ( 'plugin_information' !== $action )
			return $result;

		if ( ! isset( $args->slug ) || $args->slug !== $this->slug )
			return $result;

		$release = $this->get_release();

		if ( ! $release )
			return $result;

		if ( ! function_exists( 'get_plugin_data' ) )
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$data = get_plugin_data( $this->file, false, false );

		return (object) array(
			'name'          => $data['Name'],
			'slug'          => $this->slug,
			'version'       => $release['version'],
			'author'        => $this->author_link( $data ),
			'homepage'      => $data['PluginURI'],
			'download_link' => $release['package'],
			'requires'      => isset( $data['RequiresWP'] ) ? $data['RequiresWP'] : '',
			'requires_php'  => isset( $data['RequiresPHP'] ) ? $data['RequiresPHP'] : '',
			'tested'        => $this->readme_field( 'Tested up to' ),
			'last_updated'  => $release['published'],
			'sections'      => array(
				'description' => wpautop( wp_kses_post( $data['Description'] ) ),
				'changelog'   => $this->changelog_html( $release['changelog'] ),
			),
		);
	}

	/**
	 * Drops the cached release once an update has run, so that the plugins screen
	 * stops offering a version which is now installed.
	 * @param  WP_Upgrader 	$upgrader 		The upgrader that ran.
	 * @param  array 		$hook_extra 	Details of what was upgraded.
	 * @return void
	 */
	public function flush_cache_after_update( $upgrader, $hook_extra )
	{
		if ( ! isset( $hook_extra['action'], $hook_extra['type'] ) )
			return;

		if ( 'update' !== $hook_extra['action'] || 'plugin' !== $hook_extra['type'] )
			return;

		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Returns the latest published release, from cache where possible.
	 * @return array|false 	Keys are version, package, changelog and published.
	 */
	private function get_release()
	{
		$cached = $this->is_forced_check() ? false : get_site_transient( self::CACHE_KEY );

		if ( is_array( $cached ) )
			return $cached;

		// A failed lookup is cached as an empty string, which is distinguishable
		// from the false of a transient that is simply absent or expired.
		if ( '' === $cached )
			return false;

		$release = $this->fetch_release();

		if ( ! $release )
		{
			set_site_transient( self::CACHE_KEY, '', self::FAILURE_TTL );
			return false;
		}

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Requests the latest release from the GitHub API.
	 *
	 * The `latest` endpoint is used rather than the full release list because it
	 * already excludes drafts and prereleases, so neither can be offered to
	 * sites as an update.
	 *
	 * @return array|false 	Release data, or false if none is usable.
	 */
	private function fetch_release()
	{
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) )
			return false;

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $release ) || empty( $release['tag_name'] ) )
			return false;

		$package = $this->find_asset( isset( $release['assets'] ) ? $release['assets'] : array() );

		if ( ! $package )
			return false;

		return array(
			// Tags are published without a leading v, but one is tolerated so
			// that a hand-cut tag does not read as a different version than the
			// one in the plugin header.
			'version'   => ltrim( (string) $release['tag_name'], 'vV' ),
			'package'   => $package,
			'changelog' => isset( $release['body'] ) ? (string) $release['body'] : '',
			'published' => isset( $release['published_at'] ) ? (string) $release['published_at'] : '',
		);
	}

	/**
	 * Finds the download URL of the built plugin zip among a release's assets.
	 * @param  array 		$assets 	Assets as returned by the API.
	 * @return string|false 			Download URL, or false when absent.
	 */
	private function find_asset( $assets )
	{
		if ( ! is_array( $assets ) )
			return false;

		foreach ( $assets as $asset )
		{
			if ( isset( $asset['name'], $asset['browser_download_url'] ) && self::ASSET === $asset['name'] )
				return $asset['browser_download_url'];
		}

		return false;
	}

	/**
	 * Reads a single field out of the readme header.
	 *
	 * Values like `Tested up to` only exist in readme.txt, not in the plugin
	 * header, and core needs them to render the compatibility line on the
	 * plugins screen.
	 *
	 * @param  string 	$field 		Field name, e.g. 'Tested up to'.
	 * @return string 				Field value, or an empty string.
	 */
	private function readme_field( $field )
	{
		$readme = plugin_dir_path( $this->file ) . 'readme.txt';

		if ( ! is_readable( $readme ) )
			return '';

		// Only the header block is of interest, so the opening bytes are read
		// rather than the whole file.
		$head = (string) file_get_contents( $readme, false, null, 0, 1024 );

		if ( preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*(.+)$/mi', $head, $matches ) )
			return trim( $matches[1] );

		return '';
	}

	/**
	 * Builds the linked author string the details modal expects.
	 * @param  array 	$data 	Plugin headers from get_plugin_data().
	 * @return string 			Author name, linked when an author URI is set.
	 */
	private function author_link( $data )
	{
		if ( empty( $data['Author'] ) )
			return '';

		if ( empty( $data['AuthorURI'] ) )
			return esc_html( $data['Author'] );

		return '<a href="' . esc_url( $data['AuthorURI'] ) . '">' . esc_html( $data['Author'] ) . '</a>';
	}

	/**
	 * Renders a release body as the HTML the details modal expects.
	 *
	 * Release notes are generated as Markdown by release-please, so the small
	 * subset it emits - headings, bullet lists, links and code spans - is
	 * converted rather than dropping raw Markdown into the modal.
	 *
	 * @param  string 	$markdown 	Release body.
	 * @return string 				HTML
	 */
	private function changelog_html( $markdown )
	{
		$markdown = (string) $markdown;

		if ( '' === trim( $markdown ) )
			return '<p>' . esc_html__( 'No release notes were published for this version.', 'dw-last-modified' ) . '</p>';

		// Escaped up front so that everything below is inserting known-safe tags
		// into already-escaped text.
		$html = esc_html( $markdown );

		$html = preg_replace_callback(
			'/\[([^\]]+)\]\((https?:[^\s)]+)\)/',
			function ( $matches ) {
				return '<a href="' . esc_url( html_entity_decode( $matches[2], ENT_QUOTES ) ) . '">' . $matches[1] . '</a>';
			},
			$html
		);

		$html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );

		$output  = '';
		$in_list = false;

		foreach ( preg_split( '/\R/', $html ) as $line )
		{
			$line = trim( $line );

			if ( preg_match( '/^(#{1,6})\s+(.*)$/', $line, $matches ) )
			{
				$output .= $this->close_list( $in_list );

				// Release notes start at ##, stepped down one level so that the
				// modal's own section heading stays above them in the outline.
				$level   = min( 6, strlen( $matches[1] ) + 1 );
				$output .= "<h{$level}>{$matches[2]}</h{$level}>";
				continue;
			}

			if ( preg_match( '/^[*-]\s+(.*)$/', $line, $matches ) )
			{
				if ( ! $in_list )
				{
					$output .= '<ul>';
					$in_list = true;
				}

				$output .= '<li>' . $matches[1] . '</li>';
				continue;
			}

			$output .= $this->close_list( $in_list );

			if ( '' !== $line )
				$output .= '<p>' . $line . '</p>';
		}

		return $output . $this->close_list( $in_list );
	}

	/**
	 * Closes an open list, clearing the caller's flag by reference.
	 * @param  bool 	$in_list 	Whether a list is currently open.
	 * @return string 				The closing tag, or an empty string.
	 */
	private function close_list( &$in_list )
	{
		if ( ! $in_list )
			return '';

		$in_list = false;

		return '</ul>';
	}

	/**
	 * Whether this request is the "Check again" button on the updates screen.
	 *
	 * Core does not pass that intent through to the update filters, so the
	 * request itself has to be inspected in order to bypass the cache.
	 *
	 * @return bool
	 */
	private function is_forced_check()
	{
		return is_admin() && ! empty( $_GET['force-check'] );
	}

} // DWLastModifiedUpdater

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

new DWLastModifiedUpdater( __FILE__ );
