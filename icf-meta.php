<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package        ICF
 * @author         Masayuki Ietomi <jyokyoku@gmail.com>
 * @copyright      Copyright(c) 2011 Masayuki Ietomi
 * @link           http://inspire-tech.jp
 */

require_once dirname( __FILE__ ) . '/icf-loader.php';

class ICF_Meta {
	protected static $_types = array( 'post', 'user', 'option' );

	public static function post( $post, $key, $attr = array() ) {
		$post_id = null;

		if ( is_object( $post ) && isset( $post->ID ) ) {
			$post_id = $post->ID;

		} else if ( is_numeric( $post ) ) {
			$post_id = (int)$post;
		}

		if ( is_bool( $attr ) || ( is_string( $attr ) && preg_match( '/^[0|1]$/', $attr ) ) ) {
			$attr = array( 'single' => (bool)$attr );

		} else if ( is_scalar( $attr ) ) {
			$attr = array( 'default' => $attr );
		}

		$attr = wp_parse_args( $attr, array(
			'single' => true,
		) );

		$value = $post_id ? get_post_meta( $post_id, $key, $attr['single'] ) : null;

		if ( !is_string( $value ) || !$attr['single'] ) {
			return $value;
		}

		return self::_filter( $value, $attr );
	}

	public static function post_iteration( $post, $key, $min, $max, $attr = array() ) {
		return self::_iterate( 'post', $key, $min, $max, $post, $attr );
	}

	public static function current_post( $key, $attr = array() ) {
		global $post;

		return self::post( $post, $key, $attr );
	}

	public static function current_post_iteration( $key, $min, $max, $attr = array() ) {
		return self::_iterate( 'post', $key, $min, $max, null, $attr );
	}

	public static function user( $user, $key, $attr = array() ) {
		$user_id = null;

		if ( is_object( $user ) && isset( $user->ID ) ) {
			$user_id = $user->ID;

		} else if ( is_numeric( $user ) ) {
			$user_id = (int)$user;
		}

		if ( is_bool( $attr ) || ( is_string( $attr ) && preg_match( '/^[0|1]$/', $attr ) ) ) {
			$attr = array( 'single' => (bool)$attr );

		} else if ( is_scalar( $attr ) ) {
			$attr = array( 'default' => $attr );
		}

		$attr = wp_parse_args( $attr, array(
			'single' => true,
		) );

		$value = $user_id ? get_user_meta( $user_id, $key, $attr['single'] ) : null;

		if ( !is_string( $value ) || !$attr['single'] ) {
			return $value;
		}

		return self::_filter( $value, $attr );
	}

	public static function user_iteration( $user, $key, $min, $max, $attr = array() ) {
		return self::_iterate( 'user', $key, $min, $max, $user, $attr );
	}

	public static function current_user( $key, $attr = array() ) {
		$current_user = wp_get_current_user();

		return self::user( $current_user, $key, $attr );
	}

	public static function current_user_iteration( $key, $min, $max, $attr = array() ) {
		return self::_iterate( 'user', $key, $min, $max, null, $attr );
	}

	public static function option( $key, $attr = array() ) {
		$value = get_option( $key, false );

		if ( $value && !is_string( $value ) ) {
			return $option;
		}

		if ( is_scalar( $attr ) ) {
			$attr = array( 'default' => $attr );
		}

		return self::_filter( $value, $attr );
	}

	public static function option_iteration( $key, $min, $max, $attr = array() ) {
		return self::_iterate( 'option', $key, $min, $max, null, $attr );
	}

	protected static function _filter( $value, $attr = array() ) {
		return icf_filter( $value, $attr );
	}

	protected static function _iterate( $type, $key, $min, $max, $object = null, $attr = array() ) {
		$values = array();

		if (
			!in_array( $type, self::$_types ) || !method_exists( 'ICF_Meta', $type )
			|| !is_numeric( $min ) || !is_numeric( $max ) || $min >= $max
		) {
			return $values;
		}

		if ( !$object && $type != 'option' ) {
			$type = 'current_' . $type;
		}

		if ( strpos( $key, ':index' ) === false ) {
			$key .= ':index';
		}

		for ( $i = $min; $i <= $max; $i++ ) {
			$_key = str_replace( ':index', $i, $key );

			if ( !$object ) {
				$value = call_user_func( array( 'ICF_Meta', $type ), $_key, $attr );

			} else {
				$value = call_user_func( array( 'ICF_Meta', $type ), $object, $_key, $attr );
			}

			if ( $value ) {
				$values[$i] = $value;
			}
		}

		return $values;
	}
}