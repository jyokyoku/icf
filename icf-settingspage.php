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

abstract class ICF_SettingsPage_Abstract
{
	public $title;
	public $menu_title;
	public $capability;
	public $template;

	protected $_slug;
	protected $_sections = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
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
	 * Create the ICF_SettingsPage_Section object
	 *
	 * @param	string|ICF_SettingsPage_Section	$title
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
	 * Alias
	 *
	 * @see	ICF_SettingsPage_Abstract::section
	 */
	public function s($id = null, $title = null, $callback = null)
	{
		return $this->section($id, $title, $callback);
	}

	/**
	 * Display the html
	 */
	public function display()
	{
		global $wp_settings_fields;

		if ($this->template && is_file($this->template) && is_readable($this->template)) {
			@include $this->template;

		} else if ($this->template && is_callable($this->template)) {
			call_user_func_array($this->template, array($this));

		} else {
			require ABSPATH . 'wp-admin/options-head.php';
?>
<div class="wrap">
<h2><?php echo esc_html($this->title) ?></h2>
<form method="post" action="options.php" id="<?php echo $this->_slug ?>_form">
<?php settings_fields($this->_slug) . PHP_EOL; ?>
<?php if (!empty($wp_settings_fields[$this->_slug]['default'])): ?>
<table class="form-table">
<?php do_settings_fields($this->_slug, 'default') . PHP_EOL; ?>
</table>
<?php endif ?>
<?php do_settings_sections($this->_slug) . PHP_EOL; ?>
<?php submit_button() . PHP_EOL; ?>
</form>
</div>
<?php
		}
	}


	abstract public function register();
}

class ICF_SettingsPage extends ICF_SettingsPage_Abstract
{
	public $icon_url;
	public $position;

	protected $_subs = array();

	/**
	 * Constructor
	 *
	 * @param	string	$slug
	 * @param	string	$title
	 * @param	array	$args
	 */
	public function __construct($slug, $title = null, $args = array())
	{
		$args = wp_parse_args($args, array(
			'menu_title' => null, 'capability' => 'manage_options',
			'icon_url' => null, 'position' => null, 'template' => null
		));

		$this->_slug = $slug;

		$this->title = empty($title) ? $this->_slug : $title;
		$this->menu_title = empty($args['menu_title']) ? $this->title : $args['menu_title'];
		$this->capability = $args['capability'];
		$this->icon_url = $args['icon_url'];
		$this->position = $args['position'];
		$this->template = $args['template'];

		parent::__construct();
	}

	/**
	 * Create the ICF_SettingsPage_Sub object
	 *
	 * @param	string|ICF_Settings_Sub	$slug
	 * @param	string					$title
	 * @param	array					$args
	 * @return	ICF_Settings_Sub
	 */
	public function sub($slug, $title = null, $args = array())
	{
		if (is_object($slug) && is_a($slug, 'ICF_SettingsPage_Sub')) {
			$sub = $slug;
			$slug = $sub->get_slug();

			if (isset($this->_subs[$slug])) {
				if ($this->_subs[$slug] !== $sub) {
					$this->_subs[$slug] = $sub;
				}

				return $sub;
			}

		} else if (!empty($this->_subs[$slug])) {
			return $this->_subs[$slug];

		} else {
			$sub = new ICF_SettingsPage_Sub($this->_slug, $slug, $title, $args);
		}

		$this->_subs[$slug] = $sub;

		return $sub;
	}

	public function register()
	{
		add_menu_page(
			$this->title, $this->menu_title, $this->capability, $this->_slug,
			array($this, 'display'), $this->icon_url, $this->position
		);
	}
}

class ICF_SettingsPage_Sub extends ICF_SettingsPage_Abstract
{
	protected $_parent_slug;

	/**
	 * Constructor
	 *
	 * @param	string|ICF_SettingsPage	$parent_slug
	 * @param	string					$slug
	 * @param	string					$title
	 * @param	string					$menu_title
	 * @param	array					$args
	 */
	public function __construct($parent_slug, $slug, $title = null, $args = array())
	{
		$args = wp_parse_args($args, array(
			'menu_title' => null, 'capability' => 'manage_options', 'template' => null
		));

		if (is_object($parent_slug) && is_a($object, 'ICF_SettingsPage')) {
			$this->_parent_slug = $object->get_slug();

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

		$this->_slug = $slug;

		$this->title = empty($title) ? $this->_slug : $title;
		$this->menu_title = empty($args['menu_title']) ? $this->title : $args['menu_title'];
		$this->capability = $args['capability'];
		$this->template = $args['template'];

		parent::__construct();
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
	 * Register
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
	 * Returns the section id
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
	 * Create the component
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

			if (isset($this->_components[$id])) {
				if ($this->_components[$id] !== $component) {
					$this->_components[$id] = $component;
				}

				return $component;
			}

		} else if (isset($this->_components[$id])) {
			return $this->_components[$id];

		} else {
			if (!$title) {
				$title = $id;
			}

			$component = new ICF_SettingsPage_Section_Component($id, $title, $this->_page_slug, $this->_id);
		}

		$this->_components[] = $component;

		return $component;
	}

	/**
	 * Alias
	 *
	 * @see	ICF_SettingsPage_Section::component
	 */
	public function c($id, $title = '')
	{
		return $this->component($id, $title);
	}

	/**
	 * Register
	 */
	public function register()
	{
		if ($this->_id != 'default') {
			$callback = is_callable($this->description_or_callback) ? $this->description_or_callback : array($this, 'display');
			add_settings_section($this->_id, $this->title, $callback, $this->_page_slug);
		}
	}

	/**
	 * Display the html
	 */
	public function display()
	{
		if (!empty($this->description_or_callback) && is_string($this->description_or_callback)) {
			echo $this->description_or_callback;
		}
	}

	/**
	 * Add the html of link dialog
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
	 * Adds the script that is used by ICF
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
	 * Adds the css that is used by ICF
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
	 * Get the ID
	 *
	 * @return	string
	 */
	public function get_id()
	{
		return $this->_id;
	}

	/**
	 * Get the page slug
	 *
	 * @return	string
	 */
	public function get_page_slug()
	{
		return $this->_page_slug;
	}

	/**
	 * Get the section ID
	 *
	 * @return	string
	 */
	public function get_section_id()
	{
		return $this->_section_id;
	}

	/**
	 * Register
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
}

class ICF_SettingsPage_Section_Component_Element_Text extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function render()
	{
		$value = get_option($this->_name);

		if ($value !== false && $value !== '') {
			$this->_value = $value;
		}

		return ICF_Form::text($this->_name, $this->_value, $this->_args);
	}
}

class ICF_SettingsPage_Section_Component_Element_Checkbox extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function register()
	{
		register_setting($this->_component->get_page_slug(), $this->_name);

		if (get_option($this->_name) === false && $this->_value && !empty($this->_args['checked'])) {
			update_option($this->_name, $this->_value);
		}
	}

	public function render()
	{
		$value = get_option($this->_name);

		if ($value !== false && $value !== '') {
			unset($this->_args['checked'], $this->_args['selected']);
			$this->_args['checked'] = ($value == $this->_value);
		}

		return ICF_Form::checkbox($this->_name, $this->_value, $this->_args);
	}
}

class ICF_SettingsPage_Section_Component_Element_Radio extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
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

	public function render()
	{
		$value = get_option($this->_name);

		if ($value !== false && $value !== '') {
			unset($this->_args['checked'], $this->_args['selected']);
			$this->_args['checked'] = in_array($value, (array)$this->_value) ? $value : false;
		}

		return ICF_Form::radio($this->_name, $this->_value, $this->_args);
	}
}

class ICF_SettingsPage_Section_Component_Element_Textarea extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function render()
	{
		$value = get_option($this->_name);

		if ($value !== false && $value !== '') {
			$this->_value = $value;
		}

		return ICF_Form::textarea($this->_name, $this->_value, $this->_args);
	}
}

class ICF_SettingsPage_Section_Component_Element_Select extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
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

	public function render()
	{
		$value = get_option($this->_name);

		if ($value !== false && $value !== '') {
			unset($this->_args['checked'], $this->_args['selected']);
			$this->_args['selected'] = in_array($value, (array)$this->_value) ? $value : false;
		}

		return ICF_Form::select($this->_name, $this->_value, $this->_args);
	}
}

class ICF_SettingsPage_Section_Component_Element_Wysiwyg extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract
{
	public function initialize()
	{
		parent::initialize();

		if (!isset($this->_args['settings'])) {
			$this->_args['settings'] = array();
		}

		$this->_args['id'] = $this->_name;
	}

	public function render()
	{
		$value = get_option($this->_name);

		if ($value !== false && $value !== '') {
			$this->_value = $value;
		}

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