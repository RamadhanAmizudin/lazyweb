<?php
require_once __DIR__ . '/init.php';

if( !isAuth() ) {
	redirect('login.php');
}

if( get('delete') ) {
	$mysqli->query('DELETE FROM tbl_sites WHERE id = ' . intval(get('id')));
	redirect('add-site.php');
}

if( get('ping') ) {
	$site = $mysqli->query("SELECT * FROM tbl_sites WHERE id = " . intval(get('id')))->fetch_object()->url;
	$cmd = "time curl -I " . $site;
	$out = shell_exec($cmd);
	$smarty->assign('cmdout', nl2br($out));
}

if(isPost()) {
	$mysqli->query("INSERT INTO tbl_sites (user_id, url) VALUES ('" . cookie('user_id') . "', '" . post('url') . "')");
}

$query = $mysqli->query('SELECT * FROM tbl_sites WHERE user_id = ' . cookie('user_id'));
$sites = [];

if( !empty($mysqli->error) ) {
	die($mysqli->error);
}

while($qd = $query->fetch_assoc()) {
	$sites[] = $qd;
}

$data = [
	'total_sites' => $query->num_rows,
	'sites' => $sites
];


$smarty->assign('data', $data);
$smarty->display('user/add-site.php');
