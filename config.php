<?php

$_CONFIG = [
	'database' => [
		'host' => 'localhost',
		'user' => 'user',
		'pass' => 'snf1AvQ74DtF1PJp',
		'dbnm' => 'user'
	],
	'user' => [
		'encrypt_password' => true,
		'encrypt_algo' => PASSWORD_BCRYPT
	],
	'debug' => (!empty($_REQUEST['debug'])) ? true : false
];
