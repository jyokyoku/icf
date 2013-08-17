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
require_once dirname( __FILE__ ) . '/icf-component.php';

class ICF_MetaBox {
	public $title;

	public $context;

	public $priority;

	public $capability;

	protected $_screen;

	protected $_id;

	protected $_is_post = false;

	protected $_components = array();

	/**
	 * Constructor
	 *
	 * @param    string $screen
	 * @param    string $id
	 * @param    string $title
	 * @param    array  $args
	 */
	public function __construct( $screen, $id, $title = null, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'context' => 'normal', 'priority' => 'default', 'capability' => null, 'register' => true
		) );

		$this->_screen = $screen;
		$this->_id = $id;
		$this->_is_post = !is_null( get_post_type_object( $this->_screen ) );

		$this->title = empty( $title ) ? $id : $title;
		$this->context = $args['context'];
		$this->priority = $args['priority'];
		$this->capability = $args['capability'];

		if ( $args['register'] ) {
			add_action( 'admin_menu', array( $this, 'register' ) );
		}

		if ( $this->_is_post ) {
			add_action( 'save_post', array( $this, 'save_post_meta' ) );
		}
	}

	/**
	 * Returns the post type
	 *
	 * @return    string
	 */
	public function get_screen() {
		return $this->_screen;
	}

	/**
	 * Returns the id
	 *
	 * @return    string
	 */
	public function get_id() {
		return $this->_id;
	}

	/**
	 * Returns it belongs post type
	 */
	public function is_post() {
		return $this->_is_post;
	}

	/**
	 * Creates the ICF_MetaBox_Component
	 *
	 * @param    id|ICF_MetaBox_Component $id
	 * @param    string                   $title
	 * @return    ICF_MetaBox_Component
	 */
	public function component( $id, $title = null ) {
		if ( is_object( $id ) && is_a( $id, 'ICF_MetaBox_Component' ) ) {
			$component = $id;
			$id = $component->get_id();

			if ( isset( $this->_components[$id] ) && $this->_components[$id] !== $component ) {
				$this->_components[$id] = $component;
			}

		} else if ( is_string( $id ) && isset( $this->_components[$id] ) ) {
			$component = $this->_components[$id];

		} else {
			$component = new ICF_MetaBox_Component( $this, $id, $title );
			$this->_components[$id] = $component;
		}

		return $component;
	}

	/**
	 * Alias of 'component' method
	 *
	 * @param    id|ICF_MetaBox_Component $id
	 * @param    string                   $title
	 * @return    ICF_MetaBox_Component
	 * @see        ICF_MetaBox::component
	 */
	public function c( $id, $title = null ) {
		return $this->component( $id, $title );
	}

	/**
	 * Registers to system
	 */
	public function register() {
		if ( empty( $this->capability ) || ( !empty( $this->capability ) && current_user_can( $this->capability ) ) ) {
			add_meta_box( $this->_id, $this->title, array( $this, 'display' ), $this->_screen, $this->context, $this->priority );
		}
	}

	/**
	 * Displays the rendered html
	 *
	 * @param    mixed $object
	 */
	public function display( $object = null ) {
		if (
			!$this->_is_post
			|| (
				$object
				&& is_object( $object )
				&& isset( $object->ID, $object->post_type )
				&& $object->post_type == $this->_screen
			)
		) {
			$uniq_id = $this->_generate_uniq_id();
			wp_nonce_field( $uniq_id, $uniq_id . '_nonce' );

			foreach ( $this->_components as $component ) {
				$component->display( $object );
			}
		}
	}

	/**
	 * Saves the components for post meta.
	 *
	 * @param    int $post_id
	 * @return    NULL|int
	 */
	public function save_post_meta( $post_id ) {
		if (
			defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
			|| empty( $_POST['post_type'] )
			|| $_POST['post_type'] != $this->_screen
			|| ( !empty( $this->capability ) && !current_user_can( $this->capability, $post_id ) )
		) {
			return $post_id;
		}

		$uniq_id = $this->_generate_uniq_id();

		$refresh_params_key = $uniq_id . '_refresh';
		delete_option( $refresh_params_key );

		$nonce = isset( $_POST[$uniq_id . '_nonce'] ) ? $_POST[$uniq_id . '_nonce'] : '';

		if ( !$nonce || !wp_verify_nonce( $nonce, $uniq_id ) ) {
			return $post_id;
		}

		foreach ( $this->_components as $component ) {
			$component->save_post_meta( $post_id );
		}
	}

	protected function _generate_uniq_id() {
		return sha1( $this->_id . serialize( implode( '', array_keys( $this->_components ) ) ) );
	}
}

class ICF_MetaBox_Component extends ICF_Component_Abstract {
	public $title;

	protected $_metabox;

	protected $_id;

	/**
	 * Constructor
	 *
	 * @param    string $id
	 * @param    string $title
	 */
	public function __construct( ICF_MetaBox $metabox, $id, $title = '' ) {
		parent::__construct();

		$this->_metabox = $metabox;
		$this->_id = $id;

		$this->title = ( empty( $title ) && $title !== false ) ? $id : $title;

		if ( !$this->_metabox->is_post() ) {
			add_action( 'admin_menu', array( $this, 'register_option' ) );
		}
	}

	/**
	 * Returns the id
	 *
	 * @return    string
	 */
	public function get_id() {
		return $this->_id;
	}

	/**
	 * Returns the MetaBox
	 *
	 * @return ICF_MetaBox
	 */
	public function get_metabox() {
		return $this->_metabox;
	}

	/**
	 * Saves the elements for post meta
	 *
	 * @param    int $post_id
	 */
	public function save_post_meta( $post_id ) {
		foreach ( $this->_elements as $element ) {
			if ( is_subclass_of( $element, 'ICF_MetaBox_Component_Element_FormField_Abstract' ) ) {
				$element->save_post_meta( $post_id );
			}
		}
	}

	/**
	 * Registers the elements
	 */
	public function register_option() {
		foreach ( $this->_elements as $element ) {
			if ( is_subclass_of( $element, 'ICF_MetaBox_Component_Element_FormField_Abstract' ) ) {
				$element->register_option();
			}
		}
	}

	public function render( $arg1 = null, $arg2 = null ) {
		$args = func_get_args();

		$html = $this->title ? ICF_Tag::create( 'p', null, ICF_Tag::create( 'strong', null, $this->title ) ) : '';
		$html .= call_user_func_array( array( $this, 'parent::render' ), $args );

		return $html;
	}
}

class ICF_MetaBox_Component_Element_Value extends ICF_Component_Element_Abstract {
	protected $_name;

	protected $_default;

	public function __construct( ICF_MetaBox_Component $component, $name, $default = null ) {
		$this->_name = $name;
		$this->_default = $default;

		parent::__construct( $component );
	}

	public function render() {
		$args = func_get_args();
		$value = $this->_default;

		if ( $this->_component->get_metabox()->is_post() ) {
			if ( $post = array_shift( $args ) ) {
				$value = ( $meta_value = get_post_meta( $post->ID, $this->_name, true ) ) ? $meta_value : $value;
			}

		} else {
			$value = get_option( $this->_name, $value );
		}

		return $value;
	}
}

abstract class ICF_MetaBox_Component_Element_FormField_Abstract extends ICF_Component_Element_FormField_Abstract {
	protected $_stored_value = false;

	public function __construct( ICF_MetaBox_Component $component, $name, $value = null, array $args = array() ) {
		parent::__construct( $component, $name, $value, $args );
	}

	public function initialize() {
		parent::initialize();

		if ( in_array( 'chkrequired', $this->_validation ) ) {
			$required_mark = '<span style="color: #B00C0C;">*</span>';

			if ( $this->_component->title && !preg_match( '|' . preg_quote( $required_mark ) . '$|', $this->_component->title ) ) {
				$this->_component->title .= ' ' . $required_mark;

			} else if ( !preg_match( '|' . preg_quote( $required_mark ) . '$|', $this->_component->get_metabox()->title ) ) {
				$this->_component->get_metabox()->title .= ' ' . $required_mark;
			}
		}
	}

	public function before_render() {
		$args = func_get_args();

		if ( $this->_component->get_metabox()->is_post() ) {
			$post = array_shift( $args );

			if (
				isset( $post->ID, $post->post_type )
				&& $post->post_type == $this->_component->get_metabox()->get_screen()
				&& $this->exists_post_meta( $post->ID )
			) {
				$this->_stored_value = get_post_meta( $post->ID, $this->_name, true );
			}

		} else {
			$this->_stored_value = get_option( $this->_name, false );
		}
	}

	public function save_post_meta( $post_id ) {
		if ( !isset( $_POST[$this->_name] ) ) {
			return false;
		}

		update_post_meta( $post_id, $this->_name, $_POST[$this->_name] );

		return true;
	}

	public function register_option() {
		register_setting( $this->_component->get_metabox()->get_screen(), $this->_name );

		if ( get_option( $this->_name ) === false && $this->_value ) {
			update_option( $this->_name, $this->_value );
		}
	}

	public function exists_post_meta( $post_id ) {
		if ( !( $post_id = absint( $post_id ) ) || !$this->_name ) {
			return false;
		}

		$check = apply_filters( 'get_post_metadata', null, $post_id, $this->_name, true );

		if ( null !== $check ) {
			return true;
		}

		$meta_cache = wp_cache_get( $post_id, 'post_meta' );

		if ( !$meta_cache ) {
			$meta_cache = update_meta_cache( 'post', array( $post_id ) );
			$meta_cache = $meta_cache[$post_id];
		}

		return isset( $meta_cache[$this->_name] );
	}
}

class ICF_MetaBox_Component_Element_FormField_Text extends ICF_MetaBox_Component_Element_FormField_Abstract {
	public function before_render() {
		$args = func_get_args();
		call_user_func_array( array( $this, 'parent::before_render' ), $args );

		if ( $this->_stored_value !== false ) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_MetaBox_Component_Element_FormField_Password extends ICF_MetaBox_Component_Element_FormField_Abstract {
	public function before_render() {
		$args = func_get_args();
		call_user_func_array( array( $this, 'parent::before_render' ), $args );

		if ( $this->_stored_value !== false ) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_MetaBox_Component_Element_FormField_Hidden extends ICF_MetaBox_Component_Element_FormField_Abstract {
	public function before_render() {
		$args = func_get_args();
		call_user_func_array( array( $this, 'parent::before_render' ), $args );

		if ( $this->_stored_value !== false ) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_MetaBox_Component_Element_FormField_Textarea extends ICF_MetaBox_Component_Element_FormField_Abstract {
	public function before_render() {
		$args = func_get_args();
		call_user_func_array( array( $this, 'parent::before_render' ), $args );

		if ( $this->_stored_value !== false ) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_MetaBox_Component_Element_FormField_Checkbox extends ICF_MetaBox_Component_Element_FormField_Abstract {
	public function register_option() {
		register_setting( $this->_component->get_metabox()->get_screen(), $this->_name );

		if ( get_option( $this->_name ) === false && $this->_value && !empty( $this->_args['checked'] ) ) {
			update_option( $this->_name, $this->_value );
		}
	}

	public function before_render() {
		$args = func_get_args();
		call_user_func_array( array( $this, 'parent::before_render' ), $args );

		if ( $this->_stored_value !== false ) {
			$this->_args['checked'] = ( $this->_stored_value == $this->_value );
			unset( $this->_args['selected'] );
		}
	}
}

class ICF_MetaBox_Component_Element_FormField_Radio extends ICF_MetaBox_Component_Element_FormField_Abstract {
	public function register() {
		register_setting( $this->_component->get_metabox()->get_screen(), $this->_name );

		if (
			get_option( $this->_name ) === false
			&& $this->_value
			&& !empty( $this->_args['checked'] )
			&& in_array( $this->_args['checked'], array_values( (array)$this->_value ) )
		) {
			update_option( $this->_name, $this->_args['checked'] );
		}
	}

	public function before_render() {
		$args = func_get_args();
		call_user_func_array( array( $this, 'parent::before_render' ), $args );

		if ( $this->_stored_value !== false ) {
			$this->_args['checked'] = in_array( $this->_stored_value, (array)$this->_value ) ? $this->_stored_value : false;
			unset( $this->_args['selected'] );
		}
	}
}

class ICF_MetaBox_Component_Element_FormField_Select extends ICF_MetaBox_Component_Element_FormField_Abstract {
	public function register() {
		register_setting( $this->_component->get_metabox()->get_screen(), $this->_name );

		if (
			get_option( $this->_name ) === false
			&& $this->_value
			&& !empty( $this->_args['selected'] )
			&& in_array( $this->_args['selected'], array_values( (array)$this->_value ) )
		) {
			update_option( $this->_name, $this->_args['selected'] );
		}
	}

	public function before_render() {
		$args = func_get_args();
		call_user_func_array( array( $this, 'parent::before_render' ), $args );

		if ( $this->_stored_value !== false ) {
			$this->_args['selected'] = in_array( $this->_stored_value, (array)$this->_value ) ? $this->_stored_value : false;
			unset( $this->_args['checked'] );
		}
	}
}

class ICF_MetaBox_Component_Element_FormField_Wysiwyg extends ICF_MetaBox_Component_Element_FormField_Abstract {
	public function initialize() {
		parent::initialize();

		if ( !isset( $this->_args['settings'] ) ) {
			$this->_args['settings'] = array();
		}

		$this->_args['id'] = $this->_name;
	}

	public function before_render() {
		$args = func_get_args();
		call_user_func_array( array( $this, 'parent::before_render' ), $args );

		if ( $this->_stored_value !== false ) {
			$this->_value = $this->_stored_value;
		}
	}

	public function render() {
		$editor = '';

		if ( version_compare( get_bloginfo( 'version' ), '3.3', '>=' ) && function_exists( 'wp_editor' ) ) {
			ob_start();
			wp_editor( $this->_value, $this->_args['id'], $this->_args['settings'] );
			$editor = ob_get_clean();

		} else {
			trigger_error( 'The TinyMCE has been required for the WordPress 3.3 or above' );
		}

		return $editor;
	}
}

class ICF_MetaBox_Component_Element_FormField_Visual extends ICF_MetaBox_Component_Element_FormField_Wysiwyg {
}
