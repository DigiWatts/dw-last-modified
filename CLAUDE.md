# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer install
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]   # one-time; needs svn and a MySQL server
composer test                                                                # full suite (phpunit)
vendor/bin/phpunit --filter reports_the_latest_release_as_an_update          # single test
vendor/bin/phpunit tests/features/ServesUpdatesTest.php                      # single file
bin/build.sh                                                                 # builds dw-last-modified.zip
```

- `db-host` accepts a socket as `localhost:/path/to/mysqld.sock`.
- The test bootstrap reads `WP_TESTS_DIR` (default `/tmp/wordpress-tests-lib`) and loads the plugin on `muplugins_loaded`.
- CI (`.github/workflows/test.yml`) runs on PHP 7.4, 8.2 and 8.3 against WordPress latest. Composer pins the platform to PHP 7.4, so code has to stay 7.4-compatible (no union types, `match`, nullsafe, etc.).
- There is no linter or coding-standards config.

## Architecture

The whole plugin is the single file `dw-last-modified.php`, with no runtime dependencies. The release zip holds only that file and `readme.txt` (no `vendor/`). Keep it that way: anything new must ship inside that file, or `bin/build.sh` has to be updated too.

The file has two classes and the template tags:

- **`DWLastModified`** (singleton, `get_instance()`): renders the timestamp. `get_defaults($context)` merges a per-context array (`messages`, `publish-box`, `wp-table`, `shortcode`) over `base`. `construct_timestamp()` then fills in the `%date%`, `%time%`, `%sep%` and `%author%` placeholders. Every admin surface (list-table column, Publish box, updated messages), the `[dw-last-modified]` shortcode and the template tags `get_the_dw_last_modified()` / `the_dw_last_modified()` all go through it. `%author%` is deliberately only in the wp-admin context formats, so editors' names don't end up on the front-end. The modifying user comes from the `_edit_last` meta, which only exists for edits saved through wp-admin.
- **`DWLastModifiedUpdater`**: serves updates from GitHub releases. The `Update URI` header makes core fire `update_plugins_github.com`, and the updater answers it by calling the unauthenticated `releases/latest` API. Results are cached in the `dw_last_modified_release` site transient for 12h; a failed lookup is cached for 1h as `''`. A release only counts if it has a `dw-last-modified.zip` asset. It also handles `plugins_api`, turning the release body (markdown) into the "View details" changelog HTML via `changelog_html()`. `check_for_update` deliberately returns releases that are *not* newer, because core needs them for `no_update` / the auto-update toggle.

### Backward compatibility (must be preserved)

The plugin was renamed in 1.2.0 (it was a fork of Last Modified Timestamp, merged with Last Modified By). Every pre-rename name still has to work:

- Filters go through `apply_filters_with_legacy()`, which applies the new name and then the legacy name (e.g. `dw_last_modified_output` → `last_modified_timestamp_output`). New filters that replace old ones should use it.
- `register_legacy_api()` runs on `plugins_loaded` priority 5 and only registers the `[last-modified]` shortcode, the `LastModifiedTimestamp` class alias and the `*_last_modified_timestamp()` functions when nothing else already defines them. This avoids "cannot redeclare" fatals if the upstream plugin is active too. Don't move these to file load.
- Output carries both CSS classes: `dw-last-modified last-modified-timestamp`.
- `tests/features/KeepsLegacyApiTest.php` covers this.

The full filter reference and legacy mapping are in `readme.txt`.

## Tests

The tests are feature tests under `tests/features/`, built on `WP_UnitTestCase` with yoast/phpunit-polyfills. Use the polyfill fixture names such as `set_up()` (snake_case), and write test methods as snake_case sentences marked with `/** @test */`. `ServesUpdatesTest` mocks the GitHub API with `pre_http_request` and needs to clear the release transient in `set_up()`.

## Releasing

Releases are driven by release-please from Conventional Commits on `main`. The configured changelog types are `feat`, `fix`, `dev` and `perf`.

- Don't hand-edit versions. release-please bumps `.release-please-manifest.json` and `CHANGELOG.md` in its release PR. CI then runs `bin/update-plugin-version.sh`, which copies the version into the `Version:` header, the `DW_LAST_MODIFIED_VERSION` constant and `readme.txt`'s `Stable tag`.
- Tags have no `v` prefix.
- The release PR is merged automatically by `release.yml`, in the same run that opened it. Since a merge made with `GITHUB_TOKEN` doesn't trigger another workflow run, that run calls release-please a second time to cut the release. Every releasable commit merged to `main` (`feat`, `fix`, `dev`, `perf`) therefore ships right away.
- On merge, `bin/build.sh` attaches the zip to the release. The zip must have a top-level `dw-last-modified/` directory so the WordPress updater replaces the installed plugin in place.
