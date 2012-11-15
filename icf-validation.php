<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-loader.php';

class ICF_Validation
{
	protected $_current_field;
	protected $_current_rule;
	protected $_validated = array();
	protected $_errors = array();
	protected $_fields = array();
	protected $_rules = array();
	protected $_messages = array();
	protected $_default_messages = array();

	protected static $_instances = array();

	protected function __construct($config = array())
	{
		$config = wp_parse_args($config, array(
			'messages' => array()
		));

		$this->_default_messages = $config['messages'];
	}

	public function add_field($field, $label = null)
	{
		if (!array_key_exists($field, $this->_fields)) {
			if (!$label) {
				$label = $field;
			}

			$this->_fields[$field] = $label;
			$this->_current_field = $field;
		}

		return $this;
	}

	public function add_rule($rule)
	{
		if (!$this->_current_field) {
			trigger_error('There is no field that is currently selected.', E_USER_WARNING);
			return false;
		}

		if (is_string($rule) && is_callable(array('ICF_Validation', $rule))) {
			$rule = array('ICF_Validation', $rule);

		} if (!is_callable($rule)) {
			trigger_error('The rule is not a correct validation rule.', E_USER_WARNING);
			return false;
		}

		$rule_name = $this->create_callback_name($rule);

		$args = array_splice(func_get_args(), 1);
		array_unshift($args, $rule);

		$this->_current_rule = $rule_name;
		$this->_rules[$this->_current_field][$rule_name] = $args;

		return $this;
	}

	public function set_message($message)
	{
		if (!$this->_current_field) {
			trigger_error('There is no field that is currently selected.', E_USER_WARNING);
			return false;
		}

		if (!$this->_current_rule) {
			trigger_error('There is no rule that is currently selected.', E_USER_WARNING);
			return false;
		}

		if (is_null($message) || $message === false) {
			unset($this->_messages[$this->_current_field][$this->_current_rule]);

		} else {
			$this->_messages[$this->_current_field][$this->_current_rule] = $message;
		}

		return $this;
	}

	public function validated($field = null)
	{
		if (func_num_args() > 1) {
			$field = func_get_args();
		}

		if (!$field) {
			return $this->_validated;

		} else if (is_array($field)) {
			$validated_values = array();

			foreach ($field as $_field) {
				if (!$_field || ($validated_value = $this->validated($_field))) {
					continue;
				}

				$validated_values[] = $validated_value;
			}

			return $validated_values;

		} else if (isset($this->_validated[$field])) {
			return $this->_validated[$field];
		}

		return false;
	}

	public function error($field = null)
	{
		if (func_num_args() > 1) {
			$field = func_get_args();
		}

		if (!$field) {
			return $this->_errors;

		} else  if (is_array($field)) {
			$errors = array();

			foreach ($field as $_field) {
				if (!$_field || !($error = $this->error($_field))) {
					continue;
				}

				$errors[] = $error;
			}

			return $errors;

		} else if (isset($this->_errors[$field])) {
			return $this->_errors[$field];
		}

		return false;
	}

	public function run(array &$data, $overwrite = false)
	{
		$this->_errors = $this->_validated = array();

		if (empty($data)) {
			return true;
		}

		foreach ($this->_fields as $field => $label) {
			$value = icf_filter($data, $field);

			if (!empty($this->_rules[$field])) {
				foreach ($this->_rules[$field] as $rule => $params) {
					$function = array_shift($params);
					$args = $params;

					foreach ($args as $i => $arg) {
						if (is_string($arg) && strpos($arg, ':') === 0) {
							$data_field = substr($arg, 1);
							$args[$i] = icf_filter($data, $data_field);
						}
					}

					array_unshift($args, $value);
					$result = call_user_func_array($function, $args);

					if ($result === false) {
						$message = isset($this->_messages[$field][$rule])
							? $this->_messages[$field][$rule]
							: (isset($this->_default_messages[$rule]) ? $this->_default_messages[$rule] : true);

						$find = array(':field', ':label', ':value', ':rule');
						$replace = array($field, $label, $value, $rule);

						foreach($params as $param_key => $param_value) {
							if (is_array($param_value)) {
								$text = '';

								foreach ($param_value as $_param_value) {
									if (is_array($_param_value)) {
										$_param_value = '(array)';

									} elseif (is_object($_param_value)) {
										$_param_value = '(object)';

									} elseif (is_bool($_param_value)) {
										$_param_value = $_param_value ? 'true' : 'false';
									}

									$text .= empty($text) ? $_param_value : (', ' . $_param_value);
								}

								$param_value = $text;

							} elseif (is_bool($param_value)) {
								$param_value = $param_value ? 'true' : 'false';

							} elseif (is_object($param_value)) {
								$param_value = method_exists($param_value, '__toString') ? (string) $param_value : get_class($param_value);
							}

							$find[] = ':param:' . ($param_key + 1);
							$replace[] = $param_value;
						}

						$this->_errors[$field] = str_replace($find, $replace, $message);

						continue 2;

					} else if ($result !== true) {
						$value = $result;
					}
				}
			}

			$this->_validated[$field] = $value;

			if ($overwrite) {
				$data[$field] = $value;
			}
		}

		return count($this->_errors) == 0;
	}

	public function set_default_message($rule, $message = null)
	{
		if (is_array($rule) && empty($message)) {
			foreach ($rule as $_rule => $message) {
				if (is_int($_rule)) {
					continue;
				}

				$this->set_default_message($_rule, $message);
			}

		} else {
			if (!$rule_name = $this->create_callback_name($rule)) {
				return false;
			}

			if (is_null($message) || $message === false) {
				unset($this->_default_messages[$rule_name]);

			} else {
				$this->_default_messages[$rule_name] = $message;
			}
		}
	}

	public function create_callback_name($callback)
	{
		if (is_string($callback) && strpos($callback, '::')) {
			$callback = explode('::', $callback, 2);
		}

		if (is_array($callback) && reset($callback) == 'ICF_Validation') {
			$callback = $callback[1];
		}

		if (is_string($callback) && is_callable(array('ICF_Validation', $callback))) {
			$callback_name = $callback;

		} else if (is_callable($callback)) {
			if (is_array($callback)) {
				$callback_name = (is_object($callback[0]) ? get_class($callback[0]) : $callback[0]) . '::' . $callback[1];

			} else {
				$callback_name = $callback;
			}

		} else {
			$callback_name = '';
		}

		return $callback_name;
	}

	public static function get_instance($name = null, $config = array())
	{
		if (is_array($name) && empty($config)) {
			$config = $name;
			$name = '';
		}

		if (!$name) {
			$name = 'default';
		}

		if (empty(self::$_instances[$name])) {
			self::$_instances[$name] = new ICF_Validation($config);
		}

		return self::$_instances[$name];
	}

	public static function not_empty($value)
	{
		return !($value === false || $value === null || $value === '' || $value === array());
	}

	public static function not_empty_if($value, $expr)
	{
		return !self::not_empty($expr) || (self::not_empty($expr) && self::not_empty($value));
	}

	public static function valid_string($value, $flags = array('alpha', 'utf8'))
	{
		if (!self::not_empty($value)) {
			return true;
		}

		if (!is_array($flags)) {
			if ($flags == 'alpha') {
				$flags = array('alpha', 'utf8');

			} elseif ($flags == 'alpha_numeric') {
				$flags = array('alpha', 'utf8', 'numeric');

			} elseif ($flags == 'url_safe') {
				$flags = array('alpha', 'numeric', 'dashes');

			} elseif ($flags == 'integer' or $flags == 'numeric') {
				$flags = array('numeric');

			} elseif ($flags == 'float') {
				$flags = array('numeric', 'dots');

			} elseif ($flags == 'all') {
				$flags = array('alpha', 'utf8', 'numeric', 'spaces', 'newlines', 'tabs', 'punctuation', 'dashes');

			} else {
				return false;
			}
		}

		$pattern  = !in_array('uppercase', $flags) && in_array('alpha', $flags) ? 'a-z' : '';
		$pattern .= !in_array('lowercase', $flags) && in_array('alpha', $flags) ? 'A-Z' : '';
		$pattern .= in_array('numeric', $flags) ? '0-9' : '';
		$pattern .= in_array('spaces', $flags) ? ' ' : '';
		$pattern .= in_array('newlines', $flags) ? "\n" : '';
		$pattern .= in_array('tabs', $flags) ? "\t" : '';
		$pattern .= in_array('dots', $flags) && !in_array('punctuation', $flags) ? '\.' : '';
		$pattern .= in_array('commas', $flags) && !in_array('punctuation', $flags) ? ',' : '';
		$pattern .= in_array('punctuation', $flags) ? "\.,\!\?:;\&" : '';
		$pattern .= in_array('dashes', $flags) ? '_\-' : '';
		$pattern  = empty($pattern) ? '/^(.*)$/' : ('/^([' . $pattern . '])+$/');
		$pattern .= in_array('utf8', $flags) ? 'u' : '';

		return preg_match($pattern, $value) > 0;
	}

	public static function valid_email($value)
	{
		return !self::not_empty($value) || (bool) preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $value);
	}

	public static function valid_url($value)
	{
		return !self::not_empty($value) || (bool) preg_match("/^(((http|ftp|https):\/\/){1}([a-zA-Z0-9_-]+)(\.[a-zA-Z0-9_-]+)+([\S,:\/\.\?=a-zA-Z0-9_-]+))$/ix", $value);
	}

	public static function min_length($value, $length)
	{
		return !self::not_empty($value) || mb_strlen($value) >= $length;
	}

	public static function max_length($value, $length)
	{
		return !self::not_empty($value) || mb_strlen($value) <= $length;
	}

	public static function exact_length($value, $length)
	{
		return !self::not_empty($value) || mb_strlen($value) == $length;
	}

	public static function numeric_min($value, $min)
	{
		return !self::not_empty($value) || floatval($value) >= floatval($min);
	}

	public static function numeric_max($value, $max)
	{
		return !self::not_empty($value) || floatval($value) <= floatval($max);
	}

	public static function integer($value)
	{
		return !self::not_empty($value) || (bool) preg_match('/^[\-+]?[0-9]+$/', $value);
	}

	public static function decimal($str)
	{
		return !self::not_empty($value) || (bool) preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $value);
	}

	public static function match_value($value, $compare, $strict = false)
	{
		if (!self::not_empty($value) || $value === $compare || (!$strict && $value == $compare)) {
			return true;
		}

		if (is_array($compare)) {
			foreach($compare as $_compare) {
				if ($value === $_compare || (!$strict && $value == $_compare)) {
					return true;
				}
			}
		}

		return false;
	}

	public static function match_pattern($value, $pattern)
	{
		return !self::not_empty($value) || (bool) preg_match($pattern, $value);
	}
}