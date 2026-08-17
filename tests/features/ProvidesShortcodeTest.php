<?php

class ProvidesShortcodeTest extends WP_UnitTestCase
{
	/** @test */
	function shortcode_returns_last_modified_timestamp()
	{
		$output = do_shortcode('[last-modified]');

		$this->assertStringContainsString('last-modified-timestamp', $output);
	}
}