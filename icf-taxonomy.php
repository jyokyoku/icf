<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

require_once dirname(__FILE__) . '/icf-loader.php';
require_once dirname(__FILE__) . '/icf-inflector.php';

class ICF_Taxonomy
{
	protected $_slug;
	protected $_post_type;
	protected $_args = array();
	protected $_components = array();

	public function __construct($slug, $post_type, $args = array())
	{
		global $wp_taxonomies;

		$this->_slug = $slug;
		$this->_post_type = $post_type;
		$this->_args = wp_parse_args($args);

		if (!isset($wp_taxonomies[$this->_slug])) {
			if (empty($this->_args['label'])) {
				$this->_args['label'] = $this->_slug;
			}

			if (empty($this->_args['labels'])) {
				$this->_args['labels'] = array(
					'name' => ICF_Inflector::humanize($this->_pluralize($this->_args['label'])),
					'singular_name' => ICF_Inflector::humanize($this->_singularize($this->_args['label'])),
					'search_items' => sprintf(__('Search %s', 'icf'), ICF_Inflector::humanize($this->_singularize($this->_args['label']))),
					'popular_items' => sprintf(__('Popular %s', 'icf'), ICF_Inflector::humanize($this->_pluralize($this->_args['label']))),
					'all_items' => sprintf(__('All %s', 'icf'), ICF_Inflector::humanize($this->_pluralize($this->_args['label']))),
					'parent_item' => sprintf(__('Parent %s', 'icf'), ICF_Inflector::humanize($this->_singularize($this->_args['label']))),
					'parent_item_colon' => sprintf(__('Parent %s:', 'icf'), ICF_Inflector::humanize($this->_singularize($this->_args['label']))),
					'edit_item' => sprintf(__('Edit %s', 'icf'), ICF_Inflector::humanize($this->_singularize($this->_args['label']))),
					'view_item' => sprintf(__('View %s', 'icf'), ICF_Inflector::humanize($this->_singularize($this->_args['label']))),
					'update_item' => sprintf(__('Update %s', 'icf'), ICF_Inflector::humanize($this->_singularize($this->_args['label']))),
					'add_new_item' => sprintf(__('Add New %s', 'icf'), ICF_Inflector::humanize($this->_singularize($this->_args['label']))),
					'new_item_name' => sprintf(__('New %s Name', 'icf'), ICF_Inflector::humanize($this->_singularize($this->_args['label']))),
					'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'icf'), strtolower($this->_pluralize($this->_args['label']))),
					'add_or_remove_items' => sprintf(__('Add or remove %s', 'icf'), strtolower($this->_pluralize($this->_args['label']))),
					'choose_from_most_used' => sprintf(__('Choose from the most used %s', 'icf'), strtolower($this->_pluralize($this->_args['label']))),
				);
			}

			register_taxonomy($this->_slug, $this->_post_type, $this->_args);

		} else {
			register_taxonomy_for_object_type($this->_slug, $this->_post_type);
		}

		add_action('edited_' . $this->_slug, array($this, 'save'), 10, 2);
		add_action('created_' . $this->_slug, array($this, 'save'), 10, 2);
		add_action($this->_slug . '_add_form_fields', array($this, 'display_add_form'), 10, 1);
		add_action($this->_slug . '_edit_form_fields', array($this, 'display_edit_form'), 10, 2);

		if (!has_action('admin_init', array('ICF_Taxonomy', 'load_wpeditor_html'))) {
			add_action('admin_init', array('ICF_Taxonomy', 'load_wpeditor_html'), 10);
		}

		if (!has_action('admin_head', array('ICF_Taxonomy', 'add_local_style'))) {
			add_action('admin_head', array('ICF_Taxonomy', 'add_local_style'), 10);
		}

		if (!has_action('admin_print_scripts', array('ICF_Taxonomy', 'add_scripts'))) {
			add_action('admin_print_scripts', array('ICF_Taxonomy', 'add_scripts'), 10);
		}

		if (!has_action('admin_print_styles', array('ICF_Taxonomy', 'admin_print_styles'))) {
			add_action('admin_print_styles', array('ICF_Taxonomy', 'add_styles'), 10);
		}
	}

	public function get_slug()
	{
		return $this->_slug;
	}

	public function get_post_type()
	{
		return $this->_post_type;
	}

	public function component($id, $title = null)
	{
		if (is_object($id) && is_a($id, 'ICF_Taxonomy_Component')) {
			$component = $id;
			$id = $component->get_id();

			if (isset($this->_components[$id])) {
				if ($this->_components[$id] !== $component) {
					$this->_components[$id] = $component;
				}

				return $component;
			}

		} else if (isset($this->_components[$id])) {
			return $this->_components[$id];

		} else {
			$component = new ICF_Taxonomy_Component($this, $id, $title);
		}

		$this->_components[$id] = $component;

		return $component;
	}

	public function c($id, $title = null)
	{
		return $this->component($id, $title);
	}

	public function save($term_id, $tt_id)
	{
		$option_key = self::get_option_key($term_id, $this->_slug);
		$values = get_option($option_key);

		if (!is_array($values)) {
			$values = array();
		}

		foreach ($this->_components as $component) {
			$component->save($values, $term_id, $tt_id);
		}

		update_option($option_key, $values);
	}

	public function display_add_form($taxonomy)
	{
		$html = '';

		foreach ($this->_components as $component) {
			$label = ICF_Tag::create('label', null, $component->title);
			$body  = $component->render();
			$html .= ICF_Tag::create('div', array('class' => 'form-field'), $label . "\n" . $body);
		}

		echo $html;
	}

	public function display_edit_form(stdClass $tag, $taxonomy)
	{
		$html = '';

		foreach ($this->_components as $component) {
			$th = ICF_Tag::create('th', array('scope' => 'row', 'valign' => 'top'), $component->title);
			$td = ICF_Tag::create('td', null, $component->render($tag));
			$html .= ICF_Tag::create('tr', array('class' => 'form-field'), $th . "\n" . $td);
		}

		echo $html;
	}

	public static function add_local_style()
	{
		global $pagenow;

		if ($pagenow == 'edit-tags.php') {
?>
<style type="text/css">
.form-field input[type=button],
.form-field input[type=submit],
.form-field input[type=reset],
.form-field input[type=radio],
.form-field input[type=checkbox] {
	width: auto;
}

.form-field .wp-editor-wrap textarea {
	border: none;
	width: 99.5%;
}

.form-wrap label {
	display: inline;
}

.form-wrap label:first-child {
	display: block;
}
</style>
<?php
		}
	}

	public static function add_scripts()
	{
		global $pagenow;

		if ($pagenow == 'edit-tags.php') {
			ICF_Loader::register_javascript();
		}
	}

	public static function add_styles()
	{
		global $pagenow;

		if ($pagenow == 'edit-tags.php') {
			ICF_Loader::register_css();
		}
	}

	public static function load_wpeditor_html()
	{
		global $pagenow;

		if ($pagenow == 'edit-tags.php' && !has_action('admin_print_footer_scripts', array('ICF_Loader', 'load_wpeditor_html'))) {
			add_action('admin_print_footer_scripts', array('ICF_Loader', 'load_wpeditor_html'));
		}
	}

	public static function get_option_key($term_id, $taxonomy)
	{
		return 'term_meta_' . $taxonomy . '_' . $term_id;
	}

	public static function get_option($term_id, $taxonomy, $key, $default = false)
	{
		$values = get_option(self::get_option_key($term_id, $taxonomy), false);

		if ($values === false || !is_array($values) || !isset($values[$key])) {
			return $default;
		}

		return stripslashes_deep($values[$key]);
	}

	protected function _pluralize($text)
	{
		return preg_match('/[a-zA-Z]$/', $text) ? ICF_Inflector::pluralize($text) : $text;
	}

	protected function _singularize($text)
	{
		return preg_match('/[a-zA-Z]$/', $text) ? ICF_Inflector::singularize($text) : $text;
	}
}

class ICF_Taxonomy_Component extends ICF_Component
{
	public $title;

	protected $_id;
	protected $_taxonomy;

	public function __construct(ICF_Taxonomy $taxonomy, $id, $title = null)
	{
		parent::__construct();

		$this->_id = $id;
		$this->_taxonomy = $taxonomy;

		$this->title = empty($title) ? $this->_id : $title;
	}

	public function get_taxonomy()
	{
		return $this->_taxonomy;
	}

	public function get_id()
	{
		return $this->_id;
	}

	public function save(array &$values, $term_id, $tt_id)
	{
		foreach ($this->_elements as $element) {
			if (is_subclass_of($element, 'ICF_Taxonomy_Component_Element_FormField_Abstract')) {
				$element->save($values, $term_id, $tt_id);
			}
		}
	}
}

class ICF_Taxonomy_Component_Element_FormField_Abstract extends ICF_Component_Element_FormField_Abstract
{
	public function __construct(ICF_Taxonomy_Component $component, $name, $value = null, array $args = array())
	{
		parent::__construct($component, $name, $value, $args);
	}

	public function save(array &$values, $term_id, $tt_id)
	{
		if (!isset($_POST[$this->_name])) {
			return false;
		}

		$values[$this->_name] = $_POST[$this->_name];

		return true;
	}

	public function before_render(stdClass $tag = null)
	{
		if ($tag && !empty($tag->term_id)) {
			$value = ICF_Taxonomy::get_option($tag->term_id, $this->_component->get_taxonomy()->get_slug(), $this->_name);

			if ($value !== false) {
				$this->_value = $value;
			}
		}
	}

	public function render(stdClass $tag = null)
	{
		if (!method_exists('ICF_Form', $this->_type)) {
			return '';
		}

		return call_user_func(array('ICF_Form', $this->_type), $this->_name, $this->_value, $this->_args);
	}
}

class ICF_Taxonomy_Component_Element_FormField_Text extends ICF_Taxonomy_Component_Element_FormField_Abstract
{
}

class ICF_Taxonomy_Component_Element_FormField_Textarea extends ICF_Taxonomy_Component_Element_FormField_Abstract
{
}

class ICF_Taxonomy_Component_Element_FormField_Checkbox extends ICF_Taxonomy_Component_Element_FormField_Abstract
{
	public function before_render(stdClass $tag = null)
	{
		if ($tag && !empty($tag->term_id)) {
			$value = ICF_Taxonomy::get_option($tag->term_id, $this->_component->get_taxonomy()->get_slug(), $this->_name);

			if ($value !== false) {
				unset($this->_args['checked'], $this->_args['selected']);
				$this->_args['checked'] = ($value == $this->_value);
			}
		}
	}
}

class ICF_Taxonomy_Component_Element_FormField_Radio extends ICF_Taxonomy_Component_Element_FormField_Abstract
{
	public function before_render(stdClass $tag = null)
	{
		if ($tag && !empty($tag->term_id)) {
			$value = ICF_Taxonomy::get_option($tag->term_id, $this->_component->get_taxonomy()->get_slug(), $this->_name);

			if ($value !== false) {
				unset($this->_args['checked'], $this->_args['selected']);
				$this->_args['checked'] = in_array($value, (array)$this->_value) ? $value : false;
			}
		}
	}
}

class ICF_Taxonomy_Component_Element_FormField_Select extends ICF_Taxonomy_Component_Element_FormField_Abstract
{
	public function before_render(stdClass $tag = null)
	{
		if ($tag && !empty($tag->term_id)) {
			$value = ICF_Taxonomy::get_option($tag->term_id, $this->_component->get_taxonomy()->get_slug(), $this->_name);

			if ($value !== false) {
				unset($this->_args['checked'], $this->_args['selected']);
				$this->_args['selected'] = in_array($value, (array)$this->_value) ? $value : false;
			}
		}
	}
}

class ICF_Taxonomy_Component_Element_FormField_Wysiwyg extends ICF_Taxonomy_Component_Element_FormField_Abstract
{
	public function initialize()
	{
		parent::initialize();

		if (!isset($this->_args['settings'])) {
			$this->_args['settings'] = array();
		}

		$this->_args['id'] = $this->_name;
	}

	public function render()
	{
		$editor = '';

		if (version_compare(get_bloginfo('version'), '3.3', '>=') && function_exists('wp_editor')) {
			ob_start();
			wp_editor($this->_value, $this->_args['id'], $this->_args['settings']);
			$editor = ob_get_clean();

		} else {
			trigger_error('The TinyMCE has been required for the WordPress 3.3 or above');
		}

		return $editor;
	}
}