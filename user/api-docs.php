<?php
require_once __DIR__ . '/init.php';

if( !isAuth() ) {
	redirect('login.php');
}

$xml = htmlspecialchars("        <creds>
            <user>Ed</user>
            <pass>mypass</pass>
        </creds>");
$smarty->assign('xml', $xml);
$smarty->display('user/api-docs.php');
