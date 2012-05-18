<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-metabox.php';
require_once dirname(__FILE__) . '/icf-inflector.php';

class ICF_CustomPost
{
	protected $_post_type;
	protected $_enter_title_here;
	protected $_metaboxes = array();

	/**
	 * Constructor
	 *
	 * @param	string	$post_type
	 * @param	array	$args
	 */
	public function __construct($post_type, $args = array())
	{
		$this->_post_type = $post_type;
		$args = wp_parse_args($args);

		if (empty($args['label'])) {
			$args['label'] = $post_type;
		}

		if (empty($args['labels'])) {
			$args['labels'] = array(
				'name' => ICF_Inflector::humanize($this->_pluralize($args['label'])),
				'singular_name' => ICF_Inflector::humanize($this->_singularize($args['label'])),
				'add_new' => __('Add New', 'icf'),
				'add_new_item' => sprintf(__('Add New %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
				'edit_item' => sprintf(__('Edit %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
				'new_item' => sprintf(__('New %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
				'view_item' => sprintf(__('View %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
				'search_items' => sprintf(__('Search %s', 'icf'), ICF_Inflector::humanize($this->_pluralize($args['label']))),
				'not_found' => sprintf(__('No %s found.', 'icf'), strtolower($this->_pluralize($args['label']))),
				'not_found_in_trash' => sprintf(__('No %s found in Trash.', 'icf'), strtolower($this->_pluralize($args['label']))),
				'parent_item_colon' => sprintf(__('Parent %s:', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
				'all_items' => sprintf(__('All %s', 'icf'), ICF_Inflector::humanize($this->_pluralize($args['label'])))
			);
		}

		$thumbnail_support_types = get_theme_support('post-thumbnails');

		if (
			isset($args['supports'])
			&& in_array('thumbnail', (array)$args['supports'])
			&& (
				(
					is_array($thumbnail_support_types)
					&& !in_array($this->_post_type, $thumbnail_support_types[0])
				)
				|| (empty($thumbnail_support_types))
			)
		) {
			$thumbnail_support_types = empty($thumbnail_support_types)
									 ? array($this->_post_type)
									 : array_merge($thumbnail_support_types[0], (array)$this->_post_type);

			add_theme_support('post-thumbnails', $thumbnail_support_types);
		}

		if ($enter_title_here = icf_extract($args, 'enter_title_here')) {
			$this->_enter_title_here = $enter_title_here;
			add_filter('enter_title_here', array($this, 'rewrite_title_watermark'));
		}

		register_post_type($post_type, $args);
	}

	/**
	 * Rewrite title watermark
	 *
	 * @param	string	$title
	 */
	public function rewrite_title_watermark($title)
	{
		$screen = get_current_screen();

		if ($screen->post_type == $this->_post_type) {
			$title = $this->_enter_title_here;
		}

		return $title;
	}

	/**
	 * Register the taxonomy
	 *
	 * @param	string	$taxonomy
	 * @param	array	$args
	 */
	public function taxonomy($taxonomy, $args = array())
	{
		global $wp_taxonomies;
		$args = wp_parse_args($args);

		if (!isset($wp_taxonomies[$taxonomy])) {
			if (empty($args['label'])) {
				$args['label'] = $taxonomy;
			}

			if (empty($args['labels'])) {
				$args['labels'] = array(
					'name' => ICF_Inflector::humanize($this->_pluralize($args['label'])),
					'singular_name' => ICF_Inflector::humanize($this->_singularize($args['label'])),
					'search_items' => sprintf(__('Search %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
					'popular_items' => sprintf(__('Popular %s', 'icf'), ICF_Inflector::humanize($this->_pluralize($args['label']))),
					'all_items' => sprintf(__('All %s', 'icf'), ICF_Inflector::humanize($this->_pluralize($args['label']))),
					'parent_item' => sprintf(__('Parent %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
					'parent_item_colon' => sprintf(__('Parent %s:', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
					'edit_item' => sprintf(__('Edit %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
					'view_item' => sprintf(__('View %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
					'update_item' => sprintf(__('Update %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
					'add_new_item' => sprintf(__('Add New %s', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
					'new_item_name' => sprintf(__('New %s Name', 'icf'), ICF_Inflector::humanize($this->_singularize($args['label']))),
					'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'icf'), strtolower($this->_pluralize($args['label']))),
					'add_or_remove_items' => sprintf(__('Add or remove %s', 'icf'), strtolower($this->_pluralize($args['label']))),
					'choose_from_most_used' => sprintf(__('Choose from the most used %s', 'icf'), strtolower($this->_pluralize($args['label']))),
				);
			}

			register_taxonomy($taxonomy, $this->_post_type, $args);

		} else {
			register_taxonomy_for_object_type($taxonomy, $this->_post_type);
		}
	}

	/**
	 * Alias
	 *
	 * @see	ICF_CustomPost::taxonomy
	 */
	public function t($taxonomy, $args = array())
	{
		return $this->taxonomy($taxonomy, $args);
	}

	/**
	 * Create the meta box
	 *
	 * @param	string|ICF_MetaBox	$id
	 * @param	string				$title
	 * @param	array				$args
	 * @return	ICF_MetaBox
	 */
	public function metabox($id, $title = null, $args = array())
	{
		if (is_object($id) && is_a($id, 'ICF_MetaBox')) {
			$metabox = $id;
			$id = $metabox->get_id();

			if (isset($this->_metaboxes[$id])) {
				if ($this->_metaboxes[$id] !== $metabox) {
					$this->_metaboxes[$id] = $metabox;
				}

				return $metabox;
			}

		} else if (isset($this->_metaboxes[$id])) {
			return $this->_metaboxes[$id];

		} else {
			$metabox = new ICF_MetaBox($this->_post_type, $id, $title, $args);
		}

		$this->_metaboxes[$id] = $metabox;

		return $metabox;
	}

	/**
	 * Alias
	 *
	 * @see	ICF_CustomPost::metabox
	 */
	public function m($id, $title = null, $args = array())
	{
		return $this->metabox($id, $title, $args);
	}

	protected function _pluralize($text)
	{
		return preg_match('/[a-zA-Z]$/', $text) ? ICF_Inflector::pluralize($text) : $text;
	}

	protected function _singularize($text)
	{
		return preg_match('/[a-zA-Z]$/', $text) ? ICF_Inflector::singularize($text) : $text;
	}
}
