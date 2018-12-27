<?php
require_once __DIR__ . '/init.php';

if( isAuth() ) {
	redirect('index.php');
}

$error = false;

if( isPost() ) {
	if( !empty( post('usermail') ) AND !empty( post('username') ) AND !empty( post('p1') ) AND !empty( post('p2') ) ) {
		$email = post('usermail');
		$username = post('username');
		$p1 = post('p1');
		$p2 = post('p2');
		if( $p1 != $p2 ) {
			$error = "Password confirmation doesn't match";
		} else {
			$q = $mysqli->query("SELECT * FROM tbl_users WHERE useremail = '" . $email . "'");
			if( $q->num_rows > 0 ) {
				$error = "Email exists in our database";
			} else {
				$sql = sprintf("INSERT INTO tbl_users (username, userpass, useremail) VALUES ('%s', '%s', '%s')", $username, pw($p2), $email);
				$qr = $mysqli->query($sql);
				if(!$qr) {
					$error = "unknown error";
				} else {
					redirect('login.php');
				}
			}
		}
	} else {
		$error = "All field is required.";
	}
}

$smarty->assign('error', $error);
$smarty->display('user/register.php');
