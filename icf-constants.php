<?php
/**
 * Inspire Custom field Framework (ICF)
 *
 * @package        ICF
 * @author         Masayuki Ietomi <jyokyoku@gmail.com>
 * @copyright      Copyright(c) 2011 Masayuki Ietomi
 * @link           http://inspire-tech.jp
 */

if ( !defined( 'ICF_DS' ) ) {
	define( 'ICF_DS', DIRECTORY_SEPARATOR );
}

if ( !defined( 'ICF_TMPL_URI' ) ) {
	define( 'ICF_TMPL_URI', get_template_directory_uri() );
}

if ( !defined( 'ICF_SS_URI' ) ) {
	define( 'ICF_SS_URI', get_stylesheet_directory_uri() );
}

if ( !defined( 'ICF_SECOND' ) ) {
	define( 'ICF_SECOND', 1 );
}

if ( !defined( 'ICF_MINUTE' ) ) {
	define( 'ICF_MINUTE', ICF_SECOND * 60 );
}

if ( !defined( 'ICF_HOUR' ) ) {
	define( 'ICF_HOUR', ICF_MINUTE * 60 );
}

if ( !defined( 'ICF_DAY' ) ) {
	define( 'ICF_DAY', ICF_HOUR * 24 );
}

if ( !defined( 'ICF_WEEK' ) ) {
	define( 'ICF_WEEK', ICF_DAY * 7 );
}

if ( !defined( 'ICF_MONTH' ) ) {
	define( 'ICF_MONTH', ICF_DAY * 30 );
}

if ( !defined( 'ICF_YEAR' ) ) {
	define( 'ICF_YEAR', ICF_DAY * 365 );
}