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
// { pre sort and uniq
$cmd='sort data/LTC-'.$currency.'-historic | uniq > data/LTC-'.$currency.'-historic.tmp && mv data/LTC-'.$currency.'-historic.tmp data/LTC-'.$currency.'-historic';
`$cmd`;
// }
$l=`tail -1 data/LTC-$currency-historic`;
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
	$data=$client->getProductHistoricRates('LTC-'.$currency, [
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
			file_put_contents('data/LTC-'.$currency.'-historic', json_encode($d)."\n", FILE_APPEND);
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
echo "sorting and discarding duplicates\n";
$cmd='sort data/LTC-'.$currency.'-historic | uniq > data/LTC-'.$currency.'-historic.tmp && mv data/LTC-'.$currency.'-historic.tmp data/LTC-'.$currency.'-historic';
`$cmd`;
echo "filling in gaps\n";
$lines=file('data/LTC-'.$currency.'-historic');
$numLines=count($lines);
$newLines=[];
$line=json_decode($lines[0], true);
$newLines[]=trim($lines[0]);
$at=$line[0];
$lastline=$line;
for ($i=1; $i<$numLines; ++$i) {
	$line=json_decode($lines[$i], true);
	if ($line[0]==$at) { // duplicate time stamp
		continue;
	}
	for ($j=$at+60; $j<$line[0]; $j+=60) { // fill in the minutes where nothing happened
		$line2=$lastline;
		$line2[0]=$j;
		$line2=json_encode($line2);
		$newLines[]=$line2;
	}
	$at=$line[0];
	$lastline=$line;
	$line=json_encode($line);
	$newLines[]=$line;
}
file_put_contents('data/LTC-'.$currency.'-historic', join("\n", $newLines));
