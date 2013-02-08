<?php
if (!defined('ICF_DS')) {
	define('ICF_DS', DIRECTORY_SEPARATOR);
}

if (!defined('ICF_SECOND')) {
	define('ICF_SECOND', 1);
}

if (!defined('ICF_MINUTE')) {
	define('ICF_MINUTE', ICF_SECOND * 60);
}

if (!defined('ICF_HOUR')) {
	define('ICF_HOUR', ICF_HOUR * 60);
}

if (!defined('ICF_DAY')) {
	define('ICF_DAY', ICF_HOUR * 24);
}

if (!defined('ICF_WEEK')) {
	define('ICF_WEEK', ICF_DAY * 7);
}

if (!defined('ICF_MONTH')) {
	define('ICF_MONTH', ICF_DAY * 30);
}

if (!defined('ICF_YEAR')) {
	define('ICF_YEAR', ICF_DAY * 365);
}