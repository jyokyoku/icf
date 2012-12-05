<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-loader.php';

class ICF_Meta
{
	protected static $_types = array('post', 'user', 'option');

	public static function post($post, $key, $attr = array())
	{
		$post_id = null;

		if (is_object($post) && isset($post->ID)) {
			$post_id = $post->ID;

		} else if (is_numeric($post)) {
			$post_id = (int)$post;
		}

		if (is_bool($attr) || (is_string($attr) && preg_match('|^[0|1]$|', $attr))) {
			$attr = array('single' => (bool)$attr);
		}

		$attr = wp_parse_args($attr, array(
			'single'  => false,
		));

		$value = $post_id ? get_post_meta($post_id, $key, $attr['single']) : null;

		if (!is_string($value) || !$attr['single']) {
			return $value;
		}

		return self::_filter($value, $attr);
	}

	public static function post_iteration($post, $key, $min, $max, $attr = array())
	{
		return self::_iterate('post', $key, $min, $max, $user, $attr);
	}

	public static function current_post($key, $attr = array())
	{
		global $post;

		return self::post($post, $key, $attr);
	}

	public static function current_post_iteration($key, $min, $max, $attr = array())
	{
		return self::_iterate('post', $key, $min, $max, null, $attr);
	}

	public static function user($user, $key, $attr = array())
	{
		$user_id = null;

		if (is_object($user) && isset($user->ID)) {
			$user_id = $user->ID;

		} else if (is_numeric($user)) {
			$user_id = (int)$user;
		}

		if (is_bool($attr) || (is_string($attr) && preg_match('|^[0|1]$|', $attr))) {
			$attr = array('single' => (bool)$attr);
		}

		$attr = wp_parse_args($attr, array(
			'single'  => false,
		));

		$value = $user_id ? get_user_meta($user_id, $key, $attr['single']) : null;

		if (!is_string($value) || !$attr['single']) {
			return $value;
		}

		return self::_filter($value, $attr);
	}

	public static function user_iteration($user, $key, $min, $max, $attr = array())
	{
		return self::_iterate('user', $key, $min, $max, $user, $attr);
	}

	public static function current_user($key, $attr = array())
	{
		$current_user = wp_get_current_user();

		return self::user($current_user, $key, $attr);
	}

	public static function current_user_iteration($key, $min, $max, $attr = array())
	{
		return self::_iterate('user', $key, $min, $max, null, $attr);
	}

	public static function option($key, $attr = array())
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

	public static function option_iteration($key, $min, $max, $attr = array())
	{
		return self::_iterate('option', $key, $min, $max, null, $attr);
	}

	protected static function _filter($value, $attr = array())
	{
		$attr = wp_parse_args($attr, array(
			'filter'  => false,
			'default' => false,
			'before'  => '',
			'after'   => '',
		));

		if ($attr['filter'] && $value) {
			if (is_callable($attr['filter'])) {
				$value = call_user_func($attr['filter'], $value);

			} else if (is_array($attr['filter'])) {
				foreach ($attr['filter'] as $filter => $args) {
					if (is_int($filter) && $args) {
						$filter = $args;
						$args = array();
					}

					if (!is_callable($filter)) {
						continue;
					}

					if ($args && !is_array($args)) {
						$args = array($args);

					} else {
						$args = array();
					}

					array_unshift($args, $value);
					$value = call_user_func($filter, $value);
				}
			}
		}

		if (!$value) {
			return $attr['single'] ? $attr['default'] ? $attr['before'] . $attr['default'] . $attr['after'] : $attr['default'] : array();
		}

		return $value;
	}

	protected static function _iterate($type, $key, $min, $max, $object = null, $attr = array())
	{
		$values = array();

		if (
			!in_array($type, self::$_types) || !method_exists('ICF_Meta', $type)
			|| !is_numeric($min) || !is_numeric($max) || $min >= $max
		) {
			return $values;
		}

		if (!$object && $type != 'option') {
			$type = 'current_' . $type;
		}

		for ($i = $min; $i <= $max; $i++) {
			if (!$object) {
				$value = call_user_func(array('ICF_Meta', $type), $key . $i, $attr);

			} else {
				$value = call_user_func(array('ICF_Meta', $type), $object, $key . $i, $attr);
			}

			if ($value) {
				$values[] = $value;
			}
		}

		return $values;
	}
}