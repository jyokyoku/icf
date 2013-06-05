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
require_once dirname(__FILE__) . '/icf-tag.php';
require_once dirname(__FILE__) . '/icf-form.php';
require_once dirname(__FILE__) . '/icf-inflector.php';

abstract class ICF_Component_Abstract extends ICF_Tag
{
	protected $_name = '';
	protected $_name_cache = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
		if (preg_match('/^ICF_([A-Z][\w]+?)_Component$/', get_class($this), $matches)) {
			$this->_name = $matches[1];
		}
	}

	/**
	 * Returns the name
	 *
	 * @return	string
	 */
	public function get_name()
	{
		return $this->_name;
	}

	public function __call($method, $args)
	{
		$element_class = 'ICF_Component_Element_' . $this->_classify($method);
		$local_element_class = 'ICF_' . $this->_name . '_Component_Element_' . $this->_classify($method);

		$form_element_class = 'ICF_Component_Element_FormField_' . $this->_classify($method);
		$local_form_element_class = 'ICF_' . $this->_name . '_Component_Element_FormField_' . $this->_classify($method);

		$local = $is_form = false;

		if (class_exists($element_class) || class_exists($local_element_class)) {
			if (class_exists($local_element_class)) {
				$element_class = $local_element_class;
				$local = true;
			}

		} else if (class_exists($form_element_class) || class_exists($local_form_element_class)) {
			if (class_exists($local_form_element_class)) {
				$element_class = $local_form_element_class;
				$local = true;

			} else {
				$element_class = $form_element_class;
			}

			$is_form = true;

		} else {
			return parent::__call($method, $args);
		}

		$reflection = new ReflectionClass($element_class);

		array_unshift($args, $this);
		$element = $reflection->newInstanceArgs($args);

		if ($local) {
			$interface = $is_form
					   ? 'ICF_' . $this->_name . '_Component_Element_FormField_Interface'
					   : 'ICF_' . $this->_name . '_Component_Element_Interface';

			if (interface_exists($interface) && !($element instanceof $interface)) {
				throw new Exception('Class "' . $element_class . '" does not implements interface of the "' . $interface . '"');
			}
		}

		$sub_class = $is_form ? 'ICF_Component_Element_FormField_Abstract' : 'ICF_Component_Element_Abstract';

		if (!is_subclass_of($element, $sub_class)) {
			throw new Exception('Class "' . $element_class . '" is not sub class of the "' . $sub_class . '"');
		}

		$this->_element_trigger($element, 'initialize');
		$this->_elements[] = $element;

		return $this;
	}

	public function render($arg = null, $_ = null)
	{
		$this->all_close();
		$this->capture_all_end();

		$args = func_get_args();
		$html = '';

		foreach ($this->_elements as $element) {
			if ($this->_element_trigger($element, 'before_render', $args) === false) {
				continue;
			}

			$result = $this->_element_trigger($element, 'render', $args);

			if (($after = $this->_element_trigger($element, 'after_render', array($result))) && $after !== true) {
				$result = $after;
			}

			$html .= $result;
		}

		$this->clear();

		return $html;
	}

	public function display($arg1 = null, $arg2 = null)
	{
		$args = func_get_args();
		echo call_user_func_array(array($this, 'render'), $args);
	}

	public function __toString()
	{
		return $this->render();
	}

	/**
	 * Triggers function of element
	 *
	 * @param	ICF_Tag_Element_Interface	$element
	 * @param	callback					$function
	 * @return	mixed
	 */
	protected function _element_trigger(ICF_Tag_Element_Interface $element, $function, array $args = array())
	{
		if (method_exists($element, $function)) {
			return call_user_func_array(array($element, $function), $args);
		}

		return true;
	}

	/**
	 * Changes $str to class name ("test_element" or "testElement" or etc.. to "Test_Element")
	 *
	 * @param	string	$str
	 * @return	string
	 */
	protected function _classify($str)
	{
		if (!isset($this->_name_cache[$str])) {
			$result = implode('_', explode(' ', ICF_Inflector::humanize($str)));

		} else {
			$result = $this->_name_cache[$str];
		}

		return $result;
	}
}

class ICF_Component extends ICF_Component_Abstract
{
	protected static $_instances = array();

	public static function get_instance($name = null)
	{
		if (!$name) {
			$name = 'default';
		}

		if (!isset($_instances[$name])) {
			self::$_instances[$name] = new ICF_Component();
		}

		return self::$_instances[$name];
	}

	public static function instance($name = null)
	{
		return self::get_instance($name);
	}
}

abstract class ICF_Component_Element_Abstract implements ICF_Tag_Element_Interface
{
	protected $_component;

	/**
	 * Constructor
	 *
	 * @param	ICF_Component	$component
	 */
	public function __construct(ICF_Component_Abstract $component)
	{
		$this->_component = $component;
	}

	public function initialize()
	{
	}

	public function before_render()
	{
	}

	public function after_render()
	{
	}

	protected static function _parse_validation_rules($rules)
	{
		if (!is_array($rules)) {
			$rules = array_filter(array_map('trim', explode(' ', trim((string)$rules))));
		}

		foreach ($rules as $i => $rule) {
			if (strpos($rule, 'chk') !== 0) {
				$rules[$i] = 'chk' . $rule;
			}
		}

		return $rules;
	}
}

abstract class ICF_Component_Element_FormField_Abstract extends ICF_Component_Element_Abstract
{
	protected $_name;
	protected $_type;
	protected $_value;
	protected $_args;
	protected $_container;
	protected $_validation;
	protected $_single_form_types = array('text', 'textarea', 'select', 'file', 'password');
	protected $_multiple_form_types = array('radio');

	/**
	 * Constructor
	 *
	 * @param	ICF_Component	$component
	 * @param	string					$name
	 * @param	int|string				$value
	 * @param	array					$args
	 */
	public function __construct(ICF_Component_Abstract $component, $name, $value = null, array $args = array())
	{
		if (is_array($name)) {
			$args = $name;

		} else {
			$args['name'] = $name;
			$args['value'] = $value;
		}

		if (empty($args['name'])) {
			throw new Exception('Class "' . __CLASS__ . '" requires the "name" attribute');
		}

		if ($component_name = $component->get_name()) {
			$component_name .= '_';
		}

		if (preg_match('/^ICF_' . $component_name . 'Component_Element_FormField_([a-zA-Z0-9]+)$/', get_class($this), $matches)) {
			$this->_type = strtolower($matches[1]);
		}

		$this->_name = icf_get_array_hard($args, 'name');
		$this->_value = icf_get_array_hard($args, 'value');
		$this->_args = $args;

		parent::__construct($component);
	}

	public function initialize()
	{
		list($this->_container, $this->_validation) = array_values(icf_get_array_hard($this->_args, array('container', 'validation')));
		$this->_validation = self::_parse_validation_rules($this->_validation);

		if ($this->_validation && in_array($this->_type, $this->_single_form_types)) {
			ICF_Tag_Element_Node::add_class($this->_args, implode(' ', $this->_validation));
		}
	}

	public function render()
	{
		if (!$this->_type || !method_exists('ICF_Form', $this->_type)) {
			return '';
		}

		return call_user_func(array('ICF_Form', $this->_type), $this->_name, $this->_value, $this->_args);
	}

	public function after_render($html = null)
	{
		$container = $this->_container;
		$container_args = array();

		if (is_array($container)) {
			list($container, $container_args) = array_values($container) + array('span', array());
		}

		if ($this->_validation && in_array($this->_type, $this->_multiple_form_types)) {
			if (empty($container)) {
				$container = 'span';
			}

			if (empty($container_args['id'])) {
				$container_args['id'] = $this->_name . '_group';
			}

			ICF_Tag_Element_Node::add_class($container_args, $this->_validation);
		}

		return $container ? ICF_Tag::create($container, $container_args, $html) : $html;
	}
}

class ICF_Component_Element_Validation extends ICF_Component_Element_Abstract
{
	protected $_rules = array();

	public function __construct(ICF_Component_Abstract $component, $rules = array(), $container = null)
	{
		parent::__construct($component);
		$container_args = array();

		if (is_array($container)) {
			list($container, $container_args) = $container + array('span', array());

		} else if (!$container) {
			$container = 'span';
		}

		$rules = self::_parse_validation_rules($rules);
		$rules[] = 'chkgroup';

		if (empty($container_args['id'])) {
			$container_args['id'] = 'v_' . uniqid();
		}

		ICF_Tag_Element_Node::add_class($container_args, $rules);
		$this->_component->open($container, $container_args);
	}

	public function render()
	{
	}
}

class ICF_Component_Element_Nbsp extends ICF_Component_Element_Abstract
{
	protected $_repeat = 1;

	public function __construct(ICF_Component_Abstract $component, $repeat = 1)
	{
		parent::__construct($component);

		if ($repeat < 1) {
			$repeat = 1;
		}

		$this->_repeat = $repeat;
	}

	public function render()
	{
		return str_repeat('&nbsp;', $this->_repeat);
	}
}

class ICF_Component_Element_Description extends ICF_Component_Element_Abstract
{
	public function __construct(ICF_Component_Abstract $component, $value = null, $args = array())
	{
		parent::__construct($component);

		if (is_array($value) && empty($args)) {
			$args = $value;
			$value = null;
		}

		ICF_Tag_Element_Node::add_class($args, 'description');
		$this->_component->p($args);

		if ($value) {
			$this->_component->html((string)$value)->close();
		}
	}

	public function render()
	{
	}
}

class ICF_Component_Element_Button_Secondary extends ICF_Component_Element_Abstract
{
	protected $_value;
	protected $_args = array();

	public function __construct(ICF_Component_Abstract $component, $value = null, $args = array())
	{
		$this->_value = $value;
		$this->_args = $args;
	}

	public function before_render()
	{
		ICF_Tag_Element_Node::add_class($this->_args, 'button');
	}

	public function render()
	{
		return ICF_Tag::create('button', $this->_args, $this->_value);
	}
}

class ICF_Component_Element_Button_Primary extends ICF_Component_Element_Button_Secondary
{
	public function before_render()
	{
		ICF_Tag_Element_Node::add_class($this->_args, 'button-primary');
	}
}

class ICF_Component_Element_Button_Media extends ICF_Component_Element_Abstract
{
	protected $_for;

	public function __construct(ICF_Component_Abstract $component, $for = null, $value = null, $args = array())
	{
		$this->_for = $for;
		$this->_value = $value;
		$this->_args = $args;
	}

	public function before_render()
	{
		$data = array_combine(
			array('type', 'mode', 'value'),
			icf_get_array_hard($this->_args, array('type', 'mode', 'value'))
		);

		foreach ($data as $key => $value) {
			if (!empty($value)) {
				$this->_args['data-' . $key] = $value;
			}
		}

		$this->_args['data-for'] = $this->_for;
		$this->_args['type'] = 'button';
		ICF_Tag_Element_Node::add_class($this->_args, 'button media_button');
	}

	public function render()
	{
		return ICF_Tag::create('button', $this->_args, $this->_value);
	}
}

class ICF_Component_Element_Button_Reset extends ICF_Component_Element_Button_Media
{
	public function before_render()
	{
		if (empty($this->_value)) {
			$this->_value = __('Clear', 'icf');
		}

		$this->_args['data-for'] = $this->_for;
		$this->_args['type'] = 'button';
		ICF_Tag_Element_Node::add_class($this->_args, 'button reset_button');
	}
}

class ICF_Component_Element_FormField_Text extends ICF_Component_Element_FormField_Abstract
{
}

class ICF_Component_Element_FormField_Password extends ICF_Component_Element_FormField_Abstract
{
}

class ICF_Component_Element_FormField_Hidden extends ICF_Component_Element_FormField_Abstract
{
}

class ICF_Component_Element_FormField_File extends ICF_Component_Element_FormField_Abstract
{
	public function __construct(ICF_Component_Abstract $component, $name, array $args = array()) {
		parent::__construct($component, $name, null, $args);
	}

	public function render()
	{
		if (!$this->_type || !method_exists('ICF_Form', $this->_type)) {
			return '';
		}

		return call_user_func(array('ICF_Form', $this->_type), $this->_name, $this->_args);
	}
}

class ICF_Component_Element_FormField_Checkbox extends ICF_Component_Element_FormField_Abstract
{
}

class ICF_Component_Element_FormField_Radio extends ICF_Component_Element_FormField_Abstract
{
}

class ICF_Component_Element_FormField_Textarea extends ICF_Component_Element_FormField_Abstract
{
}

class ICF_Component_Element_FormField_Select extends ICF_Component_Element_FormField_Abstract
{
}

class ICF_Component_Element_FormField_Quicktag extends ICF_Component_Element_FormField_Abstract
{
	public function initialize()
	{
		if (version_compare(get_bloginfo('version'), '3.3', '>=')) {
			parent::initialize();

			$buttons = icf_get_array_hard($this->_args, 'buttons');

			if ($buttons) {
				$this->_args['data-buttons'] = is_array($buttons) ? implode(' ', $buttons) : $buttons;
			}

			ICF_Tag_Element_Node::add_class($this->_args, 'icf-quicktag wp-editor-area');
			$this->_args['id'] = 'icf-quicktag-' . sha1($this->_name);

			$this->_component
				->div(array('class' => 'wp-editor-container'))
					->div(array('class' => 'wp-editor-wrap', 'id' => 'wp-' . $this->_args['id'] . '-wrap'))
						->textarea($this->_name, $this->_value, $this->_args)
					->close
				->close;
		}
	}

	public function render()
	{
		if (version_compare(get_bloginfo('version'), '3.3', '<')) {
			trigger_error('The Quicktag has been required for the WordPress 3.3 or above');
		}
	}
}

class ICF_Component_Element_FormField_Wysiwyg extends ICF_Component_Element_FormField_Abstract
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

class ICF_Component_Element_FormField_Date extends ICF_Component_Element_FormField_Abstract
{
	protected $_date_type = array('date', 'datetime', 'time');

	public function initialize()
	{
		list($pick, $reset) = array_values(icf_get_array_hard($this->_args, array('pick', 'reset')));

		$settings = array_combine(
			array('data-preset', 'data-step-year', 'data-step-hour', 'data-step-minute', 'data-step-second', 'data-start-year', 'data-end-year'),
			icf_get_array_hard($this->_args, array('preset', 'step_year', 'step_hour', 'step_minute', 'step_second', 'start_year', 'end_year'))
		);

		if (!in_array($settings['data-preset'], $this->_date_type)) {
			$settings['data-preset'] = 'date';
		}

		if (empty($settings['data-start-year'])) {
			$settings['data-start-year'] = date('Y') - 10;
		}

		if (empty($settings['data-end-year'])) {
			$settings['data-end-year'] = date('Y') + 10;
		}

		$settings = array_filter($settings);

		ICF_Tag_Element_Node::add_class($this->_args, 'date_field');
		$this->_args = array_merge($this->_args, $settings);

		if (!empty($this->_value)) {
			$this->_value = strtotime($this->_value);
		}

		if ($pick !== false){
			if (is_array($pick)) {
				$pick_label = reset(icf_extract_and_merge($pick, array('value', 'label')));

			} else {
				$pick_label = $pick;
				$pick = array();
			}

			if (!$pick_label) {
				$pick_label = __('Pick', 'icf');
			}

			$pick['type'] = 'button';
			$pick['data-for'] = $this->_name;
			ICF_Tag_Element_Node::add_class($pick, 'date_picker');
		}

		if ($reset !== false) {
			if (is_array($reset)) {
				$reset_label = reset(icf_extract_and_merge($reset, array('value', 'label')));

			} else {
				$reset_label = $reset;
				$reset = array();
			}

			if (!$reset_label) {
				$reset_label = __('Clear', 'icf');
			}

			if (!isset($this->_args['readonly'])) {
				$this->_args['readonly'] = true;
			}
		}

		$this->_component->text($this->_name, $this->_value, $this->_args);

		if ($pick !== false) {
			$this->_component
				->nbsp(1)
				->button_secondary($pick_label, $pick);
		}

		if ($reset !== false) {
			$this->_component
				->nbsp(1)
				->button_reset($this->_name, $reset_label, $reset);
		}
	}

	public function render()
	{
	}
}

class ICF_Component_Element_FormField_Media extends ICF_Component_Element_FormField_Abstract
{
	public function initialize()
	{
		list($media, $reset, $preview, $type) = array_values(icf_get_array_hard($this->_args, array('media', 'reset', 'preview', 'type')));

		if (is_array($media)) {
			$media_label = reset(icf_extract_and_merge($media, array('value', 'label')));

		} else {
			$media_label = $media;
			$media = array();
		}

		if (!$media_label) {
			$media_label = __('Select File', 'icf');
		}

		$media['type'] = $type;

		if ($reset !== false) {
			if (is_array($reset)) {
				$reset_label = reset(icf_extract_and_merge($reset, array('value', 'label')));

			} else {
				$reset_label = $reset;
				$reset = array();
			}

			if (!$reset_label) {
				$reset_label = __('Clear', 'icf');
			}
		}

		$this->_component
			->text($this->_name, $this->_value, $this->_args)
			->nbsp(1)
			->button_media($this->_name, $media_label, $media);

		if ($reset !== false) {
			$this->_component
				->nbsp(1)
				->button_reset($this->_name, $reset_label, $reset);
		}
	}

	public function render()
	{
	}
}