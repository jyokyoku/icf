<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package		ICF
 * @author		Masayuki Ietomi
 * @copyright	Copyright(c) 2011 Masayuki Ietomi
 */

$GLOBALS['icf_versions']['0.2'] = __FILE__;

if (!defined('ICF_DEBUG')) {
	define('ICF_DEBUG', false);
}

if (!class_exists('ICF_Loader')) {
	class ICF_Loader
	{
		protected static $_loaded_files = array();
		protected static $_loaded = false;

		/**
		 * Initialize
		 *
		 * @param	mixed	$callback
		 */
		public static function init($callback = '')
		{
			if ($callback) {
				foreach ((array)$callback as $_callback) {
					add_action('icf_loaded', $_callback, 10, 1);
				}
			}

			add_action('after_setup_theme', array('ICF_Loader', 'load'));
		}

		/**
		 * Load the class files
		 */
		public static function load()
		{
			if (self::$_loaded) {
				return;
			}

			$base_dir = self::get_latest_version_dir();
			load_textdomain('icf', $base_dir . '/languages/icf-' . get_locale() . '.mo');

			if ($dh = opendir($base_dir)) {
				while (false !== ($file = readdir($dh))) {
					if ($file === '.' || $file === '..' || $file[0] === '.' || strrpos($file, '.php') === false) {
						continue;
					}

					$filepath = $base_dir . '/' . $file;

					if (is_readable($filepath) && include_once $filepath) {
						self::$_loaded_files[] = $filepath;
					}
				}

				closedir($dh);
			}

			do_action('icf_loaded', self::$_loaded_files);

			self::$_loaded = true;
		}

		/**
		 * Returns the latest version directory path of ICF
		 *
		 * @return	NULL|string
		 */
		public static function get_latest_version_dir()
		{
			$latest = null;

			foreach (array_keys($GLOBALS['icf_versions']) as $version) {
				if (!$latest) {
					$latest = $version;
					continue;
				}

				if (version_compare($version, $latest) > 0) {
					$latest = $version;
				}
			}

			if (is_null($latest)) {
				return null;
			}

			return dirname($GLOBALS['icf_versions'][$latest]);
		}

		/**
		 * Returns the latest version url of ICF
		 *
		 * @return	NULL|string
		 */
		public static function get_latest_version_url()
		{
			return get_option('siteurl') . '/' . str_replace(ABSPATH, '', self::get_latest_version_dir());
		}

		/**
		 * Enqueues a JavaScript set
		 */
		public static function register_javascript(array $queue = array())
		{
			wp_enqueue_script('media-upload');
			wp_enqueue_script('thickbox');

			if (version_compare(get_bloginfo('version'), '3.3', '>=')) {
				wp_enqueue_script('wplink');
				wp_enqueue_script('wpdialogs-popup');
				wp_enqueue_script('icf-active-editor', self::get_latest_version_url() . '/js/active_editor.js', array('jquery'), null, true);
				wp_enqueue_script('icf-quicktags', self::get_latest_version_url() . '/js/quicktags.js', array('quicktags'), null, true);
			}

			if (!wp_script_is('icf-mobiscroll', 'registered')) {
				wp_enqueue_script('icf-mobiscroll', self::get_latest_version_url() . '/js/mobiscroll/mobiscroll-1.6.min.js', array('jquery'));
			}

			if (!wp_script_is('icf-exvalidaion', 'registered')) {
				wp_enqueue_script('icf-exvalidation', self::get_latest_version_url() . '/js/exvalidation/exvalidation.js', array('jquery'));
			}

			if (!wp_script_is('icf-exchecker', 'registered')) {
				$exchecker = 'exchecker-' . get_locale() . '.js';

				if (!is_readable(self::get_latest_version_dir() . '/js/exvalidation/' . $exchecker)) {
					$exchecker = 'exchecker-en_US.min.js';
				}

				wp_enqueue_script('icf-exchecker', self::get_latest_version_url() . '/js/exvalidation/' . $exchecker, array('jquery'));
			}

			if (!wp_script_is('icf-common', 'registered')) {
				$assoc = array('jquery', 'media-upload', 'thickbox', 'icf-exchecker', 'icf-mobiscroll');

				wp_enqueue_script('icf-common', self::get_latest_version_url() . '/js/common.js', $assoc, null, true);
				wp_localize_script('icf-common', 'icfCommonL10n', array(
					'insertToField' => __('Insert to field', 'icf'),
					'cancelText' => __('Cancel', 'icf'),
					'dateFormat' => __('mm/dd/yy', 'icf'),
					'dateOrder' => __('mmddy', 'icf'),
					'sunday' => __('Sunday', 'icf'),
					'monday' => __('Monday', 'icf'),
					'tuesday' => __('Tuesday', 'icf'),
					'wednesday' => __('Wednesday', 'icf'),
					'thursday' => __('Thursday', 'icf'),
					'friday' => __('Friday', 'icf'),
					'saturday' => __('Saturday', 'icf'),
					'sundayShort' => __('Sun', 'icf'),
					'mondayShort' => __('Mon', 'icf'),
					'tuesdayShort' => __('Tue', 'icf'),
					'wednesdayShort' => __('Wed', 'icf'),
					'thursdayShort' => __('Thu', 'icf'),
					'fridayShort' => __('Fri', 'icf'),
					'saturdayShort' => __('Sat', 'icf'),
					'dayText' => __('Day', 'icf'),
					'hourText' => __('Hours', 'icf'),
					'minuteText' => __('Minutes', 'icf'),
					'january' => __('January', 'icf'),
					'february' => __('February', 'icf'),
					'march' => __('March', 'icf'),
					'april' => __('April', 'icf'),
					'may' => _x('May', 'long', 'icf'),
					'june' => __('June', 'icf'),
					'july' => __('July', 'icf'),
					'august' => __('August', 'icf'),
					'september' => __('September', 'icf'),
					'october' => __('October', 'icf'),
					'november' => __('November', 'icf'),
					'december' => __('December', 'icf'),
					'januaryShort' => __('Jan', 'icf'),
					'februaryShort' => __('Feb', 'icf'),
					'marchShort' => __('Mar', 'icf'),
					'aprilShort' => __('Apr', 'icf'),
					'mayShort' => _x('May', 'short', 'icf'),
					'juneShort' => __('Jun', 'icf'),
					'julyShort' => __('Jul', 'icf'),
					'augustShort' => __('Aug', 'icf'),
					'septemberShort' => __('Sep', 'icf'),
					'octoberShort' => __('Oct', 'icf'),
					'november' => __('Nov', 'icf'),
					'decemberShort' => __('Dec', 'icf'),
					'monthText' => __('Month', 'icf'),
					'secText' => __('Seconds', 'icf'),
					'setText' => __('Set', 'icf'),
					'timeFormat' => __('hh:ii A', 'icf'),
					'yearText' => __('Year', 'icf')
				));
			}

			self::_enqueue($queue, 'script');
		}

		/**
		 * Enqueues a CSS set
		 */
		public static function register_css(array $queue = array())
		{
			wp_enqueue_style('thickbox');
			wp_enqueue_style('editor-buttons');

			if (version_compare(get_bloginfo('version'), '3.3', '>=')) {
				wp_enqueue_style('wp-jquery-ui-dialog');
			}

			if (!wp_style_is('icf-mobiscroll', 'registered')) {
				wp_enqueue_style('icf-mobiscroll', ICF_Loader::get_latest_version_url() . '/js/mobiscroll/mobiscroll-1.6.min.css');
			}

			if (!wp_style_is('icf-exvalidation', 'registered')) {
				wp_enqueue_style('icf-exvalidation', ICF_Loader::get_latest_version_url() . '/js/exvalidation/exvalidation.css');
			}

			self::_enqueue($queue, 'style');
		}

		/**
		 * Enqueue a script/style
		 *
		 * @param	array	$queue
		 * @param	string	$type
		 * @return	boolean
		 */
		protected static function _enqueue(array $queue, $type = 'script')
		{
			$check_func = 'wp_' . $type . '_is';
			$register_func = 'wp_enqueue_' . $type;

			if (!function_exists($check_func) || !function_exists($register_func)) {
				return false;
			}

			foreach ($queue as $handler => $params) {
				if (is_int($handler)) {
					if (is_string($params)) {
						$handler = $params;
						$params = array();

					} else {
						continue;
					}
				}

				if (!is_array($params)) {
					$params = array($params);
				}

				if (!$check_func($handler, 'registered')) {
					array_unshift($params, $handler);
					call_user_func_array($register_func, $params);
				}
			}

			return true;
		}
	}
}
