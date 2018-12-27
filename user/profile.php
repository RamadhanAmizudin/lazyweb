<?php
require_once __DIR__ . '/init.php';

if( !isAuth() ) {
	redirect('login.php');
}

$error = false;

if( isPost() ) {
	if( !empty($_FILES["avatar"]['name']) ) {
		// doing file upload
		$target_file = __DIR__ . '/avatar/' . (int)cookie('user_id') . '.png';

		if( file_exists( $target_file ) ) {
			unlink($target_file);
		}

		move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file);
		redirect('profile.php');
	}

	if( !empty( post('username') ) || !empty('useremail')) {
		if(!empty(post('password'))) {
			$mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "', useremail='" . post('useremail') . "', userpass='" . pw(post('password')) . "' WHERE id = " . cookie('user_id'));
		} else {
			$mysqli->query("UPDATE tbl_users SET username = '" . post('username') . "', useremail='" . post('useremail') . "' WHERE id = " . cookie('user_id'));
		}
		redirect('logout.php');
	}
}

$q = $mysqli->query('SELECT * FROM tbl_users WHERE id = ' . cookie('user_id'));
$data = $q->fetch_assoc();
$smarty->assign('data', $data);
$smarty->display('user/profile.php');
