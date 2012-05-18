<?php
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