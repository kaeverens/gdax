<?php
define('TEST', false);
require_once 'lib.php';

do {
	$ret=runOne();
	echo $ret['str'];
	if ($ret['block'][10]) {
		echo $ret['block'][10]."\n";
	}
	file_put_contents('data/holdings.tsv', $ret['report'], FILE_APPEND);
	sleep(60);
} while (1);
