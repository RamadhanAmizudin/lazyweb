<?php
require_once __DIR__ . '/init.php';

if( !isAuth() ) {
	redirect('login.php');
}

if(isPost()) {
	if(!empty(post('to')) && !empty(post('subject')) && !empty(post('message'))) {
		$sql = sprintf("INSERT INTO tbl_support (user_id, to_id, subject, message) values ('%d', '%d', '%s', '%s')", cookie('user_id'), getIdByUsername(post('to')), post('subject'), post('message'));
		$mysqli->query($sql);
		#print $sql;
		redirect('inbox.php');
	}
}

$error = false;

$q = $mysqli->query('SELECT * FROM tbl_support WHERE to_id = ' . cookie('user_id') . ' order by id desc');
$inbox = [];
while($qd = $q->fetch_assoc()) {
	$inbox[] = $qd;
}

$q = $mysqli->query('SELECT * FROM tbl_support WHERE user_id = ' . cookie('user_id') . ' order by id desc');
$sent = [];
while($qd = $q->fetch_assoc()) {
	$sent[] = $qd;
}

if(get('view_id')) {
	$q = $mysqli->query("SELECT * FROM tbl_support WHERE id = " . intval(get('view_id')));
	if($q->num_rows > 0) {
		$dmsg = $q->fetch_object();
		$msg = [];
		if($dmsg->to_id == cookie('user_id')) {
			$msg['from'] = getUsernameById($dmsg->user_id);
			$msg['to'] = getUsernameById($dmsg->to_id);
		} else {
			$msg['from'] = getUsernameById($dmsg->user_id);
			$msg['to'] = getUsernameById($dmsg->to_id);
		}
		$msg['message'] = $dmsg->message;
		$msg['subject'] = $dmsg->subject;
		$msg['created_at'] = $dmsg->created_at;
		$smarty->assign('msg', $msg);
	}
}

$smarty->assign('inbox', $inbox);
$smarty->assign('sent', $sent);
$smarty->display('user/inbox.php');
