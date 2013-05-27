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

class ICF_Form
{
	public static function input($name, $value = null, array $attributes = array())
	{
		if (is_array($name)) {
			$attributes = $name;

		} else {
			$attributes['name'] = $name;
			$attributes['value'] = $value;
		}

		if (!isset($attributes['id']) && isset($attributes['name'])) {
			$attributes['id'] = self::_generate_id($attributes['name']);
		}

		if (empty($attributes['type'])) {
			$attributes['type'] = 'text';
		}

		$label = icf_extract($attributes, 'label');
		$html = ICF_Tag::create('input', $attributes);

		if ($label) {
			$label_attributes = !empty($attributes['id']) ? array('for' => $attributes['id']) : array();
			$html = ICF_Tag::create('label', $label_attributes, sprintf(self::_filter_label($label, $attributes['type']), $html));
		}

		return $html;
	}

	public static function text($name, $value = null, array $attributes = array())
	{
		if (is_array($name)) {
			$attributes = $name;

		} else {
			$attributes['name'] = $name;
			$attributes['value'] = $value;
		}

		$attributes['type'] = __FUNCTION__;

		return self::input($attributes);
	}

	public static function password($name, $value = null, array $attributes = array())
	{
		if (is_array($name)) {
			$attributes = $name;

		} else {
			$attributes['name'] = $name;
			$attributes['value'] = $value;
		}

		$attributes['type'] = __FUNCTION__;

		return self::input($attributes);
	}

	public static function hidden($name, $value = null, array $attributes = array())
	{
		if (is_array($name)) {
			$attributes = $name;

		} else {
			$attributes['name'] = $name;
			$attributes['value'] = $value;
		}

		$attributes['type'] = __FUNCTION__;

		return self::input($attributes);
	}

	public static function file($name, array $attributes = array())
	{
		if (is_array($name)) {
			$attributes = $name;

		} else {
			$attributes['name'] = $name;
		}

		if (isset($attributes['value'])) {
			unset($attributes['value']);
		}

		$attributes['type'] = __FUNCTION__;

		return self::input($attributes);
	}

	public static function textarea($name, $value = null, array $attributes = array())
	{
		if (is_array($name)) {
			$attributes = $name;

		} else {
			$attributes['name'] = $name;
			$attributes['value'] = $value;
		}

		if (!isset($attributes['id']) && isset($attributes['name'])) {
			$attributes['id'] = self::_generate_id($attributes['name']);
		}

		$value = !($value = icf_extract($attributes, 'value')) ? '' : esc_textarea($value);
		$label = icf_extract($attributes, 'label');
		$html = ICF_Tag::create('textarea', $attributes, $value);

		if ($label) {
			$label_attributes = !empty($attributes['id']) ? array('for' => $attributes['id']) : array();
			$html = ICF_Tag::create('label', $label_attributes, sprintf(self::_filter_label($label, __FUNCTION__), $html));
		}

		return $html;
	}

	public static function select($name, $options = array(), array $attributes = array())
	{
		if (is_array($name)) {
			$attributes = $name;

		} else {
			$attributes['name'] = $name;
			$attributes['options'] = $options;
		}

		$selected = icf_extract_and_merge($attributes, array('selected', 'checked'));
		$options = icf_extract_and_merge($attributes, array('options', 'value', 'values'));

		if (icf_extract($attributes, 'empty')) {
			array_unshift($options, '');
		}

		$selected = (array)$selected;

		if (!isset($attributes['id']) && isset($attributes['name'])) {
			$attributes['id'] = self::_generate_id($attributes['name']);
		}

		$label = icf_extract($attributes, 'label');
		$html = ICF_Tag::create('select', $attributes, self::_generate_options($options, $selected));

		if ($label) {
			$label_attributes = !empty($attributes['id']) ? array('for' => $attributes['id']) : array();
			$html = ICF_Tag::create('label', $label_attributes, sprintf(self::_filter_label($label, __FUNCTION__), $html));
		}

		return $html;
	}

	public static function checkbox($name, $value = null, array $attributes = array())
	{
		if (is_array($name)) {
			$attributes = $name;

		} else {
			$attributes['name'] = $name;
			$attributes['value'] = $value;
		}

		$attributes['type'] = __FUNCTION__;
		$html = '';

		if (isset($attributes['name'])) {
			$html  = ICF_Tag::create('input', array('type' => 'hidden', 'value' => '', 'name' => $attributes['name'], 'id' => self::_generate_id($attributes['name'] . '_hidden')));
		}

		$html .= self::input($attributes);

		return $html;
	}

	public static function radio($name, $values = null, array $attributes = array())
	{
		if (is_array($name)) {
			$attributes = $name;

		} else {
			$attributes['name'] = $name;
			$attributes['values'] = $values;
		}

		list($name, $before, $after, $separator) = icf_extract($attributes, array('name', 'before', 'after', 'separator'));

		if ($separator === null) {
			$separator = '&nbsp;&nbsp;';
		}

		$checked = reset(icf_extract_and_merge($attributes, array('checked', 'selected')));
		$values = icf_extract_and_merge($attributes, array('value', 'values', 'options'));

		if (!is_array($values)) {
			$values = array($values => $values);
		}

		$radios = array();
		$i = 0;

		foreach (array_unique($values) as $label => $value) {
			$_attributes = $attributes;

			if (is_int($label)) {
				$label = $value;
			}

			$_attributes['label'] = $label;
			$_attributes['checked'] = ($value == $checked);
			$_attributes['type'] = 'radio';

			if ($name) {
				$_attributes['id'] = self::_generate_id($name . '_' . $i);
			}

			$radios[] = $before . ICF_Form::input($name, $value, $_attributes) . $after;
			$i++;
		}

		return implode($separator, $radios);

	}

	protected static function _generate_id($name)
	{
		return '_' . preg_replace(array('/\]\[|\[/', '/(\[\]|\])/'), array('_', ''), $name);
	}

	protected static function _filter_label($label, $type = 'text')
	{
		if (!preg_match_all('/(?:^|[^%])%(?:[0-9]+\$)?s/u', $label, $matches)) {
			$label = in_array($type, array('checkbox', 'radio'), true) ? '%s&nbsp;' . $label : $label . '&nbsp;%s';
		}

		return $label;
	}

	protected static function _generate_options(array $options, array $selected = array())
	{
		$html = '';

		foreach ($options as $label => $value) {
			if (is_array($value)) {
				$html .= ICF_Tag::create(
					'optgroup',
					array('label' => $label),
					self::_generate_options($value, $selected)
				);

			} else {
				if (is_int($label)) {
					$label = $value;
				}

				$option_attributes = array('value' => $value);

				if (in_array($value, $selected)) {
					$option_attributes['selected'] = true;
				}

				$html .= ICF_Tag::create('option', $option_attributes, $label);
			}
		}

		return $html;
	}
}