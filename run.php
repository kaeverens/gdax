<?php
define('TEST', false);
require_once 'lib.php';

$smaBuyLongMax=$smaBuyLong;
$smaSellLongMax=$smaSellLong;
$emaBuyLongMax=$emaBuyLong;
$emaSellLongMax=$emaSellLong;
$tradeHistory=[];
do {
	$ret=runOne();
	echo $ret['str'];
	if ($ret['block'][10]) {
		if (preg_match('/buy/i', $ret['block'][10])) {
			echo "\033[32m".$ret['block'][10]."\033[0m\n";
		}
		else {
			echo "\033[31m".$ret['block'][10]."\033[0m\n";
		}
	}
	file_put_contents('data/holdings.tsv', $ret['report'], FILE_APPEND);
	sleep(60);
} while (1);
