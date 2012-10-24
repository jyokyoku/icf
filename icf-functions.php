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
		$default = null;

		if (is_array($key)) {
			if (count($key) > 1) {
				list($key, $default) = array_values($key);

			} else {
				$key = reset($key);
			}
		}

		if (!$key) {
			continue;
		}

		if (isset($array[$key])) {
			$values[] = $array[$key];
			unset($array[$key]);

		} else {
			$values[] = $default;
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

function icf_timthumb($file, $width = null, $height = null, $attr = array())
{
	if (is_array($width) && empty($height) && empty($attr))
	{
		$attr = $width;
		$width = null;
	}

	$defaults = array(
		'q' => null,
		'a' => null,
		'zc' => null,
		'f' => array(),
		's' => null,
		'w' => null,
		'h' => null
	);

	$attr = array_intersect_key(wp_parse_args($attr, $defaults), $defaults);
	$timthumb = ICF_Loader::get_latest_version_url() . '/vendors/timthumb.php';
	$attr['src'] = $file;

	if ($width) {
		$attr['w'] = $width;
	}

	if ($height) {
		$attr['h'] = $height;
	}

	foreach ($attr as $property => $value) {
		switch ($property) {
			case 'zc':
			case 'q':
			case 's':
			case 'w':
			case 'h':
				if (!is_numeric($value)) {
					unset($$attr[$property]);
					continue;
				}

				$attr[$property] = (int) $value;
				break;

			case 'f':
				if (!is_array($value)) {
					unset($$attr[$property]);
					$value = array($value);
				}

				$filters = array();

				foreach ($value as $filter_name => $filter_args)
				{
					$filter_args = is_array($filter_args) ? implode(',', array_map('trim', $filter_args)) : trim(filter_args);
					$filters[] = implode(',', array(trim($filter_name), $filter_args));
				}

				$attr[$property] = implode('|', $filters);
				break;

			default:
				$attr[$property] = (string) $value;
				break;
		}
	}

	return $timthumb . '?' . http_build_query(array_filter($attr));
}

function icf_html_tag($tag, $attributes = array(), $content = null)
{
	return ICF_Tag::create($tag, $attributes, $content);
}

function icf_get_term_meta($term, $taxonomy, $key, $default = false)
{
	return ICF_Taxonomy::get_option($term, $taxonomy, $key, $default);
}

function icf_get_post_meta($post, $key, $attr = array())
{
	$post_id = null;

	if (is_object($post) && isset($post->ID)) {
		$post_id = $post->ID;

	} else if (is_numeric($post)) {
		$post_id = (int)$post;
	}

	if (is_bool($attr) || preg_match('|^[0|1]$|', $attr)) {
		$attr = array('single' => (bool)$attr);
	}

	$attr = wp_parse_args($attr, array(
		'single'  => false,
		'before'  => '',
		'after'   => '',
		'default' => ''
	));

	if (!$post_id || !($meta_value = get_post_meta($post_id, $key, $attr['single']))) {
		return $attr['single'] ? $attr['default'] ? $attr['before'] . $attr['default'] . $attr['after'] : $attr['default'] : array();
	}

	if ($attr['single']) {
		return $attr['before'] . $meta_value . $attr['after'];
	}

	return $meta_value;
}

function icf_get_option($key, $attr = array())
{
	if (!is_array($attr)) {
		$attr = array('default' => $attr);
	}

	$attr = wp_parse_args($attr, array(
		'default' => false,
		'before'  => '',
		'after'   => ''
	));

	if (($option = get_option($key, false)) === false) {
		return $attr['default'] ? $attr['before'] . $attr['default'] . $attr['after'] : $attr['default'];
	}

	return $attr['before'] . $option . $attr['after'];
}

function icf_get_iteration_option($key, $min, $max, $attr = array())
{
	$options = array();

	if (!is_numeric($min) || !is_numeric($max) || $min >= $max) {
		return $options;
	}

	for ($i = $min; $i <= $max; $i++) {
		if ($option = icf_get_option($key . $i, $attr)) {
			$options[] = $option;
		}
	}

	return $options;
}

function icf_get_iteration_post_meta($post, $key, $min, $max, $attr = array())
{
	$post_metas = array();

	if (!is_numeric($min) || !is_numeric($max) || $min >= $max) {
		return $post_metas;
	}

	for ($i = $min; $i <= $max; $i++) {
		if ($post_meta = icf_get_post_meta($post_id, $key . $i, $attr)) {
			$post_metas[] = $post_meta;
		}
	}

	return $post_metas;
}