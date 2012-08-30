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

abstract class ICF_Profile_Abstract
{
	protected $_components = array();

	public function __construct()
	{
		add_action('profile_update', array($this, 'save'), 10, 2);
		add_action('admin_init', array('ICF_Profile_Abstract', 'load_wpeditor_html'), 10);

		if (!has_action('admin_head', array('ICF_Profile_Abstract', 'add_local_style'))) {
			add_action('admin_head', array('ICF_Profile_Abstract', 'add_local_style'), 10);
		}

		add_action('admin_print_scripts', array('ICF_Profile_Abstract', 'add_scripts'), 10);
		add_action('admin_print_styles', array('ICF_Profile_Abstract', 'add_styles'), 10);
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

	public static function add_scripts()
	{
		global $pagenow;

		if ($pagenow == 'profile.php' || $pagenow == 'user-edit.php') {
			ICF_Loader::register_javascript();
		}
	}

	public static function add_styles()
	{
		global $pagenow;

		if ($pagenow == 'profile.php' || $pagenow == 'user-edit.php') {
			ICF_Loader::register_css();
		}
	}

	public static function load_wpeditor_html()
	{
		global $pagenow;

		if (
			($pagenow == 'profile.php' || $pagenow == 'user-edit.php')
			&& !has_action('admin_print_footer_scripts', array('ICF_Loader', 'load_wpeditor_html'))
		) {
			add_action('admin_print_footer_scripts', array('ICF_Loader', 'load_wpeditor_html'));
		}
	}

	public function component($id, $title = null)
	{
		if (is_object($id) && is_a($id, 'ICF_Profile_Component')) {
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
			$component = new ICF_Profile_Component($this, $id, $title);
		}

		$this->_components[$id] = $component;

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

	public function display(WP_User $user)
	{
		foreach ($this->_components as $component) {
			$component->display($user);
		}
	}
}

class ICF_Profile_PersonalOptions extends ICF_Profile_Abstract
{
	public function __construct()
	{
		parent::__construct();

		add_action('personal_options', array($this, 'display'), 10, 1);
	}
}

class ICF_Profile_UserProfile extends ICF_Profile_Abstract
{
	public function __construct($title = null)
	{
		parent::__construct();

		$this->title = $title;

		add_action('show_user_profile', array($this, 'display'), 10, 1);
		add_action('edit_user_profile', array($this, 'display'), 10, 1);
	}

	public function display(WP_User $user)
	{
		if ($this->title) {
			echo ICF_Tag::create('h3', null, $this->title);
		}

		echo '<table class="form-table">';
		parent::display($user);
		echo '</table>';
	}
}

class ICF_Profile_Component extends ICF_Component
{
	public $title;

	protected $_profile;
	protected $_id;

	public function __construct(ICF_Profile_Abstract $profile, $id, $title = '')
	{
		parent::__construct();

		$this->_profile = $profile;
		$this->_id = $id;

		$this->title = (empty($title) && $title !== false) ? $id : $title;
	}

	public function get_id()
	{
		return $this->_id;
	}

	public function get_profile()
	{
		return $this->_profile;
	}

	public function save($user_id, $old_user_meta)
	{
		foreach ($this->_elements as $element) {
			if (is_subclass_of($element, 'ICF_Profile_Component_Element_FormField_Abstract')) {
				$element->save($user_id, $old_user_meta);
			}
		}
	}

	public function render($arg1 = null, $arg2 = null)
	{
		$args = func_get_args();

		$html  = ICF_Tag::create('th', null, $this->title);
		$html .= ICF_Tag::create('td', null, call_user_func_array(array(parent, 'render'), $args));
		$html  = ICF_Tag::create('tr', null, $html);

		return $html;
	}
}

abstract class ICF_Profile_Component_Element_FormField_Abstract extends ICF_Component_Element_FormField_Abstract
{
	protected $_stored_value = false;

	public function __construct(ICF_Profile_Component $component, $name, $value = null, array $args = array())
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

class ICF_Profile_Component_Element_FormField_Text extends ICF_Profile_Component_Element_FormField_Abstract
{
	public function before_render(WP_User $user = null)
	{
		parent::before_render($user);

		if ($this->_stored_value !== false) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_Profile_Component_Element_FormField_Textarea extends ICF_Profile_Component_Element_FormField_Abstract
{
	public function before_render(WP_User $user = null)
	{
		parent::before_render($user);

		if ($this->_stored_value !== false) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_Profile_Component_Element_FormField_Checkbox extends ICF_Profile_Component_Element_FormField_Abstract
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

class ICF_Profile_Component_Element_FormField_Radio extends ICF_Profile_Component_Element_FormField_Abstract
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

class ICF_Profile_Component_Element_FormField_Select extends ICF_Profile_Component_Element_FormField_Abstract
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

class ICF_Profile_Component_Element_FormField_Wysiwyg extends ICF_Profile_Component_Element_FormField_Abstract
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