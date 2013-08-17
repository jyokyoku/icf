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

class ICF_Tag {
	protected $_stack = array();

	protected $_capture_stack = array();

	protected $_elements = array();

	public function __call( $method, $args ) {
		if ( preg_match( '/^(open|close)_([a-zA-Z_]+)$/', $method, $matches ) ) {
			call_user_func( array( $this, $matches[1] ), $matches[2], $args );

		} else {
			$attributes = !empty( $args ) ? (array)array_shift( $args ) : array();
			$this->open( $method, $attributes );
		}

		return $this;
	}

	public function __get( $property ) {
		return $this->{$property}();
	}

	public function open( $tag, $attributes = array() ) {
		$tag = strtolower( $tag );
		$element = new ICF_Tag_Element_Node( $tag, $attributes );

		if ( !$element->is_empty() ) {
			$this->_stack[] = $tag;
		}

		$this->_elements[] = $element;

		return $this;
	}

	public function close( $tag = null ) {
		if ( !empty( $this->_stack ) ) {
			$current_tag = array_pop( $this->_stack );

			if ( !empty( $tag ) && strtolower( $tag ) !== $current_tag ) {
				trigger_error( 'Tag "' . strtolower( $tag ) . '" is not current opened tag', E_USER_WARNING );

			} else {
				$this->_elements[] = new ICF_Tag_Element_Node( $current_tag, false );
			}
		}

		return $this;
	}

	public function capture() {
		ob_start();
		$this->_capture_stack[] = true;

		return $this;
	}

	public function capture_end() {
		if ( count( $this->_capture_stack ) > 0 ) {
			array_pop( $this->_capture_stack );
			$this->html( ob_get_clean() );
		}

		return $this;
	}

	public function capture_all_end() {
		while ( $this->_capture_stack ) {
			$this->capture_end();
		}
	}

	public function func( $callback ) {
		$args = func_get_args();
		$args = array_splice( $args, 1 );

		if ( is_callable( $callback ) ) {
			if ( $result = call_user_func_array( $callback, $args ) ) {
				$this->html( $result );
			}
		}

		return $this;
	}

	public function all_close() {
		while ( $this->_stack ) {
			$this->close();
		}

		return $this;
	}

	public function clear() {
		$this->capture_all_end();
		$this->clear_stack();
		$this->clear_elements();

		return $this;
	}

	public function clear_stack() {
		$this->_stack = array();

		return $this;
	}

	public function clear_elements() {
		$this->_elements = array();

		return $this;
	}

	public function html( $html ) {
		$this->_elements[] = new ICF_Tag_Element_Html( $html );

		return $this;
	}

	public function render() {
		$this->all_close();
		$this->capture_all_end();

		$html = '';

		foreach ( $this->_elements as $element ) {
			$html .= $element->render();
		}

		$this->clear();

		return $html;
	}

	public static function create( $tag, $attributes = array(), $content = null ) {
		$html = '';
		$open = new ICF_Tag_Element_Node( $tag, $attributes );

		if ( $content !== false && !is_null( $content ) ) {
			$close = new ICF_Tag_Element_Node( $tag, false );
			$html = $open->render() . $content . $close->render();

		} else {
			$html = $open->render();
		}

		return $html;
	}
}

interface ICF_Tag_Element_Interface {
	public function render();
}

class ICF_Tag_Element_Node implements ICF_Tag_Element_Interface {
	protected static $_open_tag_format = '<%s%s>';

	protected static $_close_tag_format = '</%s>';

	protected static $_empty_tag_format = '<%s%s />';

	protected static $_attribute_format = '%s="%s"';

	protected static $_empty_tags = array(
		'area', 'base', 'br', 'col', 'hr', 'img',
		'input', 'link', 'meta', 'param'
	);

	protected static $_minimized_attributes = array(
		'compact', 'checked', 'declare', 'readonly', 'disabled', 'selected',
		'defer', 'ismap', 'nohref', 'noshade', 'nowrap', 'multiple', 'noresize'
	);

	protected $_tag;

	protected $_attributes = array();

	public function __construct( $tag, $attributes = array() ) {
		$this->_tag = $tag;
		$this->_close = ( $attributes === false );
		$this->_attributes = $attributes;
	}

	public function is_empty() {
		return in_array( $this->_tag, self::$_empty_tags );
	}

	public function render() {
		$html = '';

		if ( $this->_close ) {
			$html = sprintf( self::$_close_tag_format, $this->_tag );

		} else {
			$attributes = ( $attributes = self::parse_attributes( $this->_attributes ) ) ? ' ' . $attributes : '';

			if ( $this->is_empty() ) {
				$html = sprintf( self::$_empty_tag_format, $this->_tag, $attributes );

			} else {
				$html = sprintf( self::$_open_tag_format, $this->_tag, $attributes );
			}
		}

		return $html;
	}

	public static function parse_attributes( $attributes = array() ) {
		$formatted = array();

		foreach ( wp_parse_args( $attributes ) as $property => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ' ', $value );
			}

			if ( is_string( $value ) ) {
				$value = trim( $value );
			}

			if ( is_numeric( $property ) ) {
				if ( empty( $value ) || strpos( ' ', $value ) !== false ) {
					continue;
				}

				$property = $value;

			} else if ( in_array( $property, self::$_minimized_attributes ) ) {
				if ( $value !== true && $value !== '1' && $value !== 1 && $value != $property ) {
					continue;
				}

				$value = $property;
			}

			$formatted[] = sprintf( self::$_attribute_format, $property, esc_attr( $value ) );
		}

		return implode( ' ', $formatted );
	}

	public static function add_class( array &$attributes, $class ) {
		if ( empty( $attributes['class'] ) ) {
			$attributes['class'] = $class;

		} else {
			$attributes['class'] .= " {$class}";
		}
	}
}

class ICF_Tag_Element_Html implements ICF_Tag_Element_Interface {
	protected $_html;

	public function __construct( $html ) {
		$this->_html = $html;
	}

	public function render() {
		return $this->_html;
	}
}