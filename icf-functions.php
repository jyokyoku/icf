<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-loader.php';

function icf_find(array $array, $key, $default = null)
{
	return isset($array[$key]) ? $array[$key] : $default;
}

function icf_extract(array &$array, $key)
{
	$keys = array_splice(func_get_args(), 1);
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
	$keys = array_splice(func_get_args(), 1);
	$values = array();

	foreach ($keys as $key) {
		if ($value = icf_extract($array, $key)) {
			$values = array_merge($values, (array)$value);
		}
	}

	return $values;
}