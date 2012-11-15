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
require_once dirname(__FILE__) . '/icf-metabox.php';

abstract class ICF_SettingsPage_Abstract
{
	public $title;
	public $menu_title;
	public $capability;
	public $template;
	public $before_template;
	public $after_template;
	public $embed_form;

	protected $_slug;
	protected $_sections = array();
	protected $_metaboxes = array();

	/**
	 * Constructor
	 */
	public function __construct($slug, $title = null, $args = array())
	{
		$args = wp_parse_args($args, array(
			'menu_title' => null, 'capability' => 'manage_options',
			'template' => null, 'include_header' => true,
			'before_template' => null, 'after_template' => null, 'embed_form' => true,
		));

		$this->_slug = $slug;

		$this->title = empty($title) ? $this->_slug : $title;
		$this->menu_title = empty($args['menu_title']) ? $this->title : $args['menu_title'];
		$this->capability = $args['capability'];
		$this->template = $args['template'];
		$this->before_template = $args['before_template'];
		$this->after_template = $args['after_template'];
		$this->embed_form = $args['embed_form'];

		add_action('admin_menu', array($this, 'register'));
	}

	/**
	 * Returns the settings page slug
	 *
	 * @return	string
	 */
	public function get_slug()
	{
		return $this->_slug;
	}

	/**
	 * Creates the ICF_SettingsPage_Section
	 *
	 * @param	string|ICF_SettingsPage_Section	$id
	 * @param	string							$title
	 * @param	callback						$callback
	 * @return	ICF_SettingsPage_Section
	 */
	public function section($id = null, $title = null, $callback = null)
	{
		if (is_object($id) && is_a($id, 'ICF_SettingsPage_Section')) {
			$section = $id;
			$id = $section->get_id();

			if (isset($this->_sections[$id])) {
				if ($this->_sections[$id] !== $section) {
					$this->_sections[$id] = $section;
				}

				return $section;
			}

		} if (isset($this->_sections[$id])) {
			return $this->_sections[$id];

		} else {
			$section = new ICF_SettingsPage_Section($this->_slug, $id, $title, $callback);
		}

		$this->_sections[$id] = $section;

		return $section;
	}

	/**
	 * Alias of 'section' method
	 *
	 * @param	string|ICF_SettingsPage_Section	$id
	 * @param	string							$title
	 * @param	callback						$callback
	 * @return	ICF_SettingsPage_Section
	 * @see		ICF_SettingsPage_Abstract::section
	 */
	public function s($id = null, $title = null, $callback = null)
	{
		return $this->section($id, $title, $callback);
	}

	/**
	 * Craetes the ICF_MetaBox
	 *
	 * @param	string|ICF_MetaBox	$id
	 * @param	string				$title
	 * @param	array				$args
	 * @return	ICF_MetaBox
	 */
	public function metabox($id, $title = '', $args = array())
	{
		if (is_object($id) && is_a($id, 'ICF_MetaBox')) {
			$metabox = $id;
			$id = $metabox->get_id();

			if (isset($this->_metaboxes[$id]) && $this->_metaboxes[$id] !== $metabox) {
				$this->_metaboxes[$id] = $metabox;
			}

		} if (is_string($id) && isset($this->_metaboxes[$id])) {
			$metabox = $this->_metaboxes[$id];

		} else {
			$metabox = new ICF_MetaBox($this->_slug, $id, $title, $args);
			$this->_metaboxes[$id] = $metabox;
		}

		return $metabox;
	}

	/**
	 * Alias of 'metabox' method
	 *
	 * @param	string|ICF_MetaBox	$id
	 * @param	string				$title
	 * @param	array				$args
	 * @return	ICF_MetaBox
	 * @see		ICF_SettingsPage_Abstract::metabox
	 */
	public function m($id, $title = '', $args = array())
	{
		return $this->metabox($id, $title, $args);
	}

	/**
	 * Displays the rendered html
	 */
	public function display()
	{
		global $wp_settings_fields;

		if ($this->before_template) {
			echo $this->before_template;

		} else if ($this->before_template === null) {
?>
<div class="wrap">
<h2><?php echo esc_html($this->title) ?></h2>
<?php
		}

		if ($this->embed_form) {
?>
<form method="post" action="options.php" id="<?php echo $this->_slug ?>_form">
<?php
			require ABSPATH . 'wp-admin/options-head.php';
			$this->display_hidden_fields();
		}

		if ($this->template && is_file($this->template) && is_readable($this->template)) {
			@include $this->template;

		} else if ($this->template && is_callable($this->template)) {
			call_user_func_array($this->template, array($this));

		} else {
			if (!empty($wp_settings_fields[$this->_slug]['default'])) {
?>
<table class="form-table">
<?php $this->display_settings_fields('default') ?>
</table>
<?php
			}

			$this->display_settings_sections();
?>
<div id="poststuff">
<?php
			$this->display_metaboxes('normal');
			$this->display_metaboxes('advanced');
?>
</div>
<?php
		}

		if ($this->embed_form) {
			submit_button();
?>
</form>
<?php
		}

		if ($this->after_template) {
			echo $this->after_template;

		} else if ($this->after_template === null) {
?>
</div>
<?php
		}
	}

	/**
	 * Wrapper of 'settings_fields' function
	 *
	 * @see	settings_fields
	 */
	public function display_hidden_fields()
	{
		settings_fields($this->_slug);
	}

	/**
	 * Wrapper of 'do_settings_sections' function
	 *
	 * @see	do_settings_sections
	 */
	public function display_settings_sections()
	{
		do_settings_sections($this->_slug);
	}

	/**
	 * Wrapper of 'do_settings_fields' function
	 *
	 * @param	string	$section
	 * @see		do_settings_fields
	 */
	public function display_settings_fields($section = 'default')
	{
		do_settings_fields($this->_slug, $section);
	}

	/**
	 * Wrapper of 'do_meta_boxes' function
	 *
	 * @param	string	$context
	 * @see		do_meta_boxes
	 */
	public function display_metaboxes($context = 'normal')
	{
		do_meta_boxes($this->_slug, $context, $this);
	}

	abstract public function register();
}

class ICF_SettingsPage_Parent extends ICF_SettingsPage_Abstract
{
	public $icon_url;
	public $position;

	protected $_children = array();

	/**
	 * Constructor
	 *
	 * @param	string	$slug
	 * @param	string	$title
	 * @param	array	$args
	 */
	public function __construct($slug, $title = null, $args = array())
	{
		parent::__construct($slug, $title, $args);
		$args = wp_parse_args($args, array('icon_url' => null, 'position' => null));

		$this->icon_url = $args['icon_url'];
		$this->position = $args['position'];

	}

	/**
	 * Creates the ICF_SettingsPage_Child
	 *
	 * @param	string|ICF_SettingsPage_Child	$slug
	 * @param	string							$title
	 * @param	array							$args
	 * @return	ICF_SettingsPage_Child
	 */
	public function child($slug, $title = null, $args = array())
	{
		if (is_object($slug) && is_a($slug, 'ICF_SettingsPage_Child')) {
			$child = $slug;
			$slug = $child->get_slug();

			if (isset($this->_children[$slug])) {
				if ($this->_children[$slug] !== $child) {
					$this->_children[$slug] = $child;
				}

				return $child;
			}

		} else if (!empty($this->_children[$slug])) {
			return $this->_children[$slug];

		} else {
			$child = new ICF_SettingsPage_Child($this, $slug, $title, $args);
		}

		$this->_children[$slug] = $child;

		return $child;
	}

	/**
	 * Alias of 'child' method
	 *
	 * @param	string|ICF_SettingsPage_Child	$slug
	 * @param	string							$title
	 * @param	array							$args
	 * @return	ICF_SettingsPage_Child
	 * @see		ICF_SettingsPage_Parent::child
	 */
	public function c($slug, $title = null, $args = array())
	{
		return $this->child($slug, $title, $args);
	}

	public function register()
	{
		add_menu_page(
			$this->title, $this->menu_title, $this->capability, $this->_slug,
			array($this, 'display'), $this->icon_url, $this->position
		);
	}
}

class ICF_SettingsPage_Child extends ICF_SettingsPage_Abstract
{
	protected $_parent_slug;

	/**
	 * Constructor
	 *
	 * @param	string|ICF_SettingsPage_Parent	$parent_slug
	 * @param	string							$slug
	 * @param	string							$title
	 * @param	string							$menu_title
	 * @param	array							$args
	 */
	public function __construct($parent_slug, $slug, $title = null, $args = array())
	{
		parent::__construct($slug, $title, $args);

		if (is_object($parent_slug) && is_a($parent_slug, 'ICF_SettingsPage_Parent')) {
			$this->_parent_slug = $parent_slug->get_slug();

		} else {
			$parent_alias = array(
				'management'	=> 'tools.php',
				'options'		=> 'options-general.php',
				'theme'			=> 'themes.php',
				'plugin'		=> 'plugins.php',
				'users'			=> current_user_can('edit_users') ? 'users.php' : 'profile.php',
				'dashboard'		=> 'index.php',
				'posts'			=> 'edit.php',
				'media'			=> 'upload.php',
				'links'			=> 'link-manager.php',
				'pages'			=> 'edit.php?post_type=page',
				'comments'		=> 'edit-comments.php'
			);

			$this->_parent_slug = isset($parent_alias[$parent_slug]) ? $parent_alias[$parent_slug] : $parent_slug;
		}

	}

	/**
	 * Returns the parent page slug
	 *
	 * @return	string
	 */
	public function get_parent_slug()
	{
		return $this->_parent_slug;
	}

	/**
	 * Registers to system
	 */
	public function register()
	{
		add_submenu_page(
			$this->_parent_slug, $this->title, $this->menu_title,
			$this->capability, $this->_slug, array($this, 'display')
		);
	}
}

class ICF_SettingsPage_Section
{
	public $title;
	public $description_or_callback;

	protected $_id;
	protected $_page_slug;
	protected $_components = array();

	/**
	 * Constructor
	 *
	 * @param	string			$page_slug
	 * @param	string			$id
	 * @param	stirng			$title
	 * @param	string|callback	$description_or_callback
	 */
	public function __construct($page_slug, $id = null, $title = null, $description_or_callback = null)
	{
		$this->_page_slug = $page_slug;
		$this->_id = empty($id) ? 'default' : $id;

		$this->title = empty($title) ? $this->_id : $title;
		$this->description_or_callback = $description_or_callback;

		add_action('admin_init', array($this, 'load_wpeditor_html'));
		add_action('admin_menu', array($this, 'register'));
		add_action('admin_print_scripts', array($this, 'add_scripts'));
		add_action('admin_print_styles', array($this, 'add_styles'));
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
	 * Returns the page slug
	 *
	 * @return	string
	 */
	public function get_page_slug()
	{
		return $this->_page_slug;
	}

	/**
	 * Creates the component
	 *
	 * @param	string|ICF_SettingsPage_Section_Component	$id
	 * @param	string										$title
	 * @return	ICF_SettingsPage_Section_Component
	 */
	public function component($id, $title = '')
	{
		if (is_object($id) && is_a($id, 'ICF_SettingsPage_Section_Component')) {
			$component = $id;
			$id = $component->get_id();

			if (isset($this->_components[$id]) && $this->_components[$id] !== $component) {
				$this->_components[$id] = $component;
			}

		} else if (is_string($id) && isset($this->_components[$id])) {
			$component = $this->_components[$id];

		} else {
			$component = new ICF_SettingsPage_Section_Component($id, $title, $this->_page_slug, $this->_id);
			$this->_components[] = $component;
		}

		return $component;
	}

	/**
	 * Alias of 'component' method
	 *
	 * @param	string|ICF_SettingsPage_Section_Component	$id
	 * @param	string										$title
	 * @return	ICF_SettingsPage_Section_Component
	 * @see		ICF_SettingsPage_Section::component
	 */
	public function c($id, $title = '')
	{
		return $this->component($id, $title);
	}

	/**
	 * Registers to system
	 */
	public function register()
	{
		if ($this->_id != 'default') {
			$callback = is_callable($this->description_or_callback) ? $this->description_or_callback : array($this, 'display');
			add_settings_section($this->_id, $this->title, $callback, $this->_page_slug);
		}
	}

	/**
	 * Displays the html
	 */
	public function display()
	{
		if (!empty($this->description_or_callback) && is_string($this->description_or_callback)) {
			echo $this->description_or_callback;
		}
	}

	/**
	 * Adds the html of link dialog
	 */
	public function load_wpeditor_html()
	{
		global $pagenow;

		if (
			(isset($_GET['page']) && $_GET['page'] == $this->_page_slug)
			|| (!isset($_GET['page']) && strpos($pagenow, 'options-') === 0)
		) {
			add_action('admin_print_footer_scripts', array('ICF_Loader', 'load_wpeditor_html'));
		}
	}

	/**
	 * Adds the scripts used by ICF
	 */
	public function add_scripts()
	{
		global $pagenow;

		if (
			(isset($_GET['page']) && $_GET['page'] == $this->_page_slug)
			|| (!isset($_GET['page']) && strpos($pagenow, 'options-') === 0)
		) {
			ICF_Loader::register_javascript(array(
				'icf-settingspage' => array(ICF_Loader::get_latest_version_url() . '/js/settingspage.js', array('icf-common'), null, true)
			));
		}
	}

	/**
	 * Adds the stylesheets used by ICF
	 */
	public function add_styles()
	{
		global $pagenow;

		if (
			(isset($_GET['page']) && $_GET['page'] == $this->_page_slug)
			|| (!isset($_GET['page']) && strpos($pagenow, 'options-') === 0)
		) {
			ICF_Loader::register_css();
		}
	}
}

class ICF_SettingsPage_Section_Component extends ICF_Component
{
	public $title;

	protected $_id;
	protected $_page_slug;
	protected $_section_id;
	protected $_registered = false;

	/**
	 * Constructor
	 *
	 * @param	string	$id
	 * @param	string	$title
	 * @param	string	$page_slug
	 * @param	string	$section_id
	 */
	public function __construct($id, $title = null, $page_slug = null, $section_id = null)
	{
		parent::__construct();

		$this->_id = $id;
		$this->_page_slug = $page_slug;
		$this->_section_id = empty($section_id) ? 'default' : $section_id;

		$this->title = empty($title) ? $this->_id : $title;

		add_action('admin_menu', array($this, 'register'));
	}

	/**
	 * Returns the ID
	 *
	 * @return	string
	 */
	public function get_id()
	{
		return $this->_id;
	}

	/**
	 * Returns the page slug
	 *
	 * @return	string
	 */
	public function get_page_slug()
	{
		return $this->_page_slug;
	}

	/**
	 * Returns the section id
	 *
	 * @return	string
	 */
	public function get_section_id()
	{
		return $this->_section_id;
	}

	/**
	 * Registers to system
	 */
	public function register()
	{
		if ($this->_page_slug && $this->_section_id) {
			add_settings_field($this->_id, $this->title, array($this, 'display'), $this->_page_slug, $this->_section_id);
		}

		foreach ($this->_elements as $element) {
			if (is_subclass_of($element, 'ICF_SettingsPage_Section_Component_Element_FormField_Abstract')) {
				$element->register();
			}
		}

		$this->_registered = true;
	}

	public function render()
	{
		if (!$this->_registered) {
			$this->register();
		}

		return parent::render();
	}
}

abstract class ICF_SettingsPage_Section_Component_Element_FormField_Abstract extends ICF_Component_Element_FormField_Abstract
{
	protected $_stored_value = false;

	public function __construct(ICF_SettingsPage_Section_Component $component, $name, $value = null, array $args = array())
	{
		parent::__construct($component, $name, $value, $args);
	}

	public function register()
	{
		register_setting($this->_component->get_page_slug(), $this->_name);

		if (get_option($this->_name) === false && $this->_value) {
			update_option($this->_name, $this->_value);
		}
	}

	public function initialize()
	{
		parent::initialize();

		if (in_array('chkrequired', $this->_validation)) {
			$required_mark = '<span style="color: #B00C0C;">*</span>';

			if (!preg_match('|' . preg_quote($required_mark) . '$|', $this->_component->title)) {
				$this->_component->title .= ' ' . $required_mark;
			}
		}
	}

	public function before_render()
	{
		$value = get_option($this->_name, false);

		if ($value !== false) {
			$this->_stored_value = $value;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Text extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function before_render()
	{
		parent::before_render();

		if ($this->_stored_value !== false) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Textarea extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function before_render()
	{
		parent::before_render();

		if ($this->_stored_value !== false) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Checkbox extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function register()
	{
		register_setting($this->_component->get_page_slug(), $this->_name);

		if (get_option($this->_name) === false && $this->_value && !empty($this->_args['checked'])) {
			update_option($this->_name, $this->_value);
		}
	}

	public function before_render()
	{
		parent::before_render();

		if ($this->_stored_value !== false) {
			unset($this->_args['checked'], $this->_args['selected']);
			$this->_args['checked'] = ($this->_stored_value == $this->_value);
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Radio extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function register()
	{
		register_setting($this->_component->get_page_slug(), $this->_name);

		if (
			get_option($this->_name) === false
			&& $this->_value
			&& !empty($this->_args['checked'])
			&& in_array($this->_args['checked'], array_values((array)$this->_value))
		) {
			update_option($this->_name, $this->_args['checked']);
		}
	}

	public function before_render()
	{
		parent::before_render();

		if ($this->_stored_value !== false) {
			unset($this->_args['checked'], $this->_args['selected']);
			$this->_args['checked'] = in_array($this->_stored_value, (array)$this->_value) ? $this->_stored_value : false;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Select extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function register()
	{
		register_setting($this->_component->get_page_slug(), $this->_name);

		if (
			get_option($this->_name) === false
			&& $this->_value
			&& !empty($this->_args['selected'])
			&& in_array($this->_args['selected'], array_values((array)$this->_value))
		) {
			update_option($this->_name, $this->_args['selected']);
		}
	}

	public function before_render()
	{
		parent::before_render();

		if ($this->_stored_value !== false) {
			unset($this->_args['checked'], $this->_args['selected']);
			$this->_args['selected'] = in_array($this->_stored_value, (array)$this->_value) ? $this->_stored_value : false;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Wysiwyg extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function initialize()
	{
		parent::initialize();

		if (!isset($this->_args['settings'])) {
			$this->_args['settings'] = array();
		}

		$this->_args['id'] = $this->_name;
	}

	public function before_render()
	{
		parent::before_render();

		if ($this->_stored_value !== false) {
			$this->_value = $this->_stored_value;
		}
	}

	public function render()
	{
		$editor = '';

		if (version_compare(get_bloginfo('version'), '3.3', '>=') && function_exists('wp_editor')) {
			ob_start();
			wp_editor($this->_value, $this->_args['id'], $this->_args['settings']);
			$editor = ob_get_clean();

		} else {
			trigger_error('The TinyMCE has been required for the WordPress 3.3 or above');
		}

		return $editor;
	}
}