<?php
define('TEST', true);
require_once 'lib.php';
if (!defined('AUTOCONFIG')) {
	define('AUTOCONFIG', 0);
	$smaSellShortMin=$smaSellShort;
	$smaSellShortMax=$smaSellShort;
	$smaSellLongMin=$smaSellLong;
	$smaSellLongMax=$smaSellLong;
	$smaBuyShortMin=$smaBuyShort;
	$smaBuyShortMax=$smaBuyShort;
	$smaBuyLongMin=$smaBuyLong;
	$smaBuyLongMax=$smaBuyLong;
	$emaSellShortMin=$emaSellShort;
	$emaSellShortMax=$emaSellShort;
	$emaSellLongMin=$emaSellLong;
	$emaSellLongMax=$emaSellLong;
	$emaBuyShortMin=$emaBuyShort;
	$emaBuyShortMax=$emaBuyShort;
	$emaBuyLongMin=$emaBuyLong;
	$emaBuyLongMax=$emaBuyLong;
	$sellMin=$percentToSell;
	$sellMax=$percentToSell;
	$sellInc= 0.1;
	$volatilityMin=$volatility;
	$volatilityMax=$volatility;
	$blocksMin=$blocks;
	$blocksMax=$blocks;
}

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
	if ($k>100) {
		for ($j=1; $j<=100; ++$j) {
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
		for ($j=1; $j<=100; ++$j) {
			$data['LTC-'.$currency][$k][9][$j]=$data['LTC-'.$currency][$k][3];
		}
	}
}
echo "calculating exponential moving averages\n";
foreach ($data['LTC-'.$currency] as $k=>$v) { // calculate moving averages
	$data['LTC-'.$currency][$k][11]=[0];
	if ($k==0) {
		for ($j=1; $j<=100; ++$j) {
			$data['LTC-'.$currency][$k][11][$j]=$data['LTC-'.$currency][$k][3];
		}
	}
	else {
		for ($j=1; $j<=100; ++$j) {
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

function runTest() {
	global
		$blocks, $volatility, $emaBuyShort, $emaSellShort, $emaBuyLong, $emaSellLong, $smaBuyShort, $smaSellShort, $smaBuyLong, $smaSellLong, $currency, $smaEmaMix, $percentToSell, $percentToBuy, $tradeAtAtrBuy, $tradeAtAtrSell,
		$smaEmaMixMin, $smaEmaMixMax,
		$blocksMin, $blocksMax,
		$sellMin, $sellMax, $sellInc,
		$buyMin, $buyMax, $buyInc,
		$volatilityMin, $volatilityMax, $volatilityInc,
		$tradeAtAtrBuyMin, $tradeAtAtrBuyMax, $tradeAtAtrBuyInc,
		$tradeAtAtrSellMin, $tradeAtAtrSellMax, $tradeAtAtrSellInc,
		$emaBuyLongMin, $emaBuyLongMax,
		$emaBuyShortMin, $emaBuyShortMax,
		$emaSellLongMin, $emaSellLongMax,
		$emaSellShortMin, $emaSellShortMax,
		$smaBuyLongMin, $smaBuyLongMax,
		$smaBuyShortMin, $smaBuyShortMax,
		$smaSellLongMin, $smaSellLongMax,
		$smaSellShortMin, $smaSellShortMax,
		$data_start_at, $data, $currency, $startupLtc, $startupEur, $data_at;
	$best=$GLOBALS['best'];

	for ($smaEmaMix=$smaEmaMixMin; $smaEmaMix<=$smaEmaMixMax; ++$smaEmaMix) {
		for ($blocks=$blocksMin; $blocks<=$blocksMax; ++$blocks) {
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
					for ($tradeAtAtrSell=$tradeAtAtrSellMin; $tradeAtAtrSell<=$tradeAtAtrSellMax; $tradeAtAtrSell+=$tradeAtAtrSellInc) {
						for ($tradeAtAtrBuy=$tradeAtAtrBuyMin; $tradeAtAtrBuy<=$tradeAtAtrBuyMax; $tradeAtAtrBuy+=$tradeAtAtrBuyInc) {
							for ($volatility=$volatilityMin; $volatility<=$volatilityMax; $volatility+=$volatilityInc) {
								for ($emaBuyLong=$emaBuyLongMin; $emaBuyLong<=$emaBuyLongMax; $emaBuyLong++) {
									for ($emaBuyShort=$emaBuyShortMin; $emaBuyShort<$emaBuyLong && $emaBuyShort<=$emaBuyShortMax; $emaBuyShort++) {
										for ($emaSellLong=$emaSellLongMin; $emaSellLong<=$emaSellLongMax; $emaSellLong++) {
											for ($emaSellShort=$emaSellShortMin; $emaSellShort<$emaSellLong && $emaSellShort<=$emaSellShortMax; $emaSellShort++) {
												for ($smaSellLong=$smaSellLongMin; $smaSellLong<=$smaSellLongMax; $smaSellLong++) {
													for ($smaBuyLong=$smaBuyLongMin; $smaBuyLong<=$smaBuyLongMax; $smaBuyLong++) {
														for ($smaBuyShort=$smaBuyShortMin; $smaBuyShort<$smaBuyLong && $smaBuyShort<=$smaBuyShortMax; $smaBuyShort++) {
															for ($smaSellShort=$smaSellShortMin; $smaSellShort<$smaSellLong && $smaSellShort<=$smaSellShortMax; $smaSellShort++) {
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
																	'tradeAtAtrBuy'=>sprintf('%.2f', $tradeAtAtrBuy),
																	'tradeAtAtrSell'=>sprintf('%.2f', $tradeAtAtrSell),
																	'holding'=>$bits[1],
																];
																if (floatval($bits[1])>$best['holding']) {
																	$best=$current;
																}
																if (!AUTOCONFIG) {
																	echo 'current   : '.json_encode($current)."\n";
																	echo 'best found: '.json_encode($best)."\n";
																}
																else {
																	echo '.';
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
			}
		}
	}
	return $best;
}

if (!AUTOCONFIG) {
	$best=runTest();
	echo 'best found: '.json_encode($best)."\n";
}

