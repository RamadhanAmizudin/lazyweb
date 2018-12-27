<?php
require_once __DIR__ . '/init.php';

if( isAuth() ) {
	redirect('index.php');
}

$error = false;

if( isPost() ) {
	if( !empty( post('email') ) AND !empty( post('password') ) ) {
		$email = post('email');
		$password = post('password');

		$sql = sprintf("SELECT * FROM tbl_users WHERE useremail = '%s'", $email);
		$q = $mysqli->query($sql);
		if( $q->num_rows > 0 ) {
			$data = $q->fetch_assoc();
			if( $_CONFIG['debug'] ) {
				print_r($data);
			}
			if( verifypw( $password, $data['userpass'] ) ) {
				$_SESSION = $data;
				$_SESSION['auth'] = true;
				foreach($data as $key => $val) {
					if( $key != 'userpass' ) {
						setcookie("user_" . $key, $val);
					}
				}
				setcookie("user_auth", (string)true);
				redirect('index.php');
			} else {
				$error = 'Invalid Password';
			}
		} else {
			$error = "User Email doesn't exists.";
		}
	} else {
		$error = "All field is required.";
	}
}

$smarty->assign('error', $error);
$smarty->display('user/login.php');
