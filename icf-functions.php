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

	$log_dir = WP_CONTENT_DIR . ICF_DS . 'icf-logs';

	if (!is_dir($log_dir)) {
		if (!@mkdir($log_dir)) {
			throw Exception('Could not make a log directory.');
		}
	}

	$log_file = $log_dir . ICF_DS . date('Y-m-d') . '.txt';

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
 * @deprecated
 */
function icf_filter(array $array, $key, $default = null)
{
	return icf_get_array($array, $key, $default);
}

/**
 * Returns a value(s) of the specified key(s) of the array and removes it from the array.
 *
 * @param	array			$array
 * @param	string|array	$key
 * @param	mixed			$default
 * @return	mixed
 * @deprecated
 */
function icf_extract(array &$array, $key, $default = null)
{
	return icf_get_array_hard($array, $key, $default);
}

/**
 * Returns a merged value of the specified key(s) of array and removes it from array.
 *
 * @param	array			$array
 * @param	string|array	$key
 * @param	mixed			$default
 * @return	array
 */
function icf_extract_and_merge(array &$array, $key, $default = null)
{
	$tmp_keys = $key;

	if (!is_array($tmp_keys)) {
		$tmp_keys = array($key => $default);
	}

	$values = array();

	foreach ($tmp_keys as $tmp_key => $default) {
		if ($value = icf_get_array_hard($array, $tmp_key, $default)) {
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

	$script_filename = str_replace(DIRECTORY_SEPARATOR, '/', icf_get_array($_SERVER, 'SCRIPT_FILENAME'));
	$php_self = icf_get_array($_SERVER, 'PHP_SELF');

	$defaults = array(
		'q' => null,
		'a' => null,
		'zc' => null,
		'f' => array(),
		's' => null,
		'w' => null,
		'h' => null,
		'cc' => null,
		'path' => ($script_filename && $php_self && strpos($script_filename, $php_self) === false),
	);

	$attr = array_intersect_key(wp_parse_args($attr, $defaults), $defaults);
	$timthumb = ICF_Loader::get_latest_version_url() . '/vendors/timthumb.php';

	$attr['src'] = icf_get_array_hard($attr, 'path') ? icf_url_to_path($file) : $file;

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

function icf_get_current_url($query = array(), $overwrite = false, $glue = '&')
{
	$url = (is_ssl() ? 'https://' : 'http://') . getenv('HTTP_HOST') . getenv('REQUEST_URI');
	$query_string = getenv('QUERY_STRING');

	if (strpos($url, '?') !== false) {
		list($url, $query_string) = explode('?', $url);
	}

	if ($query_string) {
		$query_string = wp_parse_args($query_string);

	} else {
		$query_string = array();
	}

	if ($query === false || $query === null) {
		$query = array();

	} else {
		$query = wp_parse_args($query);
	}

	if (!$overwrite) {
		$query = array_merge($query_string, $query);
	}

	foreach ($query as $key => $val) {
		if ($val === false || $val === null || $val === '') {
			unset($query[$key]);
		}
	}

	$url = icf_create_url($url, $query, $glue);

	return $url;
}

function icf_create_url($url, $query = array(), $glue = '&')
{
	$query = http_build_query(wp_parse_args($query));

	if ($query) {
		$url .= (strrpos($url, '?') !== false) ? $glue . $query : '?' . $query;
	}

	return $url;
}

function icf_get_post_thumbnail_data($post_id = null)
{
	global $post;

	if (!$post_id && $post) {
		$post_id = $post->ID;
	}

	if (!has_post_thumbnail($post_id)) {
		return false;
	}

	$post_thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), '');
	$data = array('src' => $post_thumbnail_src[0]);

	if (
		($attachment_id = get_post_thumbnail_id($post_id))
		&& ($attachment = get_post($attachment_id))
	) {
		$alt = trim(strip_tags(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)));

		if (empty($alt)) {
			$alt = trim(strip_tags($attachment->post_excerpt));
		}

		if (empty($alt)) {
			$alt = trim(strip_tags($attachment->post_title));
		}

		$data['alt'] = $alt;
	}

	return $data;
}

function icf_get_document_root()
{
	$script_filename = icf_get_array($_SERVER, 'SCRIPT_FILENAME');
	$php_self = icf_get_array($_SERVER, 'PHP_SELF');
	$document_root = icf_get_array($_SERVER, 'DOCUMENT_ROOT');

	if ($php_self && $script_filename && (!$document_root || strpos($script_filename, $document_root) === false)) {
		$script_filename = str_replace(DIRECTORY_SEPARATOR, '/', $script_filename);

		if (strpos($script_filename, $php_self) !== false) {
			$document_root = substr($script_filename, 0, 0 - strlen($php_self));

		} else {
			$paths = array_reverse(explode('/', $script_filename));
			$php_self_paths = array_reverse(explode('/', $php_self));

			foreach ($php_self_paths as $i => $php_self_path) {
				if (!isset($paths[$i]) || $paths[$i] != $php_self_path) {
					break;
				}

				unset($paths[$i]);
			}

			$document_root = implode('/', array_reverse($paths));
		}
	}

	if ($document_root && icf_get_array($_SERVER, 'DOCUMENT_ROOT') != '/'){
		$document_root = preg_replace('|/$|', '', $document_root);
	}

	return $document_root;
}

function icf_url_to_path($url)
{
	$script_filename = str_replace(DIRECTORY_SEPARATOR, '/', icf_get_array($_SERVER, 'SCRIPT_FILENAME'));
	$php_self = icf_get_array($_SERVER, 'PHP_SELF');
	$remove_path = null;

	if ($script_filename && $php_self && strpos($script_filename, $php_self) === false) {
		$paths = array_reverse(explode('/', $script_filename));
		$php_self_paths = array_reverse(explode('/', $php_self));

		foreach ($paths as $i => $path) {
			if (!isset($php_self_paths[$i]) || $php_self_paths[$i] != $path) {
				break;
			}

			unset($php_self_paths[$i]);
		}

		if ($php_self_paths) {
			$remove_path = implode('/', $php_self_paths);
		}
	}

	$host = preg_replace('|^www\.|i', '', icf_get_array($_SERVER, 'HTTP_HOST'));
	$url = ltrim(preg_replace('|https?://(?:www\.)?' . $host . '|i', '', $url), '/');

	if ($remove_path) {
		$url = str_replace($remove_path, '', $url);
	}

	$document_root = icf_get_document_root();

	if (!$document_root) {
		$file = preg_replace('|^.*?([^/\\\\]+)$|', '$1', $url);

		if (is_file($file)) {
			return realpath($file);
		}
	}

	if (file_exists($document_root . '/' . $url)) {
		$real = realpath($document_root . '/' . $url);

		if (stripos($real, $document_root) === 0){
			return $real;
		}
	}

	$absolute = realpath('/' . $url);

	if ($absolute && file_exists($absolute)) {
		if (stripos($absolute, $document_root) === 0){
			return $absolute;
		}
	}

	$base = $document_root;
	$sub_directories = explode('/', str_replace($document_root, '', $script_filename));

	foreach ($sub_directories as $sub){
		$base .= $sub . '/';

		if (file_exists($base . $url)){
			$real = realpath($base . $url);

			if (stripos($real, realpath($document_root)) === 0) {
				return $real;
			}
		}
	}

	return false;
}

function icf_calc_image_size($width, $height, $new_width = 0, $new_height = 0)
{
	$sizes = array('width' => $new_width, 'height' => $new_height);

	if ($new_width > 0) {
		$ratio = (100 * $new_width) / $width;
		$sizes['height'] = floor(($height * $ratio) / 100);

		if ($new_height > 0 && $sizes['height'] > $new_height) {
			$ratio = (100 * $new_height) / $sizes['height'];
			$sizes['width'] = floor(($sizes['width'] * $ratio) / 100);
			$sizes['height'] = $new_height;
		}
	}

	if ($new_height > 0) {
		$ratio = (100 * $new_height) / $height;
		$sizes['width'] = floor(($width * $ratio) / 100);

		if ($new_width > 0 && $sizes['width'] > $new_width) {
			$ratio = (100 * $new_width) / $sizes['width'];
			$sizes['height'] = floor(($sizes['height'] * $ratio) / 100);
			$sizes['width'] = $new_width;
		}
	}

	return $sizes;
}

function icf_get_array($array, $key, $default = null)
{
	if (is_null($key)) {
		return $array;
	}

	if (is_array($key)) {
		$return = array();

		foreach ($key as $_key => $_default) {
			if (is_int($_key)) {
				$_key = $_default;
				$_default = $default;
			}

			$return[$_key] = icf_get_array($array, $_key, $_default);
		}

		return $return;
	}

	foreach (explode('.', $key) as $key_part) {
		if (isset($array[$key_part]) === false) {
			if (!is_array($array) || !array_key_exists($key_part, $array)) {
				return $default;
			}
		}

		$array = $array[$key_part];
	}

	return $array;
}

function icf_get_array_hard(&$array, $key, $default = null)
{
	if (is_null($key)) {
		return $array;
	}

	if (is_array($key)) {
		$return = array();

		foreach ($key as $_key => $_default) {
			if (is_int($_key)) {
				$_key = $_default;
				$_default = $default;
			}

			$return[$_key] = icf_get_array_hard($array, $_key, $_default);
		}

		return $return;
	}

	$key_parts = explode('.', $key);
	$tmp_array = $array;

	foreach ($key_parts as $i => $key_part) {
		if (isset($tmp_array[$key_part]) === false) {
			if (!is_array($tmp_array) || !array_key_exists($key_part, $tmp_array)) {
				return $default;
			}
		}

		$tmp_array = $tmp_array[$key_part];

		if (count($key_parts) <= $i + 1) {
			unset($array[$key_part]);
		}
	}

	return $tmp_array;
}

function icf_set_array(&$array, $key, $value)
{
	if (is_null($key)) {
		return;
	}

	if (is_array($key)) {
		foreach ($key as $k => $v) {
			icf_set_array($array, $k, $v);
		}

	} else {
		$keys = explode('.', $key);

		while (count($keys) > 1) {
			$key = array_shift($keys);

			if (!isset($array[$key]) || !is_array($array[$key])) {
				$array[$key] = array();
			}

			$array =& $array[$key];
		}

		$array[array_shift($keys)] = $value;
	}
}

function icf_delete_array(&$array, $key)
{
	if (is_null($key)) {
		return false;
	}

	if (is_array($key)) {
		$return = array();

		foreach ($key as $k) {
			$return[$k] = icf_delete_array($array, $k);
		}

		return $return;
	}

	$key_parts = explode('.', $key);

	if (!is_array($array) || !array_key_exists($key_parts[0], $array)) {
		return false;
	}

	$this_key = array_shift($key_parts);

	if (!empty($key_parts)) {
		$key = implode('.', $key_parts);
		return icf_delete_array($array[$this_key], $key);

	} else {
		unset($array[$this_key]);
	}

	return true;
}
