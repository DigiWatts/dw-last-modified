<?php

class PostEditScreenTest extends WP_UnitTestCase
{
	/**
	 * The admin hooks are registered on admin_init, which the test bootstrap
	 * doesn't fire.
	 */
	function set_up()
	{
		parent::set_up();

		DWLastModified::get_instance()->admin_actions();
	}

	/** @test */
	function adds_timestamp_to_post_updated_messages()
	{
		$m = array();
		$m['post'] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => __( 'Post updated.' ),
			2 => __( 'Custom field updated.' ),
			3 => __( 'Custom field deleted.' ),
			4 => __( 'Post updated.' ),
			/* translators: %s: date and time of the revision */
//			5 => isset($_GET['revision']) ? sprintf( __( 'Post restored to revision from %s.' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => __( 'Post published.' ),
			7 => __( 'Post saved.' ),
			8 => __( 'Post submitted.' ),
//			9 => sprintf( __( 'Post scheduled for: %s.' ), '<strong>' . $scheduled_date . '</strong>' ) . $scheduled_post_link_html,
			10 => __( 'Post draft updated.' ),
		);
		$messages = apply_filters( 'post_updated_messages', $m );

		foreach ($messages['post'] as $message) {
			$this->assertStringContainsString('dw-last-modified', $message);
		}
	}

	/** @test */
	function adds_last_modified_to_post_publish_box()
	{
		ob_start();
		do_action('post_submitbox_misc_actions', $this->factory->post->create_and_get());
		$output = ob_get_clean();

		$this->assertStringContainsString('dw-last-modified', $output);
	}

	/** @test */
	function keeps_full_stops_inside_the_scheduled_date_intact()
	{
		$messages = apply_filters('post_updated_messages', array(
			'post' => array(9 => 'Geplant für: <strong>14.10.2026</strong>. <a href="https://example.org/?p=1">Vorschau</a>'),
		));

		$this->assertStringContainsString('<strong>14.10.2026</strong>. <span class="dw-last-modified', $messages['post'][9]);
		$this->assertStringEndsWith('</span>. <a href="https://example.org/?p=1">Vorschau</a>', $messages['post'][9]);
	}

	/** @test */
	function ignores_full_stops_in_the_trailing_link()
	{
		$messages = apply_filters('post_updated_messages', array(
			'post' => array(1 => 'Post updated. <a href="https://example.org/?p=1">View post</a>'),
		));

		$this->assertStringStartsWith('Post updated. <span class="dw-last-modified', $messages['post'][1]);
		$this->assertStringEndsWith('</span>. <a href="https://example.org/?p=1">View post</a>', $messages['post'][1]);
	}

	/** @test */
	function uses_the_last_full_stop_of_the_message()
	{
		$messages = apply_filters('post_updated_messages', array(
			'post' => array(5 => 'Wiederhergestellt von 14. Oktober 2026 um 13:00.'),
		));

		$this->assertStringStartsWith('Wiederhergestellt von 14. Oktober 2026 um 13:00. <span class="dw-last-modified', $messages['post'][5]);
	}
}
