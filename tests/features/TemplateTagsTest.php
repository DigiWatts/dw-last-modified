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
}