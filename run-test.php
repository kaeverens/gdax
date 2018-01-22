<?php
define('TEST', true);
require_once 'lib.php';

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
		$data['LTC-'.$currency][$k][11]=[0]; // will be avgs
	}
}
echo "calculating simple moving averages\n";
foreach ($data['LTC-'.$currency] as $k=>$v) { // calculate moving averages
	if ($k>$smaBuyLongMax || $k>$smaSellLongMax) {
		for ($j=1; $j<=$smaBuyLongMax||$j<=$smaSellLongMax; ++$j) {
			if (isset($data['LTC-'.$currency][$k-1][9][$j])) {
				$data['LTC-'.$currency][$k][9][$j]=(
					$data['LTC-'.$currency][$k-1][9][$j]
					*($j-1)+$data['LTC-'.$currency][$k][3]
				)/$j;
			}
			else {
				$data['LTC-'.$currency][$k][9][$j]=$data['LTC-'.$currency][$k][3];
			}
		}
	}
	else {
		$data['LTC-'.$currency][$k][9]=[0];
		for ($j=1; $j<=$emaBuyLongMax || $j<=$emaSellLongMax; ++$j) {
			$data['LTC-'.$currency][$k][9][$j]=$data['LTC-'.$currency][$k][3];
		}
	}
}
echo "calculating exponential moving averages\n";
foreach ($data['LTC-'.$currency] as $k=>$v) { // calculate moving averages
	$data['LTC-'.$currency][$k][11]=[0];
	if ($k==0) {
		for ($j=1; $j<=$emaBuyLongMax || $j<=$emaSellLongMax; ++$j) {
			$data['LTC-'.$currency][$k][11][$j]=$data['LTC-'.$currency][$k][3];
		}
	}
	else {
		for ($j=1; $j<=$emaBuyLongMax || $j<=$emaSellLongMax; ++$j) {
			$multiplier=2/($j+1);
			$data['LTC-'.$currency][$k][11][$j]=
				($data['LTC-'.$currency][$k][3] - $data['LTC-'.$currency][$k-1][11][$j])
				* $multiplier+$data['LTC-'.$currency][$k-1][11][$j];
		}
	}
}
$best=[
	'holding'=>0
];
// }

for ($smaEmaMix=$smaEmaMixMin; $smaEmaMix<=$smaEmaMixMax; ++$smaEmaMix) {
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
					for ($emaBuyLong=$emaBuyLongMin; $emaBuyLong<=$emaBuyLongMax; $emaBuyLong++) {
						for ($emaBuyShort=$emaBuyShortMin; $emaBuyShort<$emaBuyLong && $emaBuyShort<=$emaBuyShortMax; $emaBuyShort++) {
							for ($emaSellLong=$emaSellLongMin; $emaSellLong<=$emaSellLongMax; $emaSellLong++) {
								for ($emaSellShort=$emaSellShortMin; $emaSellShort<$emaSellLong && $emaSellShort<=$emaSellShortMax; $emaSellShort++) {
									for ($smaBuyLong=$smaBuyLongMin; $smaBuyLong<=$smaBuyLongMax; $smaBuyLong++) {
										for ($smaSellLong=$smaSellLongMin; $smaSellLong<=$smaSellLongMax; $smaSellLong++) {
											for ($smaBuyShort=$smaBuyShortMin; $smaBuyShort<$smaBuyLong && $smaBuyShort<=$smaBuyShortMax; $smaBuyShort++) {
												for ($smaSellShort=$smaSellShortMin; $smaSellShort<$smaSellLong && $smaSellShort<=$smaSellShortMax; $smaSellShort++) {
													if ($exportToFile) {
														file_put_contents('data/test.tsv', "date	low	high	open	close	volume	TR	ATR	max	avg short	avg long	holding	$currency	LTC	note\n");
													}
													$data_at=$data_start_at;
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
																date('c', $block[0]).'	'.$block[1].'	'.$block[2].'	'.$block[3].'	'.$block[4].'	'.$block[5].'	'.$block[6].'	'.$block[7].'	'.$block[8].'	'.$block[9][$smaBuyShort].'	'.$block[9][$smaSellShort].'	'.$block[9][$smaBuyLong].'	'.$block[9][$smaSellLong].'	'.$bits[1].'	'.$bits[2].'	'.$bits[3].'	'.$block[10]."\n",
																FILE_APPEND
															);
														}
													} while ($go_again);
													$current=[
														'percentToBuy'=>sprintf('%.2f', $percentToBuy),
														'percentToSell'=>sprintf('%.2f', $percentToSell),
														'blocks'=>$blocks,
														'volatility'=>sprintf('%.3f', $volatility),
														'smaEmaMix'=>$smaEmaMix,
														'smaBuyShort'=>intval($smaBuyShort),
														'smaBuyLong'=>intval($smaBuyLong),
														'smaSellShort'=>intval($smaSellShort),
														'smaSellLong'=>intval($smaSellLong),
														'emaBuyShort'=>intval($emaBuyShort),
														'emaBuyLong'=>intval($emaBuyLong),
														'emaSellShort'=>intval($emaSellShort),
														'emaSellLong'=>intval($emaSellLong),
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
						}
					}
				}
			}
		}
	}
}

echo 'best found: '.json_encode($best)."\n";
