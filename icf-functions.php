<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-loader.php';

function icf_dump()
{
	$backtrace = debug_backtrace();

	if (strpos($backtrace[0]['file'], 'icf/icf-functions.php') !== false) {
		$callee = $backtrace[1];

	} else {
		$callee = $backtrace[0];
	}

	$arguments = func_get_args();

	echo '<div style="font-size: 13px;background: #EEE !important; border:1px solid #666; color: #000 !important; padding:10px;">';
	echo '<h1 style="border-bottom: 1px solid #CCC; padding: 0 0 5px 0; margin: 0 0 5px 0; font: bold 120% sans-serif;">' . $callee['file'] . ' @ line: ' . $callee['line'] . '</h1>';
	echo '<pre style="overflow:auto;font-size:100%;">';

	$count = count($arguments);

	for ($i = 1; $i <= $count; $i++) {
		echo '<strong>Variable #' . $i . ':</strong>' . PHP_EOL;
		var_dump($arguments[$i - 1]);
		echo PHP_EOL . PHP_EOL;
	}

	echo "</pre>";
	echo "</div>";
}

function icf_log($message = null)
{
	$backtrace = debug_backtrace();

	if (strpos($backtrace[0]['file'], 'icf/icf-functions.php') !== false) {
		$callee = $backtrace[1];

	} else {
		$callee = $backtrace[0];
	}

	if (!is_string($message)) {
		$message = print_r($message, true);
	}

	$log_dir = ABSPATH . DIRECTORY_SEPARATOR . 'icf-logs';

	if (!is_dir($log_dir)) {
		if (!@mkdir($log_dir)) {
			throw Exception('Could not make a log directory.');
		}
	}

	$log_file = $log_dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.txt';

	if (!is_file($log_file)) {
		if (!@touch($log_file)) {
			throw Exception('Could not make a log file.');
		}
	}

	$time = date('Y-m-d H:i:s');

	file_put_contents($log_file, sprintf("[%s] %s - in %s, line %s\n", $time, $message, $callee['file'], $callee['line']), FILE_APPEND);
}

/**
 * Returns a value(s) of the specified key(s) of the array.
 *
 * @param	array			$array
 * @param	string|array	$key
 * @param	mixed			$default
 * @return	mixed
 */
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

/**
 * Returns a value(s) of the specified key(s) of the array and removes it from the array.
 *
 * @param	array	$array
 * @param	string	$key
 * @param	string	$_
 * @return	mixed
 */
function icf_extract(array &$array, $key, $_ = null)
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

/**
 * Returns a merged value of the specified key(s) of array and removes it from array.
 *
 * @param	array	$array
 * @param	string	$key
 * @param	string	$_
 * @return	array
 */
function icf_extract_and_merge(array &$array, $key, $_ = null)
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