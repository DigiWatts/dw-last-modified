<?php

class IncludesModifiedAuthorTest extends WP_UnitTestCase
{
	/**
	 * Creates a post whose last modification is attributed to a user with the
	 * given display name, and makes it the current post.
	 * @param  string 	$display_name
	 * @return int 					 	post id
	 */
	function given_a_post_modified_by($display_name)
	{
		$user_id = $this->factory->user->create(array('display_name' => $display_name));
		$post_id = $this->factory->post->create();

		update_post_meta($post_id, '_edit_last', $user_id);

		$GLOBALS['post'] = get_post($post_id);

		return $post_id;
	}

	/** @test */
	function includes_the_modifying_user_in_the_admin_contexts()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		foreach (array('wp-table', 'publish-box', 'messages') as $context) {
			$output = get_the_dw_last_modified($context);

			$this->assertStringContainsString('dw-last-modified-author', $output, "context: $context");
			$this->assertStringContainsString('by Erik Mitchell', $output, "context: $context");
		}
	}

	/** @test */
	function includes_the_modifying_user_in_the_admin_list_table_column()
	{
		$post_id = $this->given_a_post_modified_by('Erik Mitchell');

		ob_start();
		do_action('manage_post_posts_custom_column', 'last-modified', $post_id);
		$output = ob_get_clean();

		$this->assertStringContainsString('by Erik Mitchell', $output);
	}

	/** @test */
	function includes_the_modifying_user_in_the_post_publish_box()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		ob_start();
		do_action('post_submitbox_misc_actions', $GLOBALS['post']);
		$output = ob_get_clean();

		$this->assertStringContainsString('by Erik Mitchell', $output);
	}

	/**
	 * The plugin this was merged from only ran in wp-admin, so the front-end
	 * output must not start publishing editors' names.
	 *
	 * @test
	 */
	function omits_the_modifying_user_from_the_front_end_contexts()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		$this->assertStringNotContainsString('Erik Mitchell', get_the_dw_last_modified());
		$this->assertStringNotContainsString('Erik Mitchell', get_the_dw_last_modified('shortcode'));
		$this->assertStringNotContainsString('Erik Mitchell', do_shortcode('[dw-last-modified]'));
	}

	/** @test */
	function includes_the_modifying_user_in_the_shortcode_when_asked_to()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		$output = do_shortcode('[dw-last-modified format="%date% %author%"]');

		$this->assertStringContainsString('by Erik Mitchell', $output);
	}

	/** @test */
	function escapes_the_display_name()
	{
		$this->given_a_post_modified_by('<script>alert(1)</script>');

		$output = get_the_dw_last_modified('wp-table');

		$this->assertStringNotContainsString('<script>', $output);
	}

	/** @test */
	function omits_the_author_when_the_post_has_never_been_modified_in_wp_admin()
	{
		$GLOBALS['post'] = $this->factory->post->create_and_get();

		$output = get_the_dw_last_modified('wp-table');

		$this->assertStringContainsString('dw-last-modified', $output);
		$this->assertStringNotContainsString('dw-last-modified-author', $output);
	}

	/** @test */
	function omits_the_author_when_the_modifying_user_no_longer_exists()
	{
		$post_id = $this->factory->post->create();
		update_post_meta($post_id, '_edit_last', 987654321);
		$GLOBALS['post'] = get_post($post_id);

		$output = get_the_dw_last_modified('wp-table');

		$this->assertStringNotContainsString('dw-last-modified-author', $output);
	}

	/** @test */
	function leaves_no_dangling_whitespace_when_the_author_is_unknown()
	{
		$GLOBALS['post'] = $this->factory->post->create_and_get();

		$output = get_the_dw_last_modified('wp-table');

		$this->assertStringNotContainsString(' </span>', $output);
	}

	/** @test */
	function allows_the_author_format_to_be_overridden()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		$output = get_the_dw_last_modified('wp-table', array('authorf' => 'edited by %author%'));

		$this->assertStringContainsString('edited by Erik Mitchell', $output);
	}

	/** @test */
	function allows_the_author_to_be_dropped_by_emptying_the_author_format()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		$output = get_the_dw_last_modified('wp-table', array('authorf' => ''));

		$this->assertStringNotContainsString('Erik Mitchell', $output);
	}

	/** @test */
	function allows_the_author_output_to_be_filtered()
	{
		$post_id = $this->given_a_post_modified_by('Erik Mitchell');
		$test_case = $this;
		$called = false;

		add_filter('dw_last_modified_author_output', function ($output, $filtered_post_id, $author) use ($test_case, $post_id, &$called) {
			$called = true;

			$test_case->assertStringContainsString('dw-last-modified-author', $output);
			$test_case->assertEquals($post_id, $filtered_post_id);
			$test_case->assertSame('Erik Mitchell', $author);

			return 'the-replaced-author';
		}, 10, 3);

		$output = get_the_dw_last_modified('wp-table');

		if (! $called) {
			$this->fail();
		}

		$this->assertStringContainsString('the-replaced-author', $output);
	}

	/** @test */
	function allows_the_display_name_to_be_filtered()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		add_filter('dw_last_modified_author', function () {
			return 'Someone Else';
		});

		$output = get_the_dw_last_modified('wp-table');

		$this->assertStringContainsString('by Someone Else', $output);
	}

	/**
	 * Defaults filtered by code written against 1.0.x will not define authorf.
	 *
	 * @test
	 */
	function still_builds_a_timestamp_when_filtered_defaults_omit_the_author_format()
	{
		$this->given_a_post_modified_by('Erik Mitchell');

		add_filter('dw_last_modified_defaults', function ($defaults) {
			unset($defaults['base']['authorf']);

			return $defaults;
		});

		$output = get_the_dw_last_modified('wp-table');

		$this->assertStringContainsString('dw-last-modified', $output);
		$this->assertStringNotContainsString('dw-last-modified-author', $output);
	}
}
