<?php

class TemplateTagsTest extends WP_UnitTestCase
{
	/** @test */
	function can_get_the_timestamp_html_with_a_function()
	{
		$output = get_the_dw_last_modified();

		$this->assertStringContainsString('dw-last-modified', $output);
	}

	/** @test */
	function can_output_the_timestamp_html_with_a_function()
	{
		ob_start();
		the_dw_last_modified();
		$output = ob_get_clean();

		$this->assertStringContainsString('dw-last-modified', $output);
	}

	/** @test */
	function can_get_the_timestamp_for_a_given_post_outside_the_loop()
	{
		global $wpdb;

		// wp_insert_post() always stamps the current time as the modified date.
		$post_id = $this->factory->post->create();
		$wpdb->update($wpdb->posts, array('post_modified' => '2020-02-03 04:05:06', 'post_modified_gmt' => '2020-02-03 04:05:06'), array('ID' => $post_id));
		clean_post_cache($post_id);

		$GLOBALS['post'] = $this->factory->post->create_and_get();

		$output = get_the_dw_last_modified(null, array('datef' => 'Y-m-d'), $post_id);

		$this->assertStringContainsString('2020-02-03', $output);
	}
}
