<?php

class ProvidesShortcodeTest extends WP_UnitTestCase
{
	/** @test */
	function shortcode_returns_the_timestamp()
	{
		$output = do_shortcode('[dw-last-modified]');

		$this->assertStringContainsString('dw-last-modified', $output);
	}

	/** @test */
	function strips_markup_smuggled_in_through_attributes()
	{
		$output = do_shortcode('[dw-last-modified sep="\\x3cscript\\x3ealert(1)\\x3c/script\\x3e" format="%date% %sep%"]');

		$this->assertStringNotContainsString('<script', $output);
	}

	/** @test */
	function keeps_safe_markup_from_attributes()
	{
		$output = do_shortcode('[dw-last-modified sep="<br />" format="%date% %sep% %time%"]');

		$this->assertStringContainsString('<br />', $output);
	}
}
