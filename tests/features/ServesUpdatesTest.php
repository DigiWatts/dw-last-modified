<?php

class ServesUpdatesTest extends WP_UnitTestCase
{
    /** @var int Number of GitHub API requests the current test has made. */
    private $requests = 0;

    /** @var string */
    private $plugin_file;

    public function set_up()
    {
        parent::set_up();

        $this->requests    = 0;
        $this->plugin_file = dirname(dirname(__DIR__)) . '/dw-last-modified.php';

        delete_site_transient('dw_last_modified_release');
    }

    /** @test */
    function reports_the_latest_release_as_an_update()
    {
        $this->mock_release($this->release());

        $update = $this->updater()->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertIsArray($update);
        $this->assertSame('9.9.9', $update['version']);
        $this->assertSame(
            'https://github.com/DigiWatts/dw-last-modified/releases/download/9.9.9/dw-last-modified.zip',
            $update['package']
        );
        $this->assertSame($this->basename(), $update['plugin']);
        $this->assertSame('7.4', $update['requires_php']);
        $this->assertSame('4.6', $update['requires']);
    }

    /** @test */
    function reports_the_release_even_when_it_is_not_newer_than_the_installed_version()
    {
        // Core runs the version comparison itself and files anything that is not
        // newer under `no_update`, which is the entry that makes the auto-update
        // toggle appear. Filtering old releases out here would suppress it.
        $this->mock_release($this->release(array('tag_name' => '0.0.1')));

        $update = $this->updater()->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertIsArray($update);
        $this->assertSame('0.0.1', $update['version']);
    }

    /** @test */
    function reads_the_tested_up_to_value_out_of_the_readme()
    {
        $this->mock_release($this->release());

        $update = $this->updater()->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertMatchesRegularExpression('/^\d+\.\d/', $update['tested']);
    }

    /** @test */
    function tolerates_a_tag_cut_with_a_leading_v()
    {
        $this->mock_release($this->release(array('tag_name' => 'v9.9.9')));

        $update = $this->updater()->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertSame('9.9.9', $update['version']);
    }

    /** @test */
    function leaves_other_plugins_on_the_same_filter_alone()
    {
        $this->mock_release($this->release());

        $update = $this->updater()->check_for_update(false, $this->plugin_data(), 'some-other/plugin.php');

        $this->assertFalse($update);
        $this->assertSame(0, $this->requests, 'The API should not be queried for another plugin.');
    }

    /** @test */
    function ignores_a_release_that_is_missing_the_built_zip()
    {
        // GitHub's own source archive is deliberately not accepted as a fallback:
        // its folder is named dw-last-modified-{version}, so installing it would
        // leave a second copy of the plugin behind rather than replace this one.
        $this->mock_release($this->release(array(
            'assets' => array(
                array(
                    'name'                 => 'something-else.zip',
                    'browser_download_url' => 'https://example.com/something-else.zip',
                ),
            ),
        )));

        $update = $this->updater()->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertFalse($update);
    }

    /** @test */
    function ignores_an_api_error()
    {
        $this->mock_release('{"message":"Not Found"}', 404);

        $update = $this->updater()->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertFalse($update);
    }

    /** @test */
    function passes_an_earlier_callbacks_update_through_untouched()
    {
        $this->mock_release('{"message":"Not Found"}', 404);

        $existing = array('version' => '1.0.0');

        $update = $this->updater()->check_for_update($existing, $this->plugin_data(), 'some-other/plugin.php');

        $this->assertSame($existing, $update);
    }

    /** @test */
    function caches_a_successful_lookup()
    {
        $this->mock_release($this->release());

        $updater = $this->updater();
        $updater->check_for_update(false, $this->plugin_data(), $this->basename());
        $updater->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertSame(1, $this->requests);
    }

    /** @test */
    function caches_a_failed_lookup()
    {
        // Unauthenticated GitHub allows 60 requests an hour per IP, so a failure
        // that is not cached would be retried on every load of the plugins screen.
        $this->mock_release('', 500);

        $updater = $this->updater();
        $updater->check_for_update(false, $this->plugin_data(), $this->basename());
        $updater->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertSame(1, $this->requests);
    }

    /** @test */
    function drops_the_cache_once_a_plugin_update_has_run()
    {
        $this->mock_release($this->release());

        $updater = $this->updater();
        $updater->check_for_update(false, $this->plugin_data(), $this->basename());

        $updater->flush_cache_after_update(null, array('action' => 'update', 'type' => 'plugin'));

        $updater->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertSame(2, $this->requests);
    }

    /** @test */
    function keeps_the_cache_when_something_other_than_a_plugin_was_updated()
    {
        $this->mock_release($this->release());

        $updater = $this->updater();
        $updater->check_for_update(false, $this->plugin_data(), $this->basename());

        $updater->flush_cache_after_update(null, array('action' => 'update', 'type' => 'theme'));

        $updater->check_for_update(false, $this->plugin_data(), $this->basename());

        $this->assertSame(1, $this->requests);
    }

    /** @test */
    function derives_the_slug_from_the_plugin_directory()
    {
        // The details modal is matched on this, and core keys installed plugins
        // by it, so it has to be the directory name the zip installs into
        // regardless of where the plugin is loaded from.
        $this->mock_release($this->release());

        $information = $this->updater()->plugin_information(false, 'plugin_information', (object) array('slug' => 'dw-last-modified'));

        $this->assertIsObject($information);
        $this->assertSame('dw-last-modified', $information->slug);
    }

    /** @test */
    function supplies_the_view_details_modal()
    {
        $this->mock_release($this->release());

        $information = $this->updater()->plugin_information(false, 'plugin_information', (object) array('slug' => $this->slug()));

        $this->assertIsObject($information);
        $this->assertSame('DW Last Modified', $information->name);
        $this->assertSame('9.9.9', $information->version);
        $this->assertSame('2026-01-15T12:00:00Z', $information->last_updated);
        $this->assertStringContainsString('dw-last-modified.zip', $information->download_link);
        $this->assertArrayHasKey('description', $information->sections);
        $this->assertArrayHasKey('changelog', $information->sections);
    }

    /** @test */
    function leaves_details_requests_for_other_plugins_alone()
    {
        $this->mock_release($this->release());

        $information = $this->updater()->plugin_information(false, 'plugin_information', (object) array('slug' => 'akismet'));

        $this->assertFalse($information);
        $this->assertSame(0, $this->requests);
    }

    /** @test */
    function leaves_other_api_actions_alone()
    {
        $this->mock_release($this->release());

        $information = $this->updater()->plugin_information(false, 'query_plugins', (object) array('slug' => $this->slug()));

        $this->assertFalse($information);
    }

    /** @test */
    function renders_release_notes_as_html()
    {
        $this->mock_release($this->release(array(
            'body' => "## Features\n\n* Added [a thing](https://example.com/thing)\n* Added `a_function()`\n\nA closing note.\n",
        )));

        $changelog = $this->changelog();

        // release-please starts its notes at ##, stepped down so the modal's own
        // section heading stays above them.
        $this->assertStringContainsString('<h3>Features</h3>', $changelog);
        $this->assertStringContainsString('<ul>', $changelog);
        $this->assertStringContainsString('</ul>', $changelog);
        $this->assertStringContainsString('<a href="https://example.com/thing">a thing</a>', $changelog);
        $this->assertStringContainsString('<code>a_function()</code>', $changelog);
        $this->assertStringContainsString('<p>A closing note.</p>', $changelog);
    }

    /** @test */
    function escapes_html_in_release_notes()
    {
        $this->mock_release($this->release(array(
            'body' => "* Fixed <script>alert(1)</script>\n",
        )));

        $changelog = $this->changelog();

        $this->assertStringNotContainsString('<script>', $changelog);
        $this->assertStringContainsString('&lt;script&gt;', $changelog);
    }

    /** @test */
    function handles_a_release_published_with_no_notes()
    {
        $this->mock_release($this->release(array('body' => '')));

        $this->assertStringContainsString('No release notes', $this->changelog());
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function updater()
    {
        return new DWLastModifiedUpdater($this->plugin_file);
    }

    private function basename()
    {
        return plugin_basename($this->plugin_file);
    }

    private function slug()
    {
        return 'dw-last-modified';
    }

    private function changelog()
    {
        $information = $this->updater()->plugin_information(false, 'plugin_information', (object) array('slug' => $this->slug()));

        return $information->sections['changelog'];
    }

    /**
     * Intercepts the GitHub API request and answers it with the given payload,
     * counting how many times it is called.
     */
    private function mock_release($body, $code = 200)
    {
        add_filter('pre_http_request', function ($preempt, $args, $url) use ($body, $code) {
            if (false === strpos($url, 'api.github.com')) {
                return $preempt;
            }

            $this->requests++;

            return array(
                'response' => array('code' => $code, 'message' => ''),
                'body'     => is_array($body) ? wp_json_encode($body) : $body,
                'headers'  => array(),
                'cookies'  => array(),
            );
        }, 10, 3);
    }

    private function release($overrides = array())
    {
        return array_merge(array(
            'tag_name'     => '9.9.9',
            'published_at' => '2026-01-15T12:00:00Z',
            'body'         => "## Features\n\n* Something new\n",
            'assets'       => array(
                array(
                    'name'                 => 'dw-last-modified.zip',
                    'browser_download_url' => 'https://github.com/DigiWatts/dw-last-modified/releases/download/9.9.9/dw-last-modified.zip',
                ),
            ),
        ), $overrides);
    }

    private function plugin_data()
    {
        return array(
            'Name'        => 'DW Last Modified',
            'PluginURI'   => 'https://github.com/DigiWatts/dw-last-modified',
            'Version'     => DW_LAST_MODIFIED_VERSION,
            'RequiresWP'  => '4.6',
            'RequiresPHP' => '7.4',
            'UpdateURI'   => 'https://github.com/DigiWatts/dw-last-modified',
        );
    }
}
