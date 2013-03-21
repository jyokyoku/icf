<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi <jyokyoku@gmail.com>
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 * @link		http://inspire-tech.jp
 */

require_once dirname(__FILE__) . '/icf-loader.php';
require_once dirname(__FILE__) . '/icf-component.php';

abstract class ICF_Profile_Abstract
{
	protected $_sections = array();
	protected $_profile_page = true;
	protected $_role = array();
	protected $_capability = array();
	protected $_current_user;

	public function __construct($args = array())
	{
		$args = wp_parse_args($args, array(
			'profile_page' => true,
			'role' => array(),
			'capablity' => array()
		));

		$this->_current_user = get_user_by('id', get_current_user_id());
		$this->_profile_page = $args['profile_page'];
		$this->_role = $args['role'];
		$this->_capability = $args['capablity'];

		if ($this->_role && !is_array($this->_role)) {
			$this->_role = array($this->_role);
		}

		if ($this->_capability && !is_array($this->_capability)) {
			$this->_capability = array($this->capability);
		}

		if (!has_action('admin_head', array('ICF_Profile_Abstract', 'add_local_style'))) {
			add_action('admin_head', array('ICF_Profile_Abstract', 'add_local_style'), 10);
		}
	}

	public static function add_local_style()
	{
		global $pagenow;

		if ($pagenow == 'profile.php' || $pagenow == 'user-edit.php') {
?>
<style type="text/css">
#profile-page .form-table .wp-editor-container textarea {
	width: 99.9%;
	margin-bottom: 0;
}
</style>
<?php
		}
	}

	public function section($id = null, $title = null)
	{
		if (empty($id)) {
			$id = 'default';
		}

		if (is_object($id) && is_a($id, 'ICF_Profile_Section')) {
			$section = $id;
			$id = $section->get_id();

			if (isset($this->_sections[$id]) && $this->_sections[$id] !== $section) {
				$this->_sections[$id] = $section;
			}

		} else if (is_string($id) && isset($this->_sections[$id])) {
			$section = $this->_sections[$id];

		} else {
			$section = new ICF_Profile_Section($this, $id, $title);
			$this->_sections[$id] = $section;
		}

		return $section;
	}

	public function s($id = null, $title = null)
	{
		return $this->section($id, $title);
	}

	public function save($user_id, $old_user_meta)
	{
		if (!$this->_is_arrowed()) {
			return false;
		}

		foreach ($this->_sections as $section) {
			$section->save($user_id, $old_user_meta);
		}
	}

	public function render(WP_User $user)
	{
		if (!$this->_is_arrowed()) {
			return false;
		}

		$html = '';

		foreach ($this->_sections as $section) {
			$html .= $section->render($user);
		}

		return $html;
	}

	public function display(WP_User $user)
	{
		echo $this->render($user);
	}

	protected function _is_arrowed()
	{
		if (defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE && !$this->_profile_page) {
			return false;
		}

		if ($this->_role) {
			foreach ($this->_role as $role) {
				if (!in_array($role, $this->_current_user->roles)) {
					return false;
				}
			}
		}

		if ($this->_capability) {
			if (!current_user_can($this->_capability)) {
				return false;
			}
		}

		return true;
	}
}

class ICF_Profile_PersonalOptions extends ICF_Profile_Abstract
{
	protected $_section;

	public function __construct($args = array())
	{
		parent::__construct($args);

		$this->_section = $this->section();

		add_action('profile_update', array($this, 'save'), 10, 2);
		add_action('personal_options', array($this, 'display'), 10, 1);
	}

	public function component($id, $title = null)
	{
		return $this->_section->c($id, $title);
	}

	public function c($id, $title = null)
	{
		return $this->component($id, $title);
	}

	public function display(WP_User $user)
	{
		if (!$this->_is_arrowed()) {
			return false;
		}

		$html = '';

		foreach ($this->_sections as $section) {
			foreach ($section->get_components() as $component) {
				$html .= $component->render($user);
			}
		}

		echo $html;
	}
}

class ICF_Profile_UserProfile extends ICF_Profile_Abstract
{
	protected $_section;

	public function __construct($title = null, $args = array())
	{
		parent::__construct($args);

		$this->title = $title;
		$this->_section = $this->section();

		add_action('profile_update', array($this, 'save'), 10, 2);
		add_action('show_user_profile', array($this, 'display'), 10, 1);
		add_action('edit_user_profile', array($this, 'display'), 10, 1);
	}

	public function display(WP_User $user)
	{
		if (!$this->_is_arrowed()) {
			return false;
		}

		if ($this->title) {
			echo '<h3>' . $this->title . '</h3>';
		}

		foreach ($this->_sections as $section) {
			echo $section->display($user);
		}
	}

	public function component($id, $title = null)
	{
		return $this->_section->c($id, $title);
	}

	public function c($id, $title = null)
	{
		return $this->component($id, $title);
	}
}

class ICF_Profile_Page extends ICF_Profile_Abstract
{
	public $title;
	public $menu_title;
	public $capability;
	public $icon_url;
	public $position;
	public $embed_form = true;

	protected $_slug;

	public function __construct($slug, $title = '', $args = array())
	{
		parent::__construct($args);

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

		add_action('admin_init', array($this, 'save'));
		add_action('admin_menu', array($this, 'register'));
	}

	public function display()
	{
		if (!$this->_is_arrowed()) {
			wp_die(__('<strong>ERROR</strong>: profile page not found.'));
		}

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
<form method="post" action="<?php echo admin_url('admin.php') ?>" id="<?php echo $this->_slug ?>_form">
<?php
			require ABSPATH . 'wp-admin/options-head.php';
			echo '<input type="hidden" name="action" value="update" />';
			wp_nonce_field($this->_slug . '-profile-page');
		}

		if ($this->template && is_file($this->template) && is_readable($this->template)) {
			@include $this->template;

		} else if ($this->template && is_callable($this->template)) {
			call_user_func_array($this->template, array($this));

		} else {
			parent::display($this->_current_user);
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

	public function register()
	{
		if ($this->_is_arrowed()) {
			add_menu_page(
				$this->title, $this->menu_title, $this->capability, $this->_slug,
				array($this, 'display'), $this->icon_url, $this->position
			);
		}
	}

	public function save()
	{
		$action = icf_filter($_REQUEST, 'action');

		if (!$this->_is_arrowed() || empty($action)) {
			return false;
		}

		if ($action == 'update') {
			$user_id = get_current_user_id();
			$old_user_data = WP_User::get_data_by('id', $user_id);

			parent::save($user_id, $old_user_data);

			$goback = add_query_arg('updated', 'true',  wp_get_referer());
			wp_redirect($goback);
		}
	}
}

class ICF_Profile_Section
{
	public $title;

	protected $_id;
	protected $_profile;
	protected $_components = array();

	public function __construct(ICF_Profile_Abstract $profile, $id = null, $title = null)
	{
		$this->_profile = $profile;
		$this->_id = empty($id) ? 'default' : $id;

		$this->title = empty($title) ? $this->_id : $title;
	}

	public function get_id()
	{
		return $this->_id;
	}

	public function get_profile()
	{
		return $this->_profile;
	}

	public function get_components()
	{
		return $this->_components;
	}

	public function component($id, $title = null)
	{
		if (is_object($id) && is_a($id, 'ICF_Profile_Section_Component')) {
			$component = $id;
			$id = $component->get_id();

			if (isset($this->_components[$id]) && $this->_components[$id] !== $component) {
				$this->_components[$id] = $component;
			}

		} else if (is_string($id) && isset($this->_components[$id])) {
			$component = $this->_components[$id];

		} else {
			$component = new ICF_Profile_Section_Component($this, $id, $title);
			$this->_components[$id] = $component;
		}

		return $component;
	}

	public function c($id, $title = null)
	{
		return $this->component($id, $title);
	}

	public function save($user_id, $old_user_meta)
	{
		foreach ($this->_components as $component) {
			$component->save($user_id, $old_user_meta);
		}
	}

	public function render(WP_User $user)
	{
		$html = '';

		if ($this->title !== 'default') {
			$html .= '<h3>' . $this->title . '</h3>';
		}

		$html .= '<table class="form-table">';

		foreach ($this->_components as $component) {
			$html .= $component->render($user);
		}

		$html .= '</table>';

		return $html;
	}

	public function display(WP_User $user)
	{
		echo $this->render($user);
	}
}

class ICF_Profile_Section_Component extends ICF_Component_Abstract
{
	public $title;

	protected $_section;
	protected $_id;

	public function __construct(ICF_Profile_Section $section, $id, $title = '')
	{
		parent::__construct();

		$this->_section = $section;
		$this->_id = $id;

		$this->title = (empty($title) && $title !== false) ? $id : $title;
	}

	public function get_id()
	{
		return $this->_id;
	}

	public function get_section()
	{
		return $this->_section;
	}

	public function save($user_id, $old_user_meta)
	{
		foreach ($this->_elements as $element) {
			if (is_subclass_of($element, 'ICF_Profile_Section_Component_Element_FormField_Abstract')) {
				$element->save($user_id, $old_user_meta);
			}
		}
	}

	public function render($arg1 = null, $arg2 = null)
	{
		$args = func_get_args();

		$html  = ICF_Tag::create('th', null, $this->title);
		$html .= ICF_Tag::create('td', null, call_user_func_array(array($this, 'parent::render'), $args));
		$html  = ICF_Tag::create('tr', null, $html);

		return $html;
	}
}

abstract class ICF_Profile_Section_Component_Element_FormField_Abstract extends ICF_Component_Element_FormField_Abstract
{
	protected $_stored_value = false;

	public function __construct(ICF_Profile_Section_Component $component, $name, $value = null, array $args = array())
	{
		parent::__construct($component, $name, $value, $args);
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

	public function before_render(WP_User $user = null)
	{
		if (isset($user->ID) && $this->exists($user->ID)) {
			$this->_stored_value = get_the_author_meta($this->_name, $user->ID);
		}
	}

	public function save($user_id, $old_user_meta)
	{
		if (!isset($_POST[$this->_name])) {
			return false;
		}

		update_user_meta($user_id, $this->_name, $_POST[$this->_name]);

		return true;
	}

	public function exists($user_id = false) {
		if (!$user_id) {
			global $authordata;
			$user_id = isset($authordata->ID) ? $authordata->ID : 0;

		} else {
			$authordata = get_userdata($user_id);
		}

		return isset($authordata->{$this->_name});
	}
}

class ICF_Profile_Section_Component_Element_FormField_Text extends ICF_Profile_Section_Component_Element_FormField_Abstract
{
	public function before_render(WP_User $user = null)
	{
		parent::before_render($user);

		if ($this->_stored_value !== false) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_Profile_Section_Component_Element_FormField_Textarea extends ICF_Profile_Section_Component_Element_FormField_Abstract
{
	public function before_render(WP_User $user = null)
	{
		parent::before_render($user);

		if ($this->_stored_value !== false) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_Profile_Section_Component_Element_FormField_Checkbox extends ICF_Profile_Section_Component_Element_FormField_Abstract
{
	public function before_render(WP_User $user = null)
	{
		parent::before_render($user);

		if ($this->_stored_value !== false) {
			$this->_args['checked'] = ($this->_stored_value == $this->_value);
			unset($this->_args['selected']);
		}
	}
}

class ICF_Profile_Section_Component_Element_FormField_Radio extends ICF_Profile_Section_Component_Element_FormField_Abstract
{
	public function before_render(WP_User $user = null)
	{
		parent::before_render($user);

		if ($this->_stored_value !== false) {
			$this->_args['checked'] = in_array($this->_stored_value, (array)$this->_value) ? $this->_stored_value : false;
			unset($this->_args['selected']);
		}
	}
}

class ICF_Profile_Section_Component_Element_FormField_Select extends ICF_Profile_Section_Component_Element_FormField_Abstract
{
	public function before_render(WP_User $user = null)
	{
		parent::before_render($user);

		if ($this->_stored_value !== false) {
			$this->_args['selected'] = in_array($this->_stored_value, (array)$this->_value) ? $this->_stored_value : false;
			unset($this->_args['checked']);
		}
	}
}

class ICF_Profile_Section_Component_Element_FormField_Wysiwyg extends ICF_Profile_Section_Component_Element_FormField_Abstract
{
	public function initialize()
	{
		parent::initialize();

		if (!isset($this->_args['settings'])) {
			$this->_args['settings'] = array();
		}

		$this->_args['id'] = $this->_name;
	}

	public function before_render(WP_User $user = null)
	{
		parent::before_render($user);

		if ($this->_stored_value !== false) {
			$this->_value = $this->_stored_value;
		}
	}

	public function render(WP_User $user = null)
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