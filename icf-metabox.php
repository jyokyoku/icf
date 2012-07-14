<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-component.php';

class ICF_MetaBox
{
	public $title;
	public $context;
	public $priority;
	public $capability;

	protected $_post_type;
	protected $_id;
	protected $_components = array();

	/**
	 * Constructor
	 *
	 * @param	string	$post_type
	 * @param	string	$id
	 * @param	string	$title
	 * @param	array	$args
	 */
	public function __construct($post_type, $id, $title = null, $args = array())
	{
		$args = wp_parse_args($args, array(
			'context' => 'normal', 'priority' => 'default', 'capability' => null, 'register' => true
		));

		$this->_post_type = $post_type;
		$this->_id = $id;

		$this->title = empty($title) ? $id : $title;
		$this->context = $args['context'];
		$this->priority = $args['priority'];
		$this->capability = $args['capability'];

		add_action('admin_print_scripts', array($this, 'add_scripts'));
		add_action('admin_print_styles', array($this, 'add_styles'));

		if ($args['register']) {
			add_action('admin_menu', array($this, 'register'));
		}

		add_action('save_post', array($this, 'save'));
	}

	/**
	 * Returns the post type of the meta box
	 *
	 * @return	string
	 */
	public function get_post_type()
	{
		return $this->_post_type;
	}

	/**
	 * Returns the meta box id
	 *
	 * @return	string
	 */
	public function get_id()
	{
		return $this->_id;
	}

	/**
	 * Create the ICF_MetaBox_Component object
	 *
	 * @param	id|ICF_MetaBox_Component	$id
	 * @param	string						$title
	 * @return	ICF_MetaBox_Component
	 */
	public function component($id, $title = null)
	{
		if (is_object($id) && is_a($id, 'ICF_MetaBox_Component')) {
			$component = $id;
			$id = $component->get_id();

			if (isset($this->_components[$id])) {
				if ($this->_components[$id] !== $component) {
					$this->_components[$id] = $component;
				}

				return $component;
			}

		} else if (isset($this->_components[$id])) {
			return $this->_components[$id];

		} else {
			$component = new ICF_MetaBox_Component($id, $title);
		}

		$this->_components[$id] = $component;

		return $component;
	}

	/**
	 * Alias
	 *
	 * @see	ICF_MetaBox::component
	 */
	public function c($id, $title = null)
	{
		return $this->component($id, $title);
	}

	/**
	 * Adds the script that is used by ICF
	 */
	public function add_scripts()
	{
		global $pagenow, $wp_scripts, $post;

		if (
			(isset($_GET['post_type']) && $_GET['post_type'] == $this->_post_type)
			|| (!isset($_GET['post_type']) && $pagenow == 'post-new.php' && $this->_post_type == 'post')
			|| (isset($post) && $post->post_type == $this->_post_type)
		) {
			ICF_Loader::register_javascript(array(
				'icf-metabox' => array(ICF_Loader::get_latest_version_url() . '/js/metabox.js', array('icf-common'), null, true)
			));
		}
	}

	/**
	 * Adds the css that is used by ICF
	 */
	public function add_styles()
	{
		global $pagenow, $post;

		if (
			(isset($_GET['post_type']) && $_GET['post_type'] == $this->_post_type)
			|| (!isset($_GET['post_type']) && $pagenow == 'post-new.php' && $this->_post_type == 'post')
			|| (isset($post) && $post->post_type == $this->_post_type)
		) {
			ICF_Loader::register_css();
		}
	}

	/**
	 * Register
	 */
	public function register()
	{
		if (empty($this->capability) || (!empty($this->capability) && current_user_can($this->capability))) {
			add_meta_box($this->_id, $this->title, array($this, 'render'), $this->_post_type, $this->context, $this->priority);
		}
	}

	/**
	 * Render the html
	 *
	 * @param	StdClass	$post
	 */
	public function render($post)
	{
		wp_nonce_field($this->_id, '_' . $this->_id . '_nonce');

		foreach ($this->_components as $component) {
			$component->render($post);
		}
	}

	/**
	 * Save the meta box data
	 *
	 * @param	int	$post_id
	 * @return	NULL|int
	 */
	public function save($post_id)
	{
		if (
			defined('DOING_AUTOSAVE') && DOING_AUTOSAVE
			|| empty($_POST['post_type'])
			|| $_POST['post_type'] != $this->_post_type
			|| (!empty($this->capability) && !current_user_can($this->capability, $post_id))
		) {
    		return $post_id;
		}

		$status_key = $this->_generate_uniq_id() . '_refresh';
		delete_option($status_key);

		$nonce = isset($_POST['_' . $this->_id . '_nonce']) ? $_POST['_' . $this->_id . '_nonce'] : '';

		if (!$nonce || !wp_verify_nonce($nonce, $this->_id)) {
			return $post_id;
		}

		foreach ($this->_components as $component) {
			$component->save($post_id);
		}
	}

	/**
	 * Refresh postmeta for all posts
	 *
	 * @param int $posts_per_page
	 * @param int $force
	 * @param boolean $force_start_first
	 * @return boolean
	 */
	public function refresh($posts_per_page = 0, $force = 0, $force_start_first = false)
	{
		global $wpdb;

		$return = true;
		$status_key = $this->_generate_uniq_id() . '_refresh';

		$status = $force_start_first ? false : get_option($status_key, false);
		delete_option($status_key);

		$query = "
			SELECT %s
			FROM $wpdb->posts as p
			WHERE p.post_status IN ('publish', 'draft') AND p.post_type = '{$this->_post_type}'
		";

		if ($status === false) {
			$posts_per_page = (int)$posts_per_page;
			$total = $wpdb->get_var(sprintf($query, 'COUNT(p.ID) as count'));

			if ($total <= 0) {
				return false;
			}

			if ($posts_per_page > 0 && $total > $posts_per_page) {
				$query .= " LIMIT {$posts_per_page}";
				update_option($status_key, serialize(array('posts_per_page' => $posts_per_page, 'page' => 1, 'force' => (bool)$force)));
			}

			$post_ids = $wpdb->get_col(sprintf($query, 'p.ID'));
			$return = false;

		} else if ($status) {
			$status = unserialize($status);

			if (!isset($status['posts_per_page'], $status['page'], $status['force'])) {
				return false;
			}

			$posts_per_page = (int)$status['posts_per_page'];
			$page = (int)$status['page'];
			$force = (boolean)$status['force'];

			$total = $wpdb->get_var(sprintf($query, 'COUNT(p.ID) as count'));
			$offset = $posts_per_page * $page;
			$max = $posts_per_page * ($page + 1);

			$query .= " LIMIT {$offset}, {$max}";

			if ($max > $count) {
				delete_option($status_key);
				$return = false;

			} else {
				update_option($status_key, serialize(array('posts_per_page' => $posts_per_page, 'page' => $page + 1, 'force' => (bool)$force)));
			}

			$post_ids = $wpdb->get_col(sprintf($query, 'p.ID'));
		}

		foreach ((array)$post_ids as $post_id) {
			foreach ($this->_components as $component) {
				$component->refresh($post_id, $force);
			}
		}

		return $return;
	}

	protected function _generate_uniq_id()
	{
		return 'icf_metabox_' . sha1(serialize($this->_components));
	}
}

class ICF_MetaBox_Component extends ICF_Component
{
	public $title;

	protected $_id;

	/**
	 * Constructor
	 *
	 * @param	string		$id
	 * @param	string		$title
	 */
	public function __construct($id, $title = '')
	{
		parent::__construct();

		$this->_id = $id;

		$this->title = (empty($title) && $title !== false) ? $id : $title;
	}

	/**
	 * Get the meta box id
	 *
	 * @return	string
	 */
	public function get_id()
	{
		return $this->_id;
	}

	/**
	 * Save the meta box data
	 *
	 * @param	int		$post_id
	 */
	public function save($post_id)
	{
		foreach ($this->_elements as $element) {
			if (is_subclass_of($element, 'ICF_MetaBox_Component_Element_FormField_Abstract')) {
				$element->save($post_id);
			}
		}
	}

	public function refresh($post_id, $force = false)
	{
		foreach ($this->_elements as $element) {
			if (is_subclass_of($element, 'ICF_MetaBox_Component_Element_FormField_Abstract')) {
				$element->refresh($post_id, $force);
			}
		}
	}

	public function render(stdClass $post)
	{
		if ($this->_stack) {
			$this->all_close();
		}

		$html = $this->title ? ICF_Tag::create('p', null, ICF_Tag::create('strong', null, $this->title)) : '';

		foreach ($this->_elements as $element) {
			if ($this->_element_trigger($element, 'before_render') === false) {
				continue;
			}

			$result = $element->render($post);

			if (($after = $this->_element_trigger($element, 'after_render', array($result))) && $after !== true) {
				$result = $after;
			}

			$html .= $result;
		}

		$this->clear();

		echo $html;
	}
}

abstract class ICF_MetaBox_Component_Element_FormField_Abstract extends ICF_Component_Element_FormField_Abstract
{
	public function __construct(ICF_MetaBox_Component $component, $name, $value = null, array $args = array())
	{
		parent::__construct($component, $name, $value, $args);
	}

	public function initialize()
	{
		parent::initialize();

		if (in_array('chkrequired', $this->_validation)) {
			$required_mark = '<span style="color: #B00C0C;">*</span>';

			if ($this->_component->title && !preg_match('|' . preg_quote($required_mark) . '$|', $this->_component->title)) {
				$this->_component->title .= ' ' . $required_mark;
			}
		}
	}

	public function save($post_id)
	{
		if (!isset($_POST[$this->_name])) {
			return false;
		}

		$value = $_POST[$this->_name];
		update_post_meta($post_id, $this->_name, $value);

		return true;
	}

	public function refresh($post_id, $force = false)
	{
		if ($force || get_post_meta($post_id, $this->_name, true) === false) {
			update_post_meta($post_id, $this->_name, $this->_value);
		}
	}
}

class ICF_MetaBox_Component_Element_Text extends ICF_MetaBox_Component_Element_FormField_Abstract
{
	public function render(stdClass $post = null)
	{
		if (isset($post->ID)) {
			$value = get_post_meta($post->ID, $this->_name, true);

			if ($value !== false && $value !== '') {
				$this->_value = $value;
			}
		}

		return ICF_Form::text($this->_name, $this->_value, $this->_args);
	}
}

class ICF_MetaBox_Component_Element_Checkbox extends ICF_MetaBox_Component_Element_FormField_Abstract
{
	public function render(stdClass $post = null)
	{
		if (isset($post->ID)) {
			$value = get_post_meta($post->ID, $this->_name, true);

			if ($value !== false && $value != '') {
				$this->_args['checked'] = ($value == $this->_value);
				unset($this->_args['selected']);
			}
		}

		return ICF_Form::checkbox($this->_name, $this->_value, $this->_args);
	}
}

class ICF_MetaBox_Component_Element_Radio extends ICF_MetaBox_Component_Element_FormField_Abstract
{
	public function render(stdClass $post = null)
	{
		if (isset($post->ID)) {
			$value = get_post_meta($post->ID, $this->_name, true);

			if ($value !== false && $value !== '') {
				$this->_args['checked'] = in_array($value, (array)$this->_value) ? $value : false;
				unset($this->_args['selected']);
			}
		}

		return ICF_Form::radio($this->_name, $this->_value, $this->_args);
	}
}

class ICF_MetaBox_Component_Element_Textarea extends ICF_MetaBox_Component_Element_FormField_Abstract
{
	public function render(stdClass $post = null)
	{
		if (isset($post->ID)) {
			$value = get_post_meta($post->ID, $this->_name, true);

			if ($value !== false && $value !== '') {
				$this->_value = $value;
			}
		}

		return ICF_Form::textarea($this->_name, $this->_value, $this->_args);
	}
}

class ICF_MetaBox_Component_Element_Select extends ICF_MetaBox_Component_Element_FormField_Abstract
{
	public function render(stdClass $post = null)
	{
		if (isset($post->ID)) {
			$value = get_post_meta($post->ID, $this->_name, true);

			if ($value !== false && $value !== '') {
				$this->_args['selected'] = in_array($value, (array)$this->_value) ? $value : false;
				unset($this->_args['checked']);
			}
		}

		return ICF_Form::select($this->_name, $this->_value, $this->_args);
	}
}

class ICF_MetaBox_Component_Element_Wysiwyg extends ICF_MetaBox_Component_Element_FormField_Abstract
{
	public function render()
	{
		trigger_error(__('The TinyMCE cannot be use to inside of a MetaBox', 'icf'), E_USER_NOTICE);
	}
}