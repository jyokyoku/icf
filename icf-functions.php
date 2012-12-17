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

	$log_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'icf-logs';

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
 * @param	string|array	$_
 * @return	mixed
 */
function icf_filter(array $array, $key, $_ = null)
{
	$args = func_get_args();
	$keys = array_splice($args, 1);
	$values = array();

	foreach ($keys as $key) {
		$default = null;

		if (is_array($key)) {
			if (count($key) > 1) {
				list($key, $default) = $key;

			} else {
				$key = reset($key);
			}
		}

		if (!$key) {
			continue;
		}

		if (isset($array[$key])) {
			$values[] = $array[$key];

		} else {
			$values[] = $default;
		}
	}

	return (count($key) > 1) ? $values : reset($values);
}

/**
 * Returns a value(s) of the specified key(s) of the array and removes it from the array.
 *
 * @param	array			$array
 * @param	string|array	$key
 * @param	string|array	$_
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

function icf_get_post_thumbnail_data($post_id = null, $default_src = null, $default_alt = null)
{
	global $post;

	if (!$post_id && $post) {
		$post_id = $post->ID;
	}

	$data = array(
		'src' => $default_src,
		'alt' => $default_alt
	);

	if (has_post_thumbnail($post_id)) {
		if ($post_thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), '')) {
			$data['src'] = $post_thumbnail_src[0];
		}

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
	}

	return $data;
}

function icf_get_document_root()
{
	$document_root = getenv('DOCUMENT_ROOT');

	if (!$document_root && ($php_self = getenv('PHP_SELF'))) {
		if ($script_filename = getenv('SCRIPT_FILENAME')) {
			$document_root = str_replace( '\\', '/', substr($script_filename, 0, 0 - strlen($php_self)));

		} else if ($path_traslated = getenv('PATH_TRANSLATED')) {
			$document_root = str_replace( '\\', '/', substr(str_replace('\\\\', '\\', $path_traslated), 0, 0 - strlen($php_self)));
		}
	}

	if ($document_root && getenv('DOCUMENT_ROOT') != '/'){
		$document_root = preg_replace('/\/$/', '', $document_root);
	}

	return $document_root;
}

function icf_url_to_path($url)
{
	$host = preg_replace('/^www\./i', '', getenv('HTTP_HOST'));
	$url = ltrim(preg_replace('/https?:\/\/(?:www\.)?' . $host . '/i', '', $url), '/');
	$document_root = icf_get_document_root();

	if (!$document_root) {
		$file = preg_replace('/^.*?([^\/\\\\]+)$/', '$1', $url);

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
	$script_filename = getenv('SCRIPT_FILENAME');

	if (strstr($script_filename, ':')) {
		$sub_directories = explode('\\', str_replace($document_root, '', $script_filename));

	} else {
		$sub_directories = explode('/', str_replace($document_root, '', $script_filename));
	}

	foreach ($sub_directories as $sub){
		$base .= $sub . '/';

		if(file_exists($base . $url)){
			$real = realpath($base . $url);

			if (stripos($real, realpath($document_root)) === 0) {
				return $real;
			}
		}
	}

	return false;
}