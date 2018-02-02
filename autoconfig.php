<?php
function resetVals($newBest) {
	global $smaBuyShort, $smaBuyLong, $smaSellShort, $smaSellLong, $emaBuyShort, $emaBuyLong, $emaSellShort, $emaSellLong, $blocks, $volatility, $tradeAtAtrBuy, $tradeAtAtrSell, $stopGainMultiplier;
	$blocks=0+$newBest['blocks'];
	$tradeAtAtrBuy=0+$newBest['tradeAtAtrBuy'];
	$tradeAtAtrSell=0+$newBest['tradeAtAtrSell'];
	$volatility=0+$newBest['volatility'];
	$smaBuyShort=0+$newBest['smaBuyShort'];
	$smaBuyLong=0+$newBest['smaBuyLong'];
	$smaSellShort=0+$newBest['smaSellShort'];
	$smaSellLong=0+$newBest['smaSellLong'];
	$emaBuyShort=0+$newBest['emaBuyShort'];
	$emaBuyLong=0+$newBest['emaBuyLong'];
	$emaSellShort=0+$newBest['emaSellShort'];
	$emaSellLong=0+$newBest['emaSellLong'];
	$stopGainMultiplier=0+$newBest['stopGainMultiplier'];
}
function getLatestResults() {
	global $days, $currency, $startupEur, $best, $displayPrecision;
	return "  latest test shows that running for ".sprintf('%0.2f', $days).' days, starting with '.$currency.$startupEur.', would result in '.$currency.sprintf('%0.0'.$displayPrecision.'f', $best['holding']).', which is '.sprintf('%0.0'.$displayPrecision.'f', 100*pow($best['holding']/$startupEur, 1/$days)-100)."% per day\n";
}

require_once 'config.php';
if (@file_get_contents('https://www.gdax.com/')) {
	echo "retrieving latest historical data\n";
	`php build-historic.php`;
}
else {
	echo "we don't appear to be online, so let's skip downloading new historic rates\n";
}

define('AUTOCONFIG', 1);

require_once 'run-test.php';

// { find a good early date to start calculating from
$lines=count($data['LTC-'.$currency]);
echo "there are ".$lines." minutes of data in the historic file\n";
$current=$data['LTC-'.$currency][$lines-1][1];
$earliest=0;
$days=0;
echo "finding a good starting point. the latest value is ".$current.". we want a value which is higher (if possible), and at least a month ago\n";
for ($j=$lines-(60*24*31);$j>MAXAVGS;--$j) {
	if ($data['LTC-'.$currency][$j][1]>$current) {
		$earliest=$j;
	}
}
if ($earliest) {
	$days=($lines-$earliest)/60/24;
	echo "testing will start from line ".$earliest." (value: ".$data['LTC-'.$currency][$earliest][1]."), which was ".sprintf('%0.2f', $days)." days before the last line\n";
}

$data_start_at=$earliest;
// }
// { set up defaults
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
$blocksMin=$blocks;
$blocksMax=$blocks;
$volatilityMin=$volatility;
$volatilityMax=$volatility;
$volatilityInc= 0.001;
$tradeAtAtrBuyMin=$tradeAtAtrBuy;
$tradeAtAtrBuyMax=$tradeAtAtrBuy;
$tradeAtAtrBuyInc= 0.00001;
$tradeAtAtrSellMin=$tradeAtAtrSell;
$tradeAtAtrSellMax=$tradeAtAtrSell;
$tradeAtAtrSellInc= 0.00001;
$stopGainMultiplierMin=$stopGainMultiplier;
$stopGainMultiplierMax=$stopGainMultiplier;
$stopGainMultiplierInc=0.1;
// }
// { show current calculation values
$tradeHistory=[];
$best=runTest();
$bestTradeHistory=$tradeHistory;
resetVals($best);

echo 'current status: simulation shows that running for '.sprintf('%0.2f', $days).' days, starting with '.$currency.$startupEur.', would result in '.$currency.sprintf('%0.0'.$displayPrecision.'f', $best['holding']).', which is '.sprintf('%0.0'.$displayPrecision.'f', 100*pow($best['holding']/$startupEur, 1/$days)-100)."% per day\n";

echo "about to start running tests. please note that there are a LOT of calculations in this. get a coffee. you might even have time to grow the beans.\n";
// }
do { // run tests to find new values
	$improved=0;
	// { stopGainMultiplier
	echo 'will changing the stop-gain multiplier ('.sprintf('%0.0'.$displayPrecision.'f', $stopGainMultiplier).") work better...\n  ";
	$stopGainMultiplierMin=$stopGainMultiplier-$stopGainMultiplierInc*5;
	$stopGainMultiplierMax=$stopGainMultiplier+$stopGainMultiplierInc*5;
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$bestTradeHistory=$tradeHistory;
		$improved=true;
		echo ' YES (new value '.sprintf('%0.0'.$displayPrecision.'f', $stopGainMultiplier).")\n".getLatestResults();
		$stopGainMultiplierInc*=1.1;
	}
	else {
		echo " no\n";
		$stopGainMultiplierInc*=0.5;
	}
	$stopGainMultiplierMin=$stopGainMultiplier;
	$stopGainMultiplierMax=$stopGainMultiplier;
	// }
	// { trade at ATR Buy
	echo "will changing the trade-at ATR Buy limit (".sprintf('%0.0'.$displayPrecision.'f', $tradeAtAtrBuy).") work better...\n  ";
	$tradeAtAtrBuyMin=$tradeAtAtrBuy-$tradeAtAtrBuyInc*3;
	$tradeAtAtrBuyMax=$tradeAtAtrBuy+$tradeAtAtrBuyInc*3;
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$bestTradeHistory=$tradeHistory;
		$improved=true;
		echo " YES (new value ".sprintf('%0.0'.$displayPrecision.'f', $tradeAtAtrBuy).")\n".getLatestResults();
		$tradeAtAtrBuyInc*=1.1;
	}
	else {
		echo " no\n";
		$tradeAtAtrBuyInc*=0.5;
	}
	$tradeAtAtrBuyMin=$tradeAtAtrBuy;
	$tradeAtAtrBuyMax=$tradeAtAtrBuy;
	// }
	// { trade at ATR Sell
	echo "will changing the trade-at ATR Sell limit (".sprintf('%0.0'.$displayPrecision.'f', $tradeAtAtrSell).") work better...\n  ";
	$tradeAtAtrSellMin=$tradeAtAtrSell-$tradeAtAtrSellInc*3;
	$tradeAtAtrSellMax=$tradeAtAtrSell+$tradeAtAtrSellInc*3;
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$bestTradeHistory=$tradeHistory;
		$improved=true;
		echo " YES (new value ".sprintf('%0.0'.$displayPrecision.'f', $tradeAtAtrSell).")\n".getLatestResults();
		$tradeAtAtrSellInc*=1.1;
	}
	else {
		echo " no\n";
		$tradeAtAtrSellInc*=0.5;
	}
	$tradeAtAtrSellMin=$tradeAtAtrSell;
	$tradeAtAtrSellMax=$tradeAtAtrSell;
	// }
	// { blocks/volatility
	echo "will changing the blocks and volatility (".$blocks.', '.sprintf('%0.0'.$displayPrecision.'f', $volatility)."%) work better...\n  ";
	$blocksMin=$blocks-1;
	$blocksMax=$blocks+1;
	$volatilityMin=$volatility-$volatilityInc*3;
	$volatilityMax=$volatility+$volatilityInc*3;
	if ($blocksMin<2) {
		$blocksMin=2;
	}
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$bestTradeHistory=$tradeHistory;
		$improved=true;
		echo " YES (new values ".$blocks.', '.sprintf('%0.0'.$displayPrecision.'f', $volatility)."%)\n".getLatestResults();
		$volatilityInc*=1.1;
	}
	else {
		echo " no\n";
		$volatilityInc*=0.5;
	}
	$blocksMin=$blocks;
	$blocksMax=$blocks;
	$volatilityMin=$volatility;
	$volatilityMax=$volatility;
	// }
	// { sma Buy 
	echo "will changing the SMA Buy short/long values (".$smaBuyShort.", ".$smaBuyLong.") work better...\n  ";
	$smaBuyShortMin=$smaBuyShort-4;
	$smaBuyShortMax=$smaBuyShort+4;
	$smaBuyLongMin=$smaBuyLong-8;
	$smaBuyLongMax=$smaBuyLong+8;
	if ($smaBuyShortMin<2) {
		$smaBuyShortMin=2;
	}
	if ($smaBuyShortMax>MAXAVGS-1) {
		$smaBuyShortMax=MAXAVGS-1;
	}
	if ($smaBuyLongMin<3) {
		$smaBuyLongMin=3;
	}
	if ($smaBuyLongMax>MAXAVGS) {
		$smaBuyLongMax=MAXAVGS;
	}
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$bestTradeHistory=$tradeHistory;
		$improved=true;
		echo " YES (new values ".$smaBuyShort.", ".$smaBuyLong.")\n".getLatestResults();
	}
	else {
		echo " no\n";
	}
	$smaBuyShortMin=$smaBuyShort;
	$smaBuyShortMax=$smaBuyShort;
	$smaBuyLongMin=$smaBuyLong;
	$smaBuyLongMax=$smaBuyLong;
	// }
	// { sma Sell 
	echo "will changing the SMA Sell short/long values (".$smaSellShort.", ".$smaSellLong.") work better...\n  ";
	$smaSellShortMin=$smaSellShort-4;
	$smaSellShortMax=$smaSellShort+4;
	$smaSellLongMin=$smaSellLong-8;
	$smaSellLongMax=$smaSellLong+8;
	if ($smaSellShortMin<2) {
		$smaSellShortMin=2;
	}
	if ($smaSellShortMax>MAXAVGS-1) {
		$smaSellShortMax=MAXAVGS-1;
	}
	if ($smaSellLongMin<3) {
		$smaSellLongMin=3;
	}
	if ($smaSellLongMax>MAXAVGS) {
		$smaSellLongMax=MAXAVGS;
	}
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$bestTradeHistory=$tradeHistory;
		$improved=true;
		echo " YES (new values ".$smaSellShort.", ".$smaSellLong.")\n".getLatestResults();
	}
	else {
		echo " no\n";
	}
	$smaSellShortMin=$smaSellShort;
	$smaSellShortMax=$smaSellShort;
	$smaSellLongMin=$smaSellLong;
	$smaSellLongMax=$smaSellLong;
	// }
	// { ema Buy 
	echo "will changing the EMA Buy short/long values (".$emaBuyShort.", ".$emaBuyLong.") work better...\n  ";
	$emaBuyShortMin=$emaBuyShort-4;
	$emaBuyShortMax=$emaBuyShort+4;
	$emaBuyLongMin=$emaBuyLong-8;
	$emaBuyLongMax=$emaBuyLong+8;
	if ($emaBuyShortMin<2) {
		$emaBuyShortMin=2;
	}
	if ($emaBuyShortMax>MAXAVGS-1) {
		$emaBuyShortMax=MAXAVGS-1;
	}
	if ($emaBuyLongMin<3) {
		$emaBuyLongMin=3;
	}
	if ($emaBuyLongMax>MAXAVGS) {
		$emaBuyLongMax=MAXAVGS;
	}
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$bestTradeHistory=$tradeHistory;
		$improved=true;
		echo " YES (new values ".$emaBuyShort.", ".$emaBuyLong.")\n".getLatestResults();
	}
	else {
		echo " no\n";
	}
	$emaBuyShortMin=$emaBuyShort;
	$emaBuyShortMax=$emaBuyShort;
	$emaBuyLongMin=$emaBuyLong;
	$emaBuyLongMax=$emaBuyLong;
	// }
	// { ema Sell 
	echo "will changing the EMA Sell short/long values (".$emaSellShort.", ".$emaSellLong.") work better...\n  ";
	$emaSellShortMin=$emaSellShort-4;
	$emaSellShortMax=$emaSellShort+4;
	$emaSellLongMin=$emaSellLong-8;
	$emaSellLongMax=$emaSellLong+8;
	if ($emaSellShortMin<2) {
		$emaSellShortMin=2;
	}
	if ($emaSellShortMax>MAXAVGS-1) {
		$emaSellShortMax=MAXAVGS-1;
	}
	if ($emaSellLongMin<3) {
		$emaSellLongMin=3;
	}
	if ($emaSellLongMax>MAXAVGS) {
		$emaSellLongMax=MAXAVGS;
	}
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$bestTradeHistory=$tradeHistory;
		$improved=true;
		echo " YES (new values ".$emaSellShort.", ".$emaSellLong.")\n".getLatestResults();
	}
	else {
		echo " no\n";
	}
	$emaSellShortMin=$emaSellShort;
	$emaSellShortMax=$emaSellShort;
	$emaSellLongMin=$emaSellLong;
	$emaSellLongMax=$emaSellLong;
	// }
	if (0) { // test all
	echo "will changing everything, just a bit, work better...\n  ";
	$stopGainMultiplierMin=$stopGainMultiplier-$stopGainMultiplierInc;
	$stopGainMultiplierMax=$stopGainMultiplier+$stopGainMultiplierInc;
	$tradeAtAtrBuyMin=$tradeAtAtrBuy-$tradeAtAtrBuyInc;
	$tradeAtAtrBuyMax=$tradeAtAtrBuy+$tradeAtAtrBuyInc;
	$tradeAtAtrSellMin=$tradeAtAtrSell-$tradeAtAtrSellInc;
	$tradeAtAtrSellMax=$tradeAtAtrSell+$tradeAtAtrSellInc;
	$blocksMin=$blocks-1;
	$blocksMax=$blocks+1;
	$volatilityMin=$volatility-$volatilityInc*1;
	$volatilityMax=$volatility+$volatilityInc*1;
	$smaBuyShortMin=$smaBuyShort-1;
	$smaBuyShortMax=$smaBuyShort+1;
	$smaBuyLongMin=$smaBuyLong-1;
	$smaBuyLongMax=$smaBuyLong+1;
	$smaSellShortMin=$smaSellShort-1;
	$smaSellShortMax=$smaSellShort+1;
	$smaSellLongMin=$smaSellLong-1;
	$smaSellLongMax=$smaSellLong+1;
	$emaBuyShortMin=$emaBuyShort-1;
	$emaBuyShortMax=$emaBuyShort+1;
	$emaBuyLongMin=$emaBuyLong-1;
	$emaBuyLongMax=$emaBuyLong+1;
	$emaSellShortMin=$emaSellShort-1;
	$emaSellShortMax=$emaSellShort+1;
	$emaSellLongMin=$emaSellLong-1;
	$emaSellLongMax=$emaSellLong+1;
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$bestTradeHistory=$tradeHistory;
		$improved=true;
		echo " YES\n".getLatestResults();
		echo "	best config:\n	".json_encode($best)."\n";
	}
	else {
		echo " no\n";
	}
	$stopGainMultiplierMin=$stopGainMultiplier;
	$stopGainMultiplierMax=$stopGainMultiplier;
	$tradeAtAtrBuyMin=$tradeAtAtrBuy;
	$tradeAtAtrBuyMax=$tradeAtAtrBuy;
	$tradeAtAtrSellMin=$tradeAtAtrSell;
	$tradeAtAtrSellMax=$tradeAtAtrSell;
	$blocksMin=$blocks;
	$blocksMax=$blocks;
	$volatilityMin=$volatility;
	$volatilityMax=$volatility;
	$smaBuyShortMin=$smaBuyShort;
	$smaBuyShortMax=$smaBuyShort;
	$smaBuyLongMin=$smaBuyLong;
	$smaBuyLongMax=$smaBuyLong;
	$smaSellShortMin=$smaSellShort;
	$smaSellShortMax=$smaSellShort;
	$smaSellLongMin=$smaSellLong;
	$smaSellLongMax=$smaSellLong;
	$emaBuyShortMin=$emaBuyShort;
	$emaBuyShortMax=$emaBuyShort;
	$emaBuyLongMin=$emaBuyLong;
	$emaBuyLongMax=$emaBuyLong;
	$emaSellShortMin=$emaSellShort;
	$emaSellShortMax=$emaSellShort;
	$emaSellLongMin=$emaSellLong;
	$emaSellLongMax=$emaSellLong;
	}
} while($improved);
// { record final decisions to file
echo "finished running autoconfig\n";
echo "best config:\n".json_encode($best)."\n";
file_put_contents('data/test-best.tsv', join('', $bestTradeHistory));

echo "building chart to visualise the decisions made\n";
$bars=[];
for ($i=$earliest; $i<$lines; ++$i) {
	$bars[$data['LTC-'.$currency][$i][0]]=[
		$data['LTC-'.$currency][$i][0],
		$data['LTC-'.$currency][$i][4],
	];
}
file_put_contents('data/test-visualisation-data.js', 'var data='.json_encode(array_values($bars)).';');
$signals=[];
$lastSignalType='';
for ($i=0;$i<count($bestTradeHistory);++$i) {
	$t=explode('	', $bestTradeHistory[$i]);
	$bits=explode(' ', $t[4]);
	if ($bits[0]==$lastSignalType) {
		continue;
	}
	$lastSignalType=$bits[0];
	$t=floor(strtotime($t[0])/60)*60;
	$signals[]=[
		'initialValue'=>$bars[$t][1],
		'initialDate'=>$t,
		'type'=>$bits[0]
	];
}
file_put_contents('data/test-visualisation-signals.js', 'var signals='.json_encode(array_values($signals)).';');
// }
