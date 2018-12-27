<?php
require __DIR__ .'/config.php';
require __DIR__ .'/libs/common.php';
require __DIR__ .'/libs/AumWAF.class.php';
require __DIR__ .'/libs/smarty/Smarty.class.php';

$smarty = new Smarty;
$smarty->setTemplateDir(dirname(__FILE__) . '/templates/');
$smarty->setCompileDir(dirname(__FILE__) . '/templates_c/');
$smarty->assign('cfg', $_CONFIG);

$mysqli = new mysqli($_CONFIG['database']['host'], $_CONFIG['database']['user'], $_CONFIG['database']['pass'], $_CONFIG['database']['dbnm']);

if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error);
}

function getUsernameById($id) {
	global $mysqli;
	return $mysqli->query("SELECT username FROM tbl_users WHERE id = " . $id)->fetch_object()->username;
}

function getIdByUsername($username) {
	global $mysqli;
	return $mysqli->query("SELECT id FROM tbl_users WHERE username = '" . $username . "'")->fetch_object()->id;
}

if (strlen(session_id()) < 1) {
    session_start();
}


(new AumWAF)
	->initialize()
	->prepare($_GET, $_POST, $_COOKIE, $_SERVER, $_REQUEST)
	->detect()
	->block();
