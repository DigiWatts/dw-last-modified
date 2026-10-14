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

	function provide_all_post_types()
	{
		return array_map(function($name) {
			return (array) $name;
		}, get_post_types());
	}
}