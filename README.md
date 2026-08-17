# DW Last Modified

[![Test](https://github.com/DigiWatts/dw-last-modified/actions/workflows/test.yml/badge.svg)](https://github.com/DigiWatts/dw-last-modified/actions/workflows/test.yml)

A WordPress plugin that makes the last modified date, time and modifying user more accessible in the admin.

It adds a sortable `Last Modified` column to every post-type list table showing the date, the time and who made the change, adds the timestamp to the Publish meta box and to the post-updated admin messages, and provides a `[dw-last-modified]` shortcode and template tags for front-end use.

## Installation

Not distributed through WordPress.org. Releases are published on GitHub and served to sites through the DigiWatts update proxy.

- **For a site:** register the slug `dw-last-modified` and this repo in *Tools → Plugin Update Manager*, and the update will appear in the WordPress admin like any other.
- **By hand:** download `dw-last-modified.zip` from the [latest release](https://github.com/DigiWatts/dw-last-modified/releases) and install it through *Plugins → Add New → Upload*.

The plugin header sets `Update URI`, which stops WordPress core from offering an unrelated wordpress.org plugin as an update.

## Usage

```php
// Template tags.
the_dw_last_modified();
$html = get_the_dw_last_modified( 'wp-table' );
```

```
[dw-last-modified]
[dw-last-modified format="%date% %sep% %time% %author%"]
```

Format placeholders are `%date%`, `%time%`, `%sep%` and `%author%`. The modifying user is only in the default format for the three wp-admin contexts (`wp-table`, `publish-box`, `messages`) so that the shortcode and template tags do not publish your editors' names on the front-end — add `%author%` to the format yourself if you want it there.

Filters: `dw_last_modified_defaults`, `dw_last_modified_output`, `dw_last_modified_author_output`, `dw_last_modified_author`. See `readme.txt` for the full reference, including which pre-rename name each one replaces.

The modifying user comes from the core `_edit_last` post meta, which WordPress only writes when a post is saved through wp-admin — REST, WP-CLI and importer edits have no user to show.

## Development

```bash
composer install
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
composer test
```

`db-host` accepts a socket as `localhost:/path/to/mysqld.sock`. CI runs the suite on PHP 7.4, 8.2 and 8.3 against WordPress latest.

## Releasing

Driven by [release-please](https://github.com/googleapis/release-please) from [Conventional Commits](https://www.conventionalcommits.org/) on `main`:

1. Commit with a conventional prefix (`feat:`, `fix:`, `dev:`, `perf:`).
2. release-please opens a release PR bumping `.release-please-manifest.json` and `CHANGELOG.md`. `bin/update-plugin-version.sh` then syncs that version into the plugin header, the `DW_LAST_MODIFIED_VERSION` constant and `readme.txt`.
3. Merging the PR tags the release, and `bin/build.sh` attaches `dw-last-modified.zip` to it.

Tags do not carry a `v` prefix. The zip contains a top-level `dw-last-modified/` directory, which is what the WordPress plugin updater expects.

## Credits and licence

DW Last Modified began as a fork of [Last Modified Timestamp](https://github.com/aaemnnosttv/last-modified-timestamp) by [Evan Mattson](https://aaemnnost.tv/), and has been maintained independently by DigiWatts since 1.1.0 — which also merged in [Last Modified By](https://github.com/erikdmitchell/last-modified-by). Version 1.2.0 renamed the plugin; every pre-rename shortcode, template tag, class name, filter and CSS class still works.

Copyright 2011–2013 Evan Mattson. Copyright 2026 Erik Mitchell / DigiWatts.

Licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
