<?php

class AdminListTableColumnTest extends WP_UnitTestCase
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

	/**
	 * @test
	 * @dataProvider provide_all_post_types
	 */
	function adds_last_modified_column_to_all_post_type_list_tables($post_type)
	{
		$post_columns = apply_filters("manage_{$post_type}_posts_columns", array());

		$this->assertArrayHasKey('last-modified', $post_columns);
	}

	/**
	 * @test
	 * @dataProvider provide_all_post_types
	 */
	function outputs_timestamp_in_last_modified_column($post_type)
	{
		ob_start();
		$post_id = $this->factory->post->create(array('post_type' => $post_type));
		do_action("manage_{$post_type}_posts_custom_column", 'last-modified', $post_id);
		$output = ob_get_clean();

		$this->assertStringContainsString('dw-last-modified', $output);
	}

	/**
	 * @test
	 * @dataProvider provide_all_post_types
	 */
	function registers_the_last_modified_column_as_sortable($post_type)
	{
		$sortable = apply_filters("manage_edit-{$post_type}_sortable_columns", array());

		$this->assertArrayHasKey('last-modified', $sortable);
	}

	/** @test */
	function registers_admin_hooks_on_admin_init_rather_than_init()
	{
		$instance = DWLastModified::get_instance();

		$this->assertSame(10, has_action('admin_init', array($instance, 'admin_actions')));
		$this->assertFalse(has_action('init', array($instance, 'admin_actions')));
	}

	/** @test */
	function shows_the_row_post_rather_than_the_global_post()
	{
		$row  = $this->given_a_post_modified_by('Row Editor');
		$this->given_a_post_modified_by('Global Editor');
		$GLOBALS['post'] = get_post($this->given_a_post_modified_by('Global Editor'));

		$this->assertStringContainsString('Row Editor', $this->render_column($row));
	}

	/** @test */
	function looks_up_the_modifying_users_of_every_row_in_one_go()
	{
		$ids = array();

		foreach (array('First Editor', 'Second Editor', 'Third Editor') as $name) {
			$ids[] = $this->given_a_post_modified_by($name);
		}

		$GLOBALS['wp_query'] = new WP_Query(array('post__in' => $ids, 'orderby' => 'post__in'));

		$this->assertStringContainsString('First Editor', $this->render_column($ids[0]));

		$queries = get_num_queries();

		$this->assertStringContainsString('Second Editor', $this->render_column($ids[1]));
		$this->assertStringContainsString('Third Editor', $this->render_column($ids[2]));

		$this->assertSame($queries, get_num_queries(), 'Later rows should not query for their users.');
	}

	/**
	 * Creates a post last modified by a new user with the given display name,
	 * with the user evicted from the cache as it would be on a fresh request.
	 * @param  string 	$display_name
	 * @return int 					 	post id
	 */
	function given_a_post_modified_by($display_name)
	{
		$user_id = $this->factory->user->create(array('display_name' => $display_name));
		$post_id = $this->factory->post->create();

		update_post_meta($post_id, '_edit_last', $user_id);
		clean_user_cache($user_id);

		return $post_id;
	}

	function render_column($post_id)
	{
		ob_start();
		do_action('manage_post_posts_custom_column', 'last-modified', $post_id);

		return ob_get_clean();
	}

	function provide_all_post_types()
	{
		return array_map(function($name) {
			return (array) $name;
		}, get_post_types());
	}
}