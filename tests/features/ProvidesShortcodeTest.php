<?php

class ProvidesShortcodeTest extends WP_UnitTestCase
{
	/** @test */
	function shortcode_returns_the_timestamp()
	{
		$output = do_shortcode('[dw-last-modified]');

		$this->assertStringContainsString('dw-last-modified', $output);
	}
}