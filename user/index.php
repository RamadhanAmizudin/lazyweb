<?php
require_once __DIR__ . '/init.php';

if( !isAuth() ) {
	redirect('login.php');
}

if( !empty( get('user_id') ) ) {
	$user_id = get('user_id');
} elseif( !empty( cookie('user_id') ) ) {
	$user_id = cookie('user_id');
}

print $user_id;

$query = $mysqli->query('SELECT * FROM tbl_sites WHERE user_id = ' . $user_id);
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
$smarty->display('user/index.php');
