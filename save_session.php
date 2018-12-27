<?php
require __DIR__ . '/config.php';

if (strlen(session_id()) < 1) {
    session_start();
}

if( $_CONFIG['debug'] ) {
	header('Content-type: text/plain');
	foreach($_REQUEST as $key => $val) {
		$_SESSION[$key] = $val;
	}
	print_r($_SESSION);
}
