<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-loader.php';

function icf_filter(array $array, $key, $default = null)
{
	$keys = is_array($key) ? $key : array($key => $default);
	$values = array();

	foreach ($keys as $_key => $value) {
		if (is_numeric($_key) && $value !== null) {
			$_key = $value;
			$value = null;
		}

		if (isset($array[$_key])) {
			$values[] = $array[$_key];

		} else {
			$values[] = $value;
		}
	}

	return (is_array($key) && count($key) > 1) ? $values : reset($values);
}

function icf_extract(array &$array, $key)
{
	$args = func_get_args();
	$keys = array_splice($args, 1);
	$values = array();

	foreach ($keys as $key) {
		if (isset($array[$key])) {
			$values[] = $array[$key];
			unset($array[$key]);

		} else {
			$values[] = null;
		}
	}

	return (count($keys) > 1) ? $values : reset($values);
}

function icf_extract_and_merge(array &$array, $key)
{
	$args = func_get_args();
	$keys = array_splice($args, 1);
	$values = array();

	foreach ($keys as $key) {
		if ($value = icf_extract($array, $key)) {
			$values = array_merge($values, (array)$value);
		}
	}

	return $values;
}