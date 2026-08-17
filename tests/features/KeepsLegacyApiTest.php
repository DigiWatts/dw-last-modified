<?php

/**
 * The plugin was renamed from Last Modified Timestamp to DW Last Modified in
 * 1.2.0, and absorbed Last Modified By in 1.1.0. Both public APIs are kept
 * working; this covers them.
 */
class KeepsLegacyApiTest extends WP_UnitTestCase
{
	function given_a_post_modified_by($display_name)
	{
		$user_id = $this->factory->user->create(array('display_name' => $display_name));
		$post_id = $this->factory->post->create();

		update_post_meta($post_id, '_edit_last', $user_id);

		$GLOBALS['post'] = get_post($post_id);

		return $post_id;
	}

	/** @test */
	function keeps_the_pre_rename_template_tags()
	{
		$this->assertTrue(function_exists('get_the_last_modified_timestamp'));
		$this->assertTrue(function_exists('the_last_modified_timestamp'));

		$this->assertStringContainsString('dw-last-modified', get_the_last_modified_timestamp());

		ob_start();
		the_last_modified_timestamp();
		$output = ob_get_clean();

		$this->assertStringContainsString('dw-last-modified', $output);
	}

	/** @test */
	function keeps_the_pre_rename_class_name()
	{
		$this->assertTrue(class_exists('LastModifiedTimestamp'));
		$this->assertSame(DWLastModified::get_instance(), LastModifiedTimestamp::get_instance());
	}

	/** @test */
	function keeps_the_pre_rename_shortcode()
	{
		$this->assertTrue(shortcode_exists('last-modified'));
		$this->assertStringContainsString('dw-last-modified', do_shortcode('[last-modified]'));
	}

	/**
	 * Existing stylesheets target the old class names, so both are emitted.
	 *
	 * @test
	 */
	function keeps_the_pre_rename_css_classes()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		$output = get_the_dw_last_modified('wp-table');

		$this->assertStringContainsString('last-modified-timestamp', $output);
		$this->assertStringContainsString('last-modified-by', $output);
	}

	/** @test */
	function still_applies_the_pre_rename_defaults_filter()
	{
		add_filter('last_modified_timestamp_defaults', function ($defaults) {
			$defaults['base']['sep'] = 'LEGACY-SEP';

			return $defaults;
		});

		$this->assertStringContainsString('LEGACY-SEP', get_the_dw_last_modified());
	}

	/** @test */
	function still_applies_the_pre_rename_output_filter()
	{
		add_filter('last_modified_timestamp_output', function ($timestamp) {
			return $timestamp . 'LEGACY-OUTPUT';
		});

		$this->assertStringContainsString('LEGACY-OUTPUT', get_the_dw_last_modified());
	}

	/** @test */
	function still_applies_the_pre_rename_author_output_filter()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		add_filter('last_modified_by_output', function ($output) {
			return 'LEGACY-AUTHOR-HTML';
		});

		$this->assertStringContainsString('LEGACY-AUTHOR-HTML', get_the_dw_last_modified('wp-table'));
	}

	/** @test */
	function still_applies_the_pre_rename_display_name_filter()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		add_filter('the_modified_author', function () {
			return 'Legacy Name';
		});

		$this->assertStringContainsString('by Legacy Name', get_the_dw_last_modified('wp-table'));
	}

	/**
	 * The new filter runs first and the legacy one second, so a site with both
	 * hooked sees the legacy callback win rather than one silently dropping.
	 *
	 * @test
	 */
	function runs_the_legacy_output_filter_after_the_current_one()
	{
		add_filter('dw_last_modified_output', function ($timestamp) {
			return $timestamp . '|current';
		});

		add_filter('last_modified_timestamp_output', function ($timestamp) {
			return $timestamp . '|legacy';
		});

		$this->assertStringContainsString('|current|legacy', get_the_dw_last_modified());
	}
}
