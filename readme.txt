=== DW Last Modified ===
Stable tag: 1.3.0
Contributors: erikdmitchell
Tags: page modified, post modified, updated at, last modified, modified by
Requires at least: 4.6
Requires PHP: 7.4
Tested up to: 6.8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds the last modified date, time and modifying user to the admin interface, as well as a [dw-last-modified] shortcode to use on the front-end.

== Description ==

This plugin adds information to the admin interface about when each post/page was last modified and by whom (including custom post types!).

Enhanced areas:

1. Page/post admin tables - added `Last Modified` column which is also sortable, showing the date, time and modifying user.
1. Page/post edit screen (`post.php`) - added `Last modified on: *timestamp*` to `Publish` meta box.
1. Admin messages after editing a page/post - ie: `Post updated. *timestamp* View Post`

No options currently available, but the output can be fully customized with filters and the shortcode can be easily customized using attributes.

### Distribution

This plugin is not distributed through WordPress.org. It is released from GitHub and served to sites through the DigiWatts update proxy, so the plugin header carries an `Update URI` to stop WordPress core from offering an unrelated wp.org plugin as an update. To wire a site up, register the slug `dw-last-modified` and the repo in Tools -> Plugin Update Manager.

### History and credits

DW Last Modified began as a fork of [Last Modified Timestamp](https://github.com/aaemnnosttv/last-modified-timestamp) by Evan Mattson, and is maintained independently by DigiWatts from 1.1.0 onward. Version 1.1.0 merged in the [Last Modified By](https://github.com/erikdmitchell/last-modified-by) plugin, and 1.2.0 renamed the plugin. See "Upgrading from Last Modified Timestamp or Last Modified By" below.

### Gutenberg, WordPress 5, and Beyond

This plugin does not enhance the block editor introduced as the default in WordPress 5.0. Other areas of wp-admin enhanced by the plugin still work, as does the classic editor.

== Installation ==

1. Upload the `dw-last-modified` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

**Upgrading from Last Modified Timestamp or Last Modified By?**

Deactivate and delete both of those plugins - this one replaces them. Nothing you have written against them needs to change:

* The `[last-modified]` shortcode still works, alongside the new `[dw-last-modified]`.
* `get_the_last_modified_timestamp()` and `the_last_modified_timestamp()` still work, alongside `get_the_dw_last_modified()` and `the_dw_last_modified()`.
* The `LastModifiedTimestamp` class name still resolves, as an alias of `DWLastModified`.
* Every pre-rename filter is still applied - see the filter list below.
* The `last-modified-timestamp` and `last-modified-by` CSS classes are still emitted alongside the new `dw-last-modified` and `dw-last-modified-author` ones, so existing stylesheets keep working.

The compatibility layer is registered on `plugins_loaded`, and each piece is skipped if something else already owns that name, so leaving an old copy active degrades to duplicated output rather than a fatal error.

**How to add the last modified time to my page or post?**

This plugin does not change the public facing appearance of your website, but gives you a few ways to add this if you wish.

1. Using the `[dw-last-modified]` shortcode. See below.
2. Using template functions in your theme or plugin. See below.

**How to use the [dw-last-modified] shortcode?**

[dw-last-modified] Returns the last modified timestamp in this format `date seperator time`. The modifying user is not included by default - see below.

_Attributes (all optional)_

datef - specify a date format using the [PHP date format](http://www.php.net/manual/en/function.date.php).

timef - specify a time format using the [PHP date format](http://www.php.net/manual/en/function.date.php).

sep   - specify the character/text you want to use to separate the date & time.

authorf - define how the modifying user is rendered using the placeholder `%author%`.  Set it to an empty string to leave the user out entirely.

format - define the output format using placeholders `%date%`, `%time%`, `%sep%`, and `%author%`.  Other text can be used as well.  `%author%` is not part of the shortcode's default format; add it to include the modifying user, ie: `[dw-last-modified format="%date% %sep% %time% %author%"]`.

**Who is shown as having last modified a post?**

The user is read from the core `_edit_last` post meta, which WordPress writes when a post is saved from a wp-admin edit screen. Posts created or updated another way - through the REST API, WP-CLI, an importer or a cron job - have no such meta, so no user is shown for them. Nothing is shown either when the user who made the last edit has since been deleted.

The user is only included in the three wp-admin contexts (`wp-table`, `publish-box` and `messages`), so that the shortcode and template tags do not publish the names of your editors on the front-end. To include the user on the front-end, add the `%author%` placeholder to the format you are using.

To leave the user out of the output entirely, set `authorf` to an empty string:

`function my_dwlm_no_author( $d ) {

	$d['base']['authorf'] = '';

	return $d;
}
add_filter('dw_last_modified_defaults','my_dwlm_no_author');`

**How to change the outputted date/time format?**

By default, the plugin mimicks the time & date formats used in the same context (ie: admin tables, publish box) that WordPress uses, using PHP date format strings.

To customize the output with a shortcode, use the attributes as described above. To customize the output in an admin context, a filter may be used.

* **dw_last_modified_defaults** - allows default values to be filtered. Shortcode attributes override defaults when present, otherwise there are defaults for shortcode output as well.  Passes 2 parameters (array, context). Was `last_modified_timestamp_defaults`.
* **dw_last_modified_output** - allows the final timestamp html to be filtered.  Passes 2 parameters (html, context). Was `last_modified_timestamp_output`.
* **dw_last_modified_author_output** - allows the html for the modifying user to be filtered.  Passes 3 parameters (html, post id, display name). Was `last_modified_by_output`.
* **dw_last_modified_author** - allows the display name of the modifying user to be filtered.  Passes 2 parameters (display name, post id). Was `the_modified_author`, which collided with the WordPress core filter of the same name - that is why it was renamed.

Each pre-rename name is still applied, immediately after its current equivalent, so a callback on the old name still wins if both are hooked.

For example, if you wanted to change the time format in the admin messages that appear after a post is modified to a 24hr format with leading zeros, add this to your theme's functions.php:

`function my_dwlm_defaults( $d ) {

	$d['contexts']['messages']['timef'] = 'H:i';

	return $d;
}
add_filter('dw_last_modified_defaults','my_dwlm_defaults');`

**Template Tags**

Models the function naming convention used by WordPress for `get_the_content` / `the_content` and similar functions.

* `get_the_dw_last_modified()` - returns the timestamp.
* `the_dw_last_modified()` - displays/echos the timestamp.

These functions accept 2 arguments, both are optional:

* `$context` (string) to output formatted according to a defined context (ie: admin messages, posts table, etc.)
* `$override` (array) using this will override any defaults that are specified here, but output can still be overriden at final output.
Example array structure is: `array('datef' => 'M j, Y', 'timef' => 'g:i', 'sep' => '&rarr;', 'format' => '%date% %sep% %time%')`

== Changelog ==

= 1.2.0 =
* Renamed the plugin to `DW Last Modified`, slug `dw-last-modified`, and moved it to independent maintenance under DigiWatts. It is now released from GitHub through the DigiWatts update proxy rather than WordPress.org.
* Renamed the class to `DWLastModified`, the template tags to `get_the_dw_last_modified()` / `the_dw_last_modified()`, the shortcode to `[dw-last-modified]`, the text domain to `dw-last-modified`, and the filters to the `dw_last_modified_*` prefix. Every pre-rename name is kept working.
* Added an `Update URI` header, so WordPress core no longer offers the unrelated wp.org plugin of the old slug as an update.
* Added a release pipeline that builds and attaches `dw-last-modified.zip` to each GitHub release.

= 1.1.0 =
* Merged in the `Last Modified By` plugin. The `Last Modified` column, the publish box and the admin messages now include the user who last modified the post. **Deactivate `Last Modified By` when upgrading, or the user will be shown twice.**
* Added the `%author%` format placeholder and the `authorf` default/shortcode attribute. `%author%` is only in the default format for the wp-admin contexts, so front-end output is unchanged.
* Added the `last_modified_by_output` and `the_modified_author` filters
* Made the test suite runnable again on current PHP and PHPUnit, and replaced the WordPress.org deploy workflows with test CI

= 1.0.6 =
* Fix notice about loading translations too early
* Bump minimum required version of WP to 4.6

= 1.0.5 =
* Tweaked hook for testing
* Integrated GitHub Actions

= 1.0.4 =
* Add automated tests

= 1.0.3 =
* Template function bugfix

= 1.0.2 =
* Min required WP bump to >= 3.2
* PHP compatibility fix

= 1.0.1 =
* General housekeeping & maintanence
* Tested against 3.8

= 1.0 =
**Major Update**

* Added support for all custom post types.
* Added `[last-modified]` shortcode.
* Added filters to provide complete control.
* Added template tags.
* Encapsulated code.

= 0.4 =
* Added support for other types of update messages.
* Added filter to allow output to be customized.

= 0.3.1 =
* Fixed sortable column on pages table.

= 0.3 =
* The `Last Modified` column in the admin post/page tables is now sortable!
* CSS - widened `Last Modified` column to account for extra width needed for sortable arrow.
* Updated screenshot of `Last Modified` column in the admin post/page tables.
* Corrected a typo in the admin messages for pages.

= 0.2 =
* Fixed date formatting in the admin tables.

= 0.1 =
* Initial release
