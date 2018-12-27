<?php
require_once __DIR__ . '/init.php';

if(	get('page') ) {
	$page = get('page');
	include $page . '.php';
} else {
	redirect('/');
}