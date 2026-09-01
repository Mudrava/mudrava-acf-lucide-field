<?php
/**
 * PHP built-in server router for local WordPress testing.
 */

$path = (string) parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = $_SERVER['DOCUMENT_ROOT'] . $path;

if ( '/' !== $path && is_file( $file ) ) {
	return false;
}

if ( '/' !== $path && is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) ) {
	return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
