<?php
require_once 'lib.php';
define('TEST', false);

try {
	$products=$client->getProducts();
	$productsById=[];
	foreach ($products as $pro) {
		$productsById[$pro['id']]=$pro;
	}
}
catch(Exception $e) {
	echo $e->getMessage();
	exit;
}
$l=`tail -1 data/LTC-EUR-historic`;
$from=0;
if ($l) {
	$l=json_decode(trim($l), true);
	if ($l && isset($l[0]) && $l[0]) {
		$from=$l[0]+1;
	}
}
if (!$from) {
	$from=strtotime('2017-06-01 00:00:00');
}
do {
	$data=$client->getProductHistoricRates('LTC-EUR', [
		'start'=>date('c', $from),
		'end'=>date('c', $from+3601),
		'granularity'=>'60'
	]);
	$data=array_reverse($data);
	if (count($data)) {
		$fromChanged=0;
		foreach ($data as $d) {
			if ($d[0]>$from) {
				$fromChanged=1;
				$from=$d[0];
			}
			echo date('c', $d[0]).' '.json_encode($d)."\n";
			file_put_contents('data/LTC-EUR-historic', json_encode($d)."\n", FILE_APPEND);
		}
		if ($fromChanged) {
			$from+=1;
		}
		else {
			$from+=3600;
		}
	}
	else {
		$from+=3600;
	}
	sleep(1);
} while($from<time()-3600);
