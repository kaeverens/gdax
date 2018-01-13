<?php
define('TEST', true);
require_once 'lib.php';

$exportToFile=false;

$startupEur=100000;
$startupLtc=0; // $startupEur/1000;

$buyMin=99;
$buyMax=99;
$buyInc=1;

$sellMin=99;
$sellMax=99;
$sellInc=1;

$minBlocks=22;
$maxBlocks=22;

$volatilityMin=5.94;
$volatilityMax=5.94;
$volatilityInc=0.001;

$macdShortMin=14;
$macdShortMax=14;

$macdLongMin=25;
$macdLongMax=25;

// { setup data
$data=[];
$data['LTC-'.$currency]=file('data/LTC-'.$currency.'-historic');
foreach ($data['LTC-'.$currency] as $k=>$v) {
	$data['LTC-'.$currency][$k]=json_decode($v, true);
	if ($k) { // calculate True Range
		$m1=abs($data['LTC-'.$currency][$k][2]-$data['LTC-'.$currency][$k][1]);
		$m2=abs($data['LTC-'.$currency][$k][2]-$data['LTC-'.$currency][$k-1][4]);
		$m3=abs($data['LTC-'.$currency][$k][1]-$data['LTC-'.$currency][$k-1][4]);
		$data['LTC-'.$currency][$k][6]=max($m1, $m2, $m3); // TR
		$data['LTC-'.$currency][$k][7]=0; // will be ATR
		$data['LTC-'.$currency][$k][8]=0; // will be max highs
		$data['LTC-'.$currency][$k][9]=[0]; // will be avgs
	}
	if ($k>$macdLongMax) {
		for ($j=1;$j<=$macdLongMax;++$j) {
			$data['LTC-'.$currency][$k][9][$j]=($data['LTC-'.$currency][$k-1][9][$j]*($j-1)+$data['LTC-'.$currency][$k][3])/$j;
		}
	}
}
$best=[
	'holding'=>0
];
// }

for ($blocks=$minBlocks; $blocks<=$maxBlocks; ++$blocks) {
	// { recalculate ATRs
	$sum=0;
	for ($i=1;$i<$blocks+1;++$i) {
		$sum+=$data['LTC-'.$currency][$i][6];
	}
	$data['LTC-'.$currency][$i][7]=$sum/$blocks; // true ATR
	for ($i=$blocks+1;$i<count($data['LTC-'.$currency]);++$i) {
		$data['LTC-'.$currency][$i][7]=($data['LTC-'.$currency][$i-1][7]*($blocks-1)+$data['LTC-'.$currency][$i][6])/$blocks;
	}
	$highs=[];
	for ($i=0;$i<count($data['LTC-'.$currency]);++$i) {
		$highs[]=$data['LTC-'.$currency][$i][2];
		if (count($highs)>$blocks) {
			array_shift($highs);
		}
		$data['LTC-'.$currency][$i][8]=max($highs);
	}
	// }
	for ($percentToSell=$sellMin; $percentToSell<=$sellMax;$percentToSell+=$sellInc) {
		for ($percentToBuy=$buyMin; $percentToBuy<=$buyMax;$percentToBuy+=$buyInc) {
			for ($volatility=$volatilityMin; $volatility<=$volatilityMax; $volatility+=$volatilityInc) {
				for ($macdLong=$macdLongMin; $macdLong<=$macdLongMax; $macdLong++) {
					for ($macdShort=$macdShortMin; $macdShort<$macdLong && $macdShort<=$macdShortMax; $macdShort++) {
						if ($exportToFile) {
							file_put_contents('data/test.tsv', "date	low	high	open	close	volume	TR	ATR	max	avg short	avg long	holding	$currency	LTC	note\n");
						}
						$data_at=47856; // november 1
						#$data_at=67545; // december 1
						#$data_at=82000; // december 14
						#$data_at=102193; // Jan 1
						#$data_at=103154; // Jan 2
						#$data_at=104451; // Jan 3
						#$data_at=105714; // Jan 4
						#$data_at=106999; // Jan 5
						#$data_at=109586; // Jan 7
						#$data_at=112040; // Jan 9
						#$data_at=114459; // Jan 11
						$orderRecords=[];
						$GLOBALS['accountsByCurrency']=[ // {
							'LTC'=>[
								'balance'=>$startupLtc,
								'available'=>$startupLtc
							]
						]; // }
						$GLOBALS['accountsByCurrency'][$currency]=[
							'balance'=>$startupEur,
							'available'=>$startupEur
						];
						$sales=0;
						$purchases=0;
						do {
							$ret=runOne();
							$sales+=$ret['sell'];
							$purchases+=$ret['buy'];
							$go_again=1;
							if ($data_at==count($data['LTC-'.$currency])-1) {
								$go_again=0;
							}
							$block=$ret['block'];
							$bits=explode('	', trim($ret['report']));
							if ($exportToFile) {
								file_put_contents('data/test.tsv',
									date('c', $block[0]).'	'.$block[1].'	'.$block[2].'	'.$block[3].'	'.$block[4].'	'.$block[5].'	'.$block[6].'	'.$block[7].'	'.$block[8].'	'.$block[9][$macdShort].'	'.$block[9][$macdLong].'	'.$bits[1].'	'.$bits[2].'	'.$bits[3].'	'.$block[10]."\n",
									FILE_APPEND
								);
							}
						} while ($go_again);
						$current=[
							'percentToBuy'=>sprintf('%.2f', $percentToBuy),
							'percentToSell'=>sprintf('%.2f', $percentToSell),
							'blocks'=>$blocks,
							'volatility'=>sprintf('%.2f', $volatility),
							'macdShort'=>intval($macdShort),
							'macdLong'=>intval($macdLong),
							'sales'=>$sales,
							'purchases'=>$purchases,
							'holding'=>$bits[1],
						];
						if (floatval($bits[1])>$best['holding']) {
							$best=$current;
						}
						echo 'current   : '.json_encode($current)."\n";
						echo 'best found: '.json_encode($best)."\n";
					}
				}
			}
		}
	}
}

echo 'best found: '.json_encode($best)."\n";
