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
$dirname = $icf_dir;

while ($dirname !== '/' && count($dirname) > 0 && $i < $search_depth) {
	$dirname = dirname($dirname);
	$config = $dirname . '/timthumb-config.php';

	if (is_file($config) && is_readable($config)) {
		include_once $config;
		$loaded = true;
		break;
	}

	$i++;
}

if (!$loaded && !defined('FILE_CACHE_DIRECTORY')) {
	$dirname = dirname($icf_dir);
	define('FILE_CACHE_DIRECTORY', $dirname . '/cache');
}