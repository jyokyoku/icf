<?php
$icf_dir = str_replace('\\', '/', __DIR__);

if (($pos = strrpos($icf_dir, 'icf')) === false) {
	exit();
}

$icf_dir = rtrim(substr($icf_dir, 0, $pos), '/') . '/icf';
$icf_loader = $icf_dir. '/icf-loader.php';

if (!is_file($icf_loader) || !is_readable($icf_loader)) {
	exit();
}

$loaded = false;
$i = 0;
$search_depth = 3;
$config_dir = $icf_dir;

while ($config_dir !== '/' && count($config_dir) > 0 && $i < $search_depth) {
	$config_dir = dirname($config_dir);
	$config = $config_dir . '/timthumb-config.php';

	if (is_file($config) && is_readable($config)) {
		include_once $config;
		$loaded = true;
		break;
	}

	$i++;
}

if (!$loaded && !defined('FILE_CACHE_DIRECTORY')) {
	if (($pos = strrpos($icf_dir, 'wp-content')) !== false) {
		$content_dirs[] = rtrim(substr($icf_dir, 0, $pos), '/') . '/wp-content';
	}

	$content_dirs[] = dirname($icf_dir);

	foreach ($content_dirs as $content_dir) {
		if (is_dir($content_dir) && is_writable($content_dir)) {
			define('FILE_CACHE_DIRECTORY', $content_dir . '/timthumb-cache');
			break;
		}
	}
}