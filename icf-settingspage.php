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
require_once dirname( __FILE__ ) . '/icf-metabox.php';

abstract class ICF_SettingsPage_Abstract {
	public $title;

	public $menu_title;

	public $capability;

	public $template;

	public $independent;

	public $function;

	public $include_header;

	public $before_template;

	public $after_template;

	public $embed_form;

	protected $_rendered_html = '';

	protected $_component;

	protected $_slug;

	protected $_sections = array();

	protected $_metaboxes = array();

	/**
	 * Constructor
	 */
	public function __construct( $slug, $title = null, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'menu_title' => null, 'capability' => 'manage_options',
			'template' => null, 'function' => null,
		) );

		$this->_slug = $slug;
		$this->_component = new ICF_SettingsPage_Section_Component( 'common', null, $this->_slug, false );

		$this->title = empty( $title ) ? $this->_slug : $title;
		$this->menu_title = empty( $args['menu_title'] ) ? $this->title : $args['menu_title'];
		$this->capability = $args['capability'];

		if ( version_compare( get_bloginfo( 'version' ), '3.2', '<' ) && $this->capability != 'manage_options' ) {
			trigger_error( 'Specifying capabilities are supported the WordPress version 3.2 or above.', E_USER_WARNING );
		}

		$this->template = $args['template'];
		$this->function = $args['function'];

		add_action( 'option_page_capability_' . $this->_slug, array( $this, 'get_capability' ) );
		add_action( 'admin_menu', array( $this, 'register' ) );
		add_action( 'admin_init', array( $this, 'pre_render' ) );
	}

	/**
	 * Magic method
	 *
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	public function __call( $method, $args ) {
		return call_user_func_array( array( $this->_component, $method ), $args );
	}

	/**
	 * Magic method
	 *
	 * @param $property
	 * @return mixed
	 */
	public function __get( $property ) {
		return $this->{$property}();
	}

	/**
	 * Returns the capability of settings page
	 *
	 * @return string
	 */
	public function get_capability() {
		return $this->capability;
	}

	/**
	 * Returns the settings page slug
	 *
	 * @return    string
	 */
	public function get_slug() {
		return $this->_slug;
	}

	/**
	 * Creates the ICF_SettingsPage_Section
	 *
	 * @param    string|ICF_SettingsPage_Section $id
	 * @param    string                          $title
	 * @param    callback                        $callback
	 * @return    ICF_SettingsPage_Section
	 */
	public function section( $id = null, $title = null, $callback = null ) {
		if ( is_object( $id ) && is_a( $id, 'ICF_SettingsPage_Section' ) ) {
			$section = $id;
			$id = $section->get_id();

			if ( isset( $this->_sections[$id] ) ) {
				if ( $this->_sections[$id] !== $section ) {
					$this->_sections[$id] = $section;
				}

				return $section;
			}

		}
		if ( isset( $this->_sections[$id] ) ) {
			return $this->_sections[$id];

		} else {
			$section = new ICF_SettingsPage_Section( $this->_slug, $id, $title, $callback );
		}

		$this->_sections[$id] = $section;

		return $section;
	}

	/**
	 * Alias of 'section' method
	 *
	 * @param    string|ICF_SettingsPage_Section $id
	 * @param    string                          $title
	 * @param    callback                        $callback
	 * @return    ICF_SettingsPage_Section
	 * @see        ICF_SettingsPage_Abstract::section
	 */
	public function s( $id = null, $title = null, $callback = null ) {
		return $this->section( $id, $title, $callback );
	}

	/**
	 * Craetes the ICF_MetaBox
	 *
	 * @param    string|ICF_MetaBox $id
	 * @param    string             $title
	 * @param    array              $args
	 * @return    ICF_MetaBox
	 */
	public function metabox( $id, $title = '', $args = array() ) {
		if ( is_object( $id ) && is_a( $id, 'ICF_MetaBox' ) ) {
			$metabox = $id;
			$id = $metabox->get_id();

			if ( isset( $this->_metaboxes[$id] ) && $this->_metaboxes[$id] !== $metabox ) {
				$this->_metaboxes[$id] = $metabox;
			}

		}
		if ( is_string( $id ) && isset( $this->_metaboxes[$id] ) ) {
			$metabox = $this->_metaboxes[$id];

		} else {
			$metabox = new ICF_MetaBox( $this->_slug, $id, $title, $args );
			$this->_metaboxes[$id] = $metabox;
		}

		return $metabox;
	}

	/**
	 * Alias of 'metabox' method
	 *
	 * @param    string|ICF_MetaBox $id
	 * @param    string             $title
	 * @param    array              $args
	 * @return    ICF_MetaBox
	 * @see        ICF_SettingsPage_Abstract::metabox
	 */
	public function m( $id, $title = '', $args = array() ) {
		return $this->metabox( $id, $title, $args );
	}

	/**
	 * Render and cache the html
	 */
	public function pre_render() {
		global $wp_settings_fields;

		ob_start();

		if ( $this->template ) {
			$plugin_basename = IPF_Core::plugin_basename( __FILE__ );

			if ( is_file( $this->template ) && is_readable( $this->template ) ) {
				include $this->template;

			} else if (
				is_file( WP_PLUGIN_DIR . '/' . $plugin_basename . '/' . $this->template )
				&& is_readable( WP_PLUGIN_DIR . '/' . $plugin_basename . '/' . $this->template )
			) {
				include WP_PLUGIN_DIR . '/' . $plugin_basename . '/' . $this->template;

			} else if (
				is_file( WPMU_PLUGIN_DIR . '/' . $plugin_basename . '/' . $this->template )
				&& is_readable( WPMU_PLUGIN_DIR . '/' . $plugin_basename . '/' . $this->template )
			) {
				include WPMU_PLUGIN_DIR . '/' . $plugin_basename . '/' . $this->template;

			} else if (
				is_file( get_stylesheet_directory() . '/' . $this->template )
				&& is_readable( get_stylesheet_directory() . '/' . $this->template )
			) {
				include get_stylesheet_directory() . '/' . $this->template;

			} else if (
				is_file( get_template_directory() . '/' . $this->template )
				&& is_readable( get_template_directory() . '/' . $this->template )
			) {
				include get_template_directory() . '/' . $this->template;

			} else {
				wp_die( sprintf( __( 'Template file `%s` is not exists.', 'icf' ), $this->template ) );
			}

		} else {
			echo $this->get_header();

			if ( !empty( $wp_settings_fields[$this->_slug]['default'] ) ) {
				?>
				<table class="form-table">
					<?php echo $this->get_settings_fields( 'default' ) ?>
				</table>
				<?php
			}

			echo $this->get_settings_sections();
			?>
			<div id="poststuff">
				<?php
				echo $this->get_metaboxes( 'normal' );
				echo $this->get_metaboxes( 'advanced' );
				?>
			</div>
			<?php
			echo $this->get_footer();
		}

		$this->_rendered_html = ob_get_clean();
	}

	/**
	 * Returns the rendered html
	 *
	 * @return string
	 */
	public function render() {
		if ( !$this->_rendered_html ) {
			$this->pre_render();
		}

		return $this->_rendered_html;
	}

	/**
	 * Display the html
	 */
	public function display() {
		echo $this->render();
	}

	/**
	 * Returns the header html
	 *
	 * @param array $attr
	 * @return string
	 */
	public function get_header( $attr = array() ) {
		$attr = wp_parse_args( $attr, array(
			'title' => $this->title,
			'form_action' => 'options.php',
			'form_id' => $this->_slug . '_form'
		) );

		ob_start();
			?>
		<div class="wrap">
		<h2><?php echo esc_html( $attr['title'] ) ?></h2>
		<form method="post" action="<?php echo $attr['form_action'] ?>" id="<?php echo $attr['form_id'] ?>">
			<?php
		require ABSPATH . 'wp-admin/options-head.php';
		echo $this->get_hidden_fields();

		return ob_get_clean();
		}

	/**
	 * Returns the footer html
	 *
	 * @param array $attr
	 * @return string
	 */
	public function get_footer( $attr = array() ) {
		$attr = wp_parse_args( $attr, array(
			'submit_button' => true
		) );

		ob_start();

		if ($attr['submit_button']) {
			submit_button();
		}
			?>
		</form>
			</div>
			<?php
		return ob_get_clean();
	}

	/**
	 * Wrapper of 'settings_fields' function
	 *
	 * @see    settings_fields
	 */
	public function get_hidden_fields() {
		ob_start();
		settings_fields( $this->_slug );

		return ob_get_clean();
	}

	/**
	 * Wrapper of 'do_settings_sections' function
	 *
	 * @see    do_settings_sections
	 */
	public function get_settings_sections() {
		ob_start();
		do_settings_sections( $this->_slug );

		return ob_get_clean();
	}

	/**
	 * Wrapper of 'do_settings_fields' function
	 *
	 * @param    string $section
	 * @see        do_settings_fields
	 */
	public function get_settings_fields( $section = 'default' ) {
		ob_start();
		do_settings_fields( $this->_slug, $section );

		return ob_get_clean();
	}

	/**
	 * Wrapper of 'do_meta_boxes' function
	 *
	 * @param    string $context
	 * @see        do_meta_boxes
	 */
	public function get_metaboxes( $context = 'normal' ) {
		ob_start();
		do_meta_boxes( $this->_slug, $context, $this );

		return ob_get_clean();
	}

	abstract public function register();
}

class ICF_SettingsPage_Parent extends ICF_SettingsPage_Abstract {
	public $icon_url;

	public $position;

	protected $_children = array();

	/**
	 * Constructor
	 *
	 * @param    string $slug
	 * @param    string $title
	 * @param    array  $args
	 */
	public function __construct( $slug, $title = null, $args = array() ) {
		parent::__construct( $slug, $title, $args );
		$args = wp_parse_args( $args, array( 'icon_url' => null, 'position' => null ) );

		$this->icon_url = $args['icon_url'];
		$this->position = $args['position'];
	}

	/**
	 * Creates the ICF_SettingsPage_Child
	 *
	 * @param    string|ICF_SettingsPage_Child $slug
	 * @param    string                        $title
	 * @param    array                         $args
	 * @return    ICF_SettingsPage_Child
	 */
	public function child( $slug, $title = null, $args = array() ) {
		if ( is_object( $slug ) && is_a( $slug, 'ICF_SettingsPage_Child' ) ) {
			$child = $slug;
			$slug = $child->get_slug();

			if ( isset( $this->_children[$slug] ) ) {
				if ( $this->_children[$slug] !== $child ) {
					$this->_children[$slug] = $child;
				}

				return $child;
			}

		} else if ( !empty( $this->_children[$slug] ) ) {
			return $this->_children[$slug];

		} else {
			$child = new ICF_SettingsPage_Child( $this, $slug, $title, $args );
		}

		$this->_children[$slug] = $child;

		return $child;
	}

	/**
	 * Alias of 'child' method
	 *
	 * @param    string|ICF_SettingsPage_Child $slug
	 * @param    string                        $title
	 * @param    array                         $args
	 * @return    ICF_SettingsPage_Child
	 * @see        ICF_SettingsPage_Parent::child
	 */
	public function c( $slug, $title = null, $args = array() ) {
		return $this->child( $slug, $title, $args );
	}

	public function register() {
		add_menu_page(
			$this->title, $this->menu_title, $this->capability, $this->_slug,
			is_callable( $this->function ) ? $this->function : array( $this, 'display' ),
			$this->icon_url, $this->position
		);
	}
}

class ICF_SettingsPage_Child extends ICF_SettingsPage_Abstract {
	protected $_parent_slug;

	/**
	 * Constructor
	 *
	 * @param    string|ICF_SettingsPage_Parent $parent_slug
	 * @param    string                         $slug
	 * @param    string                         $title
	 * @param    string                         $menu_title
	 * @param    array                          $args
	 */
	public function __construct( $parent_slug, $slug, $title = null, $args = array() ) {
		parent::__construct( $slug, $title, $args );

		if ( is_object( $parent_slug ) && is_a( $parent_slug, 'ICF_SettingsPage_Parent' ) ) {
			$this->_parent_slug = $parent_slug->get_slug();

		} else {
			$parent_alias = array(
				'management' => 'tools.php',
				'options' => 'options-general.php',
				'theme' => 'themes.php',
				'plugin' => 'plugins.php',
				'users' => current_user_can( 'edit_users' ) ? 'users.php' : 'profile.php',
				'dashboard' => 'index.php',
				'posts' => 'edit.php',
				'media' => 'upload.php',
				'links' => 'link-manager.php',
				'pages' => 'edit.php?post_type=page',
				'comments' => 'edit-comments.php'
			);

			$this->_parent_slug = isset( $parent_alias[$parent_slug] ) ? $parent_alias[$parent_slug] : $parent_slug;
		}
	}

	/**
	 * Returns the parent page slug
	 *
	 * @return    string
	 */
	public function get_parent_slug() {
		return $this->_parent_slug;
	}

	/**
	 * Registers to system
	 */
	public function register() {
		add_submenu_page(
			$this->_parent_slug, $this->title, $this->menu_title,
			$this->capability, $this->_slug,
			is_callable( $this->function ) ? $this->function : array( $this, 'display' )
		);
	}
}

class ICF_SettingsPage_Section {
	public $title;

	public $description_or_callback;

	protected $_id;

	protected $_page_slug;

	protected $_components = array();

	/**
	 * Constructor
	 *
	 * @param    string          $page_slug
	 * @param    string          $id
	 * @param    stirng          $title
	 * @param    string|callback $description_or_callback
	 */
	public function __construct( $page_slug, $id = null, $title = null, $description_or_callback = null ) {
		$this->_page_slug = $page_slug;
		$this->_id = empty( $id ) ? 'default' : $id;

		$this->title = empty( $title ) ? $this->_id : $title;
		$this->description_or_callback = $description_or_callback;

		add_action( 'admin_menu', array( $this, 'register' ) );
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
	 * Returns the page slug
	 *
	 * @return    string
	 */
	public function get_page_slug() {
		return $this->_page_slug;
	}

	/**
	 * Creates the component
	 *
	 * @param    string|ICF_SettingsPage_Section_Component $id
	 * @param    string                                    $title
	 * @return    ICF_SettingsPage_Section_Component
	 */
	public function component( $id, $title = '' ) {
		if ( is_object( $id ) && is_a( $id, 'ICF_SettingsPage_Section_Component' ) ) {
			$component = $id;
			$id = $component->get_id();

			if ( isset( $this->_components[$id] ) && $this->_components[$id] !== $component ) {
				$this->_components[$id] = $component;
			}

		} else if ( is_string( $id ) && isset( $this->_components[$id] ) ) {
			$component = $this->_components[$id];

		} else {
			$component = new ICF_SettingsPage_Section_Component( $id, $title, $this->_page_slug, $this->_id );
			$this->_components[$id] = $component;
		}

		return $component;
	}

	/**
	 * Alias of 'component' method
	 *
	 * @param    string|ICF_SettingsPage_Section_Component $id
	 * @param    string                                    $title
	 * @return    ICF_SettingsPage_Section_Component
	 * @see        ICF_SettingsPage_Section::component
	 */
	public function c( $id, $title = '' ) {
		return $this->component( $id, $title );
	}

	/**
	 * Registers to system
	 */
	public function register() {
		if ( $this->_id != 'default' ) {
			$callback = is_callable( $this->description_or_callback ) ? $this->description_or_callback : array( $this, 'display' );
			add_settings_section( $this->_id, $this->title, $callback, $this->_page_slug );
		}
	}

	/**
	 * Displays the html
	 */
	public function display() {
		if ( !empty( $this->description_or_callback ) && is_string( $this->description_or_callback ) ) {
			echo $this->description_or_callback;
		}
	}
}

class ICF_SettingsPage_Section_Component extends ICF_Component_Abstract {
	public $title;

	protected $_id;

	protected $_page_slug;

	protected $_section_id;

	protected $_registered = false;

	/**
	 * Constructor
	 *
	 * @param    string $id
	 * @param    string $title
	 * @param    string $page_slug
	 * @param    string $section_id
	 */
	public function __construct( $id, $title = null, $page_slug = null, $section_id = null ) {
		parent::__construct();

		$this->_id = $id;
		$this->_page_slug = $page_slug;
		$this->_section_id = ( empty( $section_id ) && $section_id !== false ) ? 'default' : $section_id;

		$this->title = empty( $title ) ? $this->_id : $title;

		add_action( 'admin_menu', array( $this, 'register' ) );
	}

	/**
	 * Returns the ID
	 *
	 * @return    string
	 */
	public function get_id() {
		return $this->_id;
	}

	/**
	 * Returns the page slug
	 *
	 * @return    string
	 */
	public function get_page_slug() {
		return $this->_page_slug;
	}

	/**
	 * Returns the section id
	 *
	 * @return    string
	 */
	public function get_section_id() {
		return $this->_section_id;
	}

	/**
	 * Registers to system
	 */
	public function register() {
		if ( $this->_page_slug && $this->_section_id ) {
			add_settings_field( $this->_id, $this->title, array( $this, 'display' ), $this->_page_slug, $this->_section_id );
		}

		foreach ( $this->_elements as $element ) {
			if ( is_subclass_of( $element, 'ICF_SettingsPage_Section_Component_Element_FormField_Abstract' ) ) {
				$element->register();
			}
		}

		$this->_registered = true;
	}

	public function render() {
		if ( !$this->_registered ) {
			$this->register();
		}

		return parent::render();
	}
}

class ICF_SettingsPage_Section_Component_Element_Value extends ICF_Component_Element_Abstract {
	protected $_name;

	protected $_default;

	public function __construct( ICF_SettingsPage_Section_Component $component, $name, $default = null ) {
		$this->_name = $name;
		parent::__construct( $component );
	}

	public function render() {
		return get_option( $this->_name, $this->_default );
	}
}

abstract class ICF_SettingsPage_Section_Component_Element_FormField_Abstract extends ICF_Component_Element_FormField_Abstract {
	protected $_stored_value = false;

	public function __construct( ICF_SettingsPage_Section_Component $component, $name, $value = null, array $args = array() ) {
		parent::__construct( $component, $name, $value, $args );
	}

	public function register() {
		if ( $this->_component->get_page_slug() ) {
		register_setting( $this->_component->get_page_slug(), $this->_name );
		}

		if ( get_option( $this->_name ) === false && $this->_value ) {
			update_option( $this->_name, $this->_value );
		}
	}

	public function initialize() {
		parent::initialize();

		if ( in_array( 'chkrequired', $this->_validation ) ) {
			$required_mark = '<span style="color: #B00C0C;">*</span>';

			if ( !preg_match( '|' . preg_quote( $required_mark ) . '$|', $this->_component->title ) ) {
				$this->_component->title .= ' ' . $required_mark;
			}
		}
	}

	public function before_render() {
		$value = get_option( $this->_name, false );

		if ( $value !== false ) {
			$this->_stored_value = $value;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Text extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract {
	public function before_render() {
		parent::before_render();

		if ( $this->_stored_value !== false ) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Password extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract {
	public function before_render() {
		parent::before_render();

		if ( $this->_stored_value !== false ) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Hidden extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract {
	public function before_render() {
		parent::before_render();

		if ( $this->_stored_value !== false ) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Textarea extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract {
	public function before_render() {
		parent::before_render();

		if ( $this->_stored_value !== false ) {
			$this->_value = $this->_stored_value;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Checkbox extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract {
	public function register() {
		register_setting( $this->_component->get_page_slug(), $this->_name );

		if ( get_option( $this->_name ) === false && $this->_value && !empty( $this->_args['checked'] ) ) {
			update_option( $this->_name, $this->_value );
		}
	}

	public function before_render() {
		parent::before_render();

		if ( $this->_stored_value !== false ) {
			unset( $this->_args['checked'], $this->_args['selected'] );
			$this->_args['checked'] = ( $this->_stored_value == $this->_value );
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Radio extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract {
	public function register() {
		register_setting( $this->_component->get_page_slug(), $this->_name );

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
		parent::before_render();

		if ( $this->_stored_value !== false ) {
			unset( $this->_args['checked'], $this->_args['selected'] );
			$this->_args['checked'] = in_array( $this->_stored_value, (array)$this->_value ) ? $this->_stored_value : false;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Select extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract {
	public function register() {
		register_setting( $this->_component->get_page_slug(), $this->_name );

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
		parent::before_render();

		if ( $this->_stored_value !== false ) {
			unset( $this->_args['checked'], $this->_args['selected'] );
			$this->_args['selected'] = in_array( $this->_stored_value, (array)$this->_value ) ? $this->_stored_value : false;
		}
	}
}

class ICF_SettingsPage_Section_Component_Element_FormField_Wysiwyg extends ICF_SettingsPage_Section_Component_Element_FormField_Abstract {
	public function initialize() {
		parent::initialize();

		if ( !isset( $this->_args['settings'] ) ) {
			$this->_args['settings'] = array();
		}

		$this->_args['id'] = $this->_name;
	}

	public function before_render() {
		parent::before_render();

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

class ICF_SettingsPage_Section_Component_Element_FormField_Visual extends ICF_SettingsPage_Section_Component_Element_FormField_Wysiwyg {
}