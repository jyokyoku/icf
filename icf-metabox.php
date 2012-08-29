<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-loader.php';
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
	 * Returns the post type
	 *
	 * @return	string
	 */
	public function get_post_type()
	{
		return $this->_post_type;
	}

	/**
	 * Returns the id
	 *
	 * @return	string
	 */
	public function get_id()
	{
		return $this->_id;
	}

	/**
	 * Creates the ICF_MetaBox_Component
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
			$component = new ICF_MetaBox_Component($this, $id, $title);
		}

		$this->_components[$id] = $component;

		return $component;
	}

	/**
	 * Alias of 'component' method
	 *
	 * @param	id|ICF_MetaBox_Component	$id
	 * @param	string						$title
	 * @return	ICF_MetaBox_Component
	 * @see		ICF_MetaBox::component
	 */
	public function c($id, $title = null)
	{
		return $this->component($id, $title);
	}

	/**
	 * Adds the scripts used by ICF
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
	 * Adds the stylesheets used by ICF
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
	 * Registers to system
	 */
	public function register()
	{
		if (empty($this->capability) || (!empty($this->capability) && current_user_can($this->capability))) {
			add_meta_box($this->_id, $this->title, array($this, 'display'), $this->_post_type, $this->context, $this->priority);
		}
	}

	/**
	 * Displays the rendered html
	 *
	 * @param	StdClass	$post
	 */
	public function display($post)
	{
		$uniq_id = $this->_generate_uniq_id();
		wp_nonce_field($uniq_id, $uniq_id . '_nonce');

		foreach ($this->_components as $component) {
			$component->display($post);
		}
	}

	/**
	 * Saves the components
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

		$uniq_id = $this->_generate_uniq_id();

		$refresh_params_key = $uniq_id . '_refresh';
		delete_option($refresh_params_key);

		$nonce = isset($_POST[$uniq_id. '_nonce']) ? $_POST[$uniq_id . '_nonce'] : '';

		if (!$nonce || !wp_verify_nonce($nonce, $uniq_id)) {
			return $post_id;
		}

		foreach ($this->_components as $component) {
			$component->save($post_id);
		}
	}

	/**
	 * Saves the default data of components when data is not registered
	 *
	 * @param	int		$posts_per_process
	 * @param	int		$force_default_all
	 * @param	boolean	$force_start_first
	 * @return	boolean
	 */
	public function refresh($posts_per_process = 0, $force_default_all = false, $force_start_first = false)
	{
		global $wpdb;

		$return = true;
		$params_key = $this->_generate_uniq_id() . '_refresh';

		$params = $force_start_first ? false : get_option($params_key, false);
		delete_option($params_key);

		$query = "
			SELECT %s
			FROM {$wpdb->posts} as p
			WHERE p.post_status IN ('publish', 'draft') AND p.post_type = '{$this->_post_type}'
		";

		if ($params === false) {
			$posts_per_process = (int)$posts_per_process;
			$total = $wpdb->get_var(sprintf($query, 'COUNT(p.ID) as count'));

			if ($total <= 0) {
				return false;
			}

			if ($posts_per_process > 0 && $total > $posts_per_process) {
				$query .= " LIMIT {$posts_per_process}";
				update_option($params_key, serialize(array(
					'posts_per_process' => $posts_per_process,
					'count' => 1,
					'force_default_all' => $force_default_all
				)));
			}

			$post_ids = $wpdb->get_col(sprintf($query, 'p.ID'));
			$return = false;

		} else if ($params) {
			$params = unserialize($params);

			if (!isset($params['posts_per_process'], $params['count'], $params['force_default_all'])) {
				return false;
			}

			$posts_per_process = (int)$params['posts_per_process'];
			$count = (int)$params['count'];
			$force_default_all = (boolean)$params['force_default_all'];

			$total = $wpdb->get_var(sprintf($query, 'COUNT(p.ID) as count'));
			$offset = $posts_per_process * $count;
			$max = $posts_per_process * ($count + 1);

			$query .= " LIMIT {$offset}, {$max}";

			if ($max > $count) {
				delete_option($params_key);
				$return = false;

			} else {
				update_option($params_key, serialize(array(
					'posts_per_process' => $posts_per_process,
					'count' => $count + 1,
					'force_default_all' => $force_default_all
				)));
			}

			$post_ids = $wpdb->get_col(sprintf($query, 'p.ID'));
		}

		foreach ((array)$post_ids as $post_id) {
			foreach ($this->_components as $component) {
				$component->refresh($post_id, $force_default_all);
			}
		}

		return $return;
	}

	protected function _generate_uniq_id()
	{
		return sha1($this->_id . serialize($this->_components));
	}
}

class ICF_MetaBox_Component extends ICF_Component
{
	public $title;

	protected $_metabox;
	protected $_id;

	/**
	 * Constructor
	 *
	 * @param	string		$id
	 * @param	string		$title
	 */
	public function __construct(ICF_MetaBox $metabox, $id, $title = '')
	{
		parent::__construct();

		$this->_metabox = $metabox;
		$this->_id = $id;

		$this->title = (empty($title) && $title !== false) ? $id : $title;
	}

	/**
	 * Returns the id
	 *
	 * @return	string
	 */
	public function get_id()
	{
		return $this->_id;
	}

	/**
	 * Returns the MetaBox
	 *
	 * @return ICF_MetaBox
	 */
	public function get_metabox()
	{
		return $this->_metabox;
	}

	/**
	 * Saves the elements
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

	/**
	 * Saves the default data of elements when data is not registered
	 *
	 * @param	int		$post_id
	 * @param	boolean	$force
	 */
	public function refresh($post_id, $force = false)
	{
		foreach ($this->_elements as $element) {
			if (is_subclass_of($element, 'ICF_MetaBox_Component_Element_FormField_Abstract')) {
				$element->refresh($post_id, $force);
			}
		}
	}

	public function render($arg1 = null, $arg2 = null)
	{
		$args = func_get_args();

		$html  = $this->title ? ICF_Tag::create('p', null, ICF_Tag::create('strong', null, $this->title)) : '';
		$html .= call_user_func_array(array(parent, 'render'), $args);

		return $html;
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

			} else if (!preg_match('|' . preg_quote($required_mark) . '$|', $this->_component->get_metabox()->title)) {
				$this->_component->get_metabox()->title .= ' ' . $required_mark;
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

class ICF_MetaBox_Component_Element_FormField_Text extends ICF_MetaBox_Component_Element_FormField_Abstract
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

class ICF_MetaBox_Component_Element_FormField_Checkbox extends ICF_MetaBox_Component_Element_FormField_Abstract
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

class ICF_MetaBox_Component_Element_FormField_Radio extends ICF_MetaBox_Component_Element_FormField_Abstract
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

class ICF_MetaBox_Component_Element_FormField_Textarea extends ICF_MetaBox_Component_Element_FormField_Abstract
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

class ICF_MetaBox_Component_Element_FormField_Select extends ICF_MetaBox_Component_Element_FormField_Abstract
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

class ICF_MetaBox_Component_Element_FormField_Wysiwyg extends ICF_MetaBox_Component_Element_FormField_Abstract
{
	public function render()
	{
		trigger_error(__('The TinyMCE cannot be use to inside of a MetaBox', 'icf'), E_USER_NOTICE);
	}
}