<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-tag.php';
require_once dirname(__FILE__) . '/icf-form.php';
require_once dirname(__FILE__) . '/icf-inflector.php';

class ICF_Component extends ICF_Tag
{
	protected $_name = '';
	protected $_name_cache = array();
	protected $_form_types = array('media', 'date', 'quicktag', 'wysiwyg');

	/**
	 * Constructor
	 */
	public function __construct()
	{
		if (preg_match('/^ICF_([A-Z][\w]+?)_Component$/', get_class($this), $matches)) {
			$this->_name = $matches[1];
		}

		$this->add_form_type(get_class_methods('ICF_Form'));
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

	/**
	 * Returns the form types
	 *
	 * @return	array
	 */
	public function get_form_types()
	{
		return $this->_form_types;
	}

	/**
	 * Adds the form type(s)
	 *
	 * @param	string|array	$type
	 */
	public function add_form_type($type)
	{
		if (!is_array($type)) {
			$type = array($type);
		}

		$this->_form_types = array_merge($this->_form_types, array_diff($type, $this->_form_types));
	}

	public function __call($method, $args)
	{
		$element_class = 'ICF_Component_Element_' . $this->_classify($method);
		$local_element_class = 'ICF_' . $this->_name . '_Component_Element_' . $this->_classify($method);
		$local = false;

		if (class_exists($local_element_class)) {
			$element_class = $local_element_class;
			$local = true;

		} else if (!class_exists($element_class)) {
			return parent::__call($method, $args);
		}

		$is_form = in_array(strtolower($method), $this->_form_types);
		$reflection = new ReflectionClass($element_class);

		array_unshift($args, $this);
		$element = $reflection->newInstanceArgs($args);

		if ($local) {
			$interface = $is_form
					   ? 'ICF_' . $this->_name . '_Component_Element_FormField_Interface'
					   : 'ICF_' . $this->_name . '_Component_Element_General_Interface';

			if (interface_exists($interface) && !($element instanceof $interface)) {
				throw new Exception('Class "' . $element_class . '" does not implements interface of the "' . $interface . '"');
			}
		}

		$sub_class = $is_form ? 'ICF_Component_Element_FormField_Abstract' : 'ICF_Component_Element_General_Abstract';

		if (!is_subclass_of($element, $sub_class)) {
			throw new Exception('Class "' . $element_class . '" is not sub class of the "' . $sub_class . '"');
		}

		$this->_element_trigger($element, 'initialize');
		$this->_elements[] = $element;

		return $this;
	}

	public function render()
	{
		if ($this->_stack) {
			$this->all_close();
		}

		$html = '';

		foreach ($this->_elements as $element) {
			if ($this->_element_trigger($element, 'before_render') === false) {
				continue;
			}

			$result = $element->render();

			if (($after = $this->_element_trigger($element, 'after_render', array($result))) && $after !== true) {
				$result = $after;
			}

			$html .= $result;
		}

		$this->clear();

		echo $html;
	}

	/**
	 * Trigger function of element
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

interface ICF_Component_Element_General_Interface
{
	public function __construct(ICF_Component $component);
}

interface ICF_Component_Element_FormField_Interface
{
	public function __construct(ICF_Component $component, $name, $value = null, array $args = array());
}

abstract class ICF_Component_Element_Abstract implements ICF_Tag_Element_Interface
{
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

abstract class ICF_Component_Element_General_Abstract extends ICF_Component_Element_Abstract implements ICF_Component_Element_General_Interface
{
	protected $_component;

	/**
	 * Constructor
	 *
	 * @param	ICF_Component	$component
	 */
	public function __construct(ICF_Component $component)
	{
		$this->_component = $component;
	}
}

abstract class ICF_Component_Element_FormField_Abstract extends ICF_Component_Element_Abstract implements ICF_Component_Element_FormField_Interface
{
	protected $_component;
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
	public function __construct(ICF_Component $component, $name, $value = null, array $args = array())
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

		if (preg_match('/^ICF_' . $component_name . 'Component_Element_([^_]+)$/', get_class($this), $matches)) {
			$this->_type = strtolower($matches[1]);
		}

		$this->_component = $component;
		$this->_name = icf_extract($args, 'name');
		$this->_value = icf_extract($args, 'value');
		$this->_args = $args;
	}

	public function initialize()
	{
		list($this->_container, $this->_validation) = icf_extract($this->_args, 'container', 'validation');
		$this->_validation = self::_parse_validation_rules($this->_validation);

		if ($this->_validation && in_array($this->_type, $this->_single_form_types)) {
			ICF_Tag_Element_Node::add_class($this->_args, implode(' ', $this->_validation));
		}
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

class ICF_Component_Element_Validation extends ICF_Component_Element_General_Abstract
{
	protected $_rules = array();

	public function __construct(ICF_Component $component, $rules = array(), $container = null)
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

class ICF_Component_Element_Nbsp extends ICF_Component_Element_General_Abstract
{
	protected $_repeat = 1;

	public function __construct(ICF_Component $component, $repeat = 1)
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

class ICF_Component_Element_Button_Secondary extends ICF_Component_Element_General_Abstract
{
	protected $_value;
	protected $_args = array();

	public function __construct(ICF_Component $component, $value = null, $args = array())
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

class ICF_Component_Element_Button_Media extends ICF_Component_Element_General_Abstract
{
	protected $_for;

	public function __construct(ICF_Component $component, $for = null, $value = null, $args = array())
	{
		$this->_for = $for;
		$this->_value = $value;
		$this->_args = $args;
	}

	public function before_render()
	{
		$data = array_combine(
			array('type', 'mode', 'value'),
			icf_extract($this->_args, 'type', 'mode', 'value')
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

class ICF_Component_Element_Text extends ICF_Component_Element_FormField_Abstract
{
	public function render()
	{
		return ICF_Form::text($this->_name, $this->_value, $this->_args);
	}
}

class ICF_Component_Element_Checkbox extends ICF_Component_Element_FormField_Abstract
{
	public function render()
	{
		return ICF_Form::checkbox($this->_name, $this->_value, $this->_args);
	}
}

class ICF_Component_Element_Radio extends ICF_Component_Element_FormField_Abstract
{
	public function render()
	{
		return ICF_Form::radio($this->_name, $this->_value, $this->_args);
	}
}

class ICF_Component_Element_Textarea extends ICF_Component_Element_FormField_Abstract
{
	public function render()
	{
		return ICF_Form::textarea($this->_name, $this->_value, $this->_args);
	}
}

class ICF_Component_Element_Select extends ICF_Component_Element_FormField_Abstract
{
	public function render()
	{
		return ICF_Form::select($this->_name, $this->_value, $this->_args);
	}
}

class ICF_Component_Element_Quicktag extends ICF_Component_Element_FormField_Abstract
{
	public function initialize()
	{
		if (version_compare(get_bloginfo('version'), '3.3', '>=')) {
			parent::initialize();

			$buttons = icf_extract($this->_args, 'buttons');

			if ($buttons) {
				$this->_args['data-buttons'] = is_array($buttons) ? implode(' ') : $buttons;
			}

			ICF_Tag_Element_Node::add_class($this->_args, 'icf-quicktag wp-editor-area');
			$this->_args['id'] = 'icf-quicktag-' . md5(uniqid(mt_rand()));

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

class ICF_Component_Element_Wysiwyg extends ICF_Component_Element_FormField_Abstract
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
		if (version_compare(get_bloginfo('version'), '3.3', '>=') && function_exists('wp_editor')) {
			wp_editor($this->_value, $this->_args['id'], $this->_args['settings']);

		} else {
			trigger_error('The TinyMCE has been required for the WordPress 3.3 or above');
		}
	}
}

class ICF_Component_Element_Date extends ICF_Component_Element_FormField_Abstract
{
	protected $_date_type = array('date', 'datetime', 'time');

	public function initialize()
	{
		list($pick, $reset) = icf_extract($this->_args, 'pick', 'reset');

		$settings = array_combine(
			array('data-preset', 'data-step-year', 'data-step-hour', 'data-step-minute', 'data-step-second', 'data-start-year', 'data-end-year'),
			icf_extract($this->_args, 'preset', 'step_year', 'step_hour', 'step_minute', 'step_second', 'start_year', 'end_year')
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
				$pick_label = reset(icf_extract_and_merge($pick, 'value', 'label'));

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
				$reset_label = reset(icf_extract_and_merge($reset, 'value', 'label'));

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

class ICF_Component_Element_Media extends ICF_Component_Element_FormField_Abstract
{
	public function initialize()
	{
		list($media, $reset, $preview, $type) = icf_extract($this->_args, 'media', 'reset', 'preview', 'type');

		if (is_array($media)) {
			$media_label = reset(icf_extract_and_merge($media, 'value', 'label'));

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
				$reset_label = reset(icf_extract_and_merge($reset, 'value', 'label'));

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