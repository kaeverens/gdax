<?php
function resetVals($newBest) {
	global $smaBuyShort, $smaBuyLong, $smaSellShort, $smaSellLong, $emaBuyShort, $emaBuyLong, $emaSellShort, $emaSellLong, $percentToSell, $percentToBuy, $blocks, $volatility, $tradeAtAtrBuy, $tradeAtAtrSell;
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
	$percentToSell=0+$newBest['percentToSell'];
	$percentToBuy=0+$newBest['percentToBuy'];
}
function getLatestResults() {
	global $days, $currency, $startupEur, $best;
	return "  latest test shows that running for ".sprintf('%0.2f', $days).' days, starting with '.$currency.$startupEur.', would result in '.$currency.sprintf('%0.02f', $best['holding']).', which is '.sprintf('%0.04f', 100*pow($best['holding']/$startupEur, 1/$days)-100)."% per day\n";
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

$lines=count($data['LTC-'.$currency]);
echo "there are ".$lines." minutes of data in the historic file\n";
$current=$data['LTC-'.$currency][$lines-1][1];
$earliest=0;
$days=0;
echo "finding a good starting point. the latest value is ".$current.". we want a value which is higher (if possible), and at least a month ago\n";
for ($j=$lines-(60*24*31);$j>100;--$j) {
	if ($data['LTC-'.$currency][$j][1]>$current) {
		$earliest=$j;
	}
}
if ($earliest) {
	$days=($lines-$earliest)/60/24;
	echo "testing will start from line ".$earliest." (value: ".$data['LTC-'.$currency][$earliest][1]."), which was ".sprintf('%0.2f', $days)." days before the last line\n";
}

$data_start_at=$earliest;

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
$sellMin=$percentToSell;
$sellMax=$percentToSell;
$sellInc= 0.1;
$buyMin=$percentToBuy;
$buyMax=$percentToBuy;
$buyInc= 0.1;
$blocksMin=$blocks;
$blocksMax=$blocks;
$volatilityMin=$volatility;
$volatilityMax=$volatility;
$volatilityInc= 0.1;
$tradeAtAtrBuyMin=$tradeAtAtrBuy;
$tradeAtAtrBuyMax=$tradeAtAtrBuy;
$tradeAtAtrBuyInc= 0.1;
$tradeAtAtrSellMin=$tradeAtAtrSell;
$tradeAtAtrSellMax=$tradeAtAtrSell;
$tradeAtAtrSellInc= 0.1;
// }

$best=runTest();
resetVals($best);

echo 'current status: simulation shows that running for '.sprintf('%0.2f', $days).' days, starting with '.$currency.$startupEur.', would result in '.$currency.sprintf('%0.02f', $best['holding']).', which is '.sprintf('%0.04f', 100*pow($best['holding']/$startupEur, 1/$days)-100)."% per day\n";

echo "about to start running tests. please note that there are a LOT of calculations in this. get a coffee. you might even have time to grow the beans.\n";

do {
	$improved=0;
	// { trade at ATR Buy
	echo "testing to see if changing the trade-at ATR Buy limit (".sprintf('%0.02f', $tradeAtAtrBuy).") improves the figures...\n  ";
	$tradeAtAtrBuyMin=$tradeAtAtrBuy-$tradeAtAtrBuyInc*5;
	$tradeAtAtrBuyMax=$tradeAtAtrBuy+$tradeAtAtrBuyInc*5;
	$newBest=runTest();
	resetVals($newBest);
	$tradeAtAtrBuyInc*=0.7;
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$improved=true;
		echo " YES (new value ".sprintf('%0.02f', $tradeAtAtrBuy).")\n".getLatestResults();
	}
	else {
		echo " no\n";
	}
	$tradeAtAtrBuyMin=$tradeAtAtrBuy;
	$tradeAtAtrBuyMax=$tradeAtAtrBuy;
	// }
	// { trade at ATR Sell
	echo "testing to see if changing the trade-at ATR Sell limit (".sprintf('%0.02f', $tradeAtAtrSell).") improves the figures...\n  ";
	$tradeAtAtrSellMin=$tradeAtAtrSell-$tradeAtAtrSellInc*5;
	$tradeAtAtrSellMax=$tradeAtAtrSell+$tradeAtAtrSellInc*5;
	$newBest=runTest();
	resetVals($newBest);
	$tradeAtAtrSellInc*=0.7;
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$improved=true;
		echo " YES (new value ".sprintf('%0.02f', $tradeAtAtrSell).")\n".getLatestResults();
	}
	else {
		echo " no\n";
	}
	$tradeAtAtrSellMin=$tradeAtAtrSell;
	$tradeAtAtrSellMax=$tradeAtAtrSell;
	// }
	// { blocks/volatility
	echo "testing to see if changing the blocks and volatility (".$blocks.', '.sprintf('%0.02f', $volatility)."%) improves the figures...\n  ";
	$blocksMin=$blocks-3;
	$blocksMax=$blocks+3;
	$volatilityMin=$volatility-$volatilityInc*3;
	$volatilityMax=$volatility+$volatilityInc*3;
	if ($blocksMin<2) {
		$blocksMin=2;
	}
	$newBest=runTest();
	resetVals($newBest);
	$volatilityInc*=0.7;
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$improved=true;
		echo " YES (new values ".$blocks.', '.sprintf('%0.02f', $volatility)."%)\n".getLatestResults();
	}
	else {
		echo " no\n";
	}
	$blocksMin=$blocks;
	$blocksMax=$blocks;
	$volatilityMin=$volatility;
	$volatilityMax=$volatility;
	// }
	// { sma Buy 
	echo "testing to see if changing the SMA Buy short/long values (".$smaBuyShort.", ".$smaBuyLong.") improves the figures...\n  ";
	$smaBuyShortMin=$smaBuyShort-3;
	$smaBuyShortMax=$smaBuyShort+3;
	$smaBuyLongMin=$smaBuyLong-3;
	$smaBuyLongMax=$smaBuyLong+3;
	if ($smaBuyShortMin<2) {
		$smaBuyShortMin=2;
	}
	if ($smaBuyShortMin>99) {
		$smaBuyShortMin=99;
	}
	if ($smaBuyLongMin<3) {
		$smaBuyLongMin=3;
	}
	if ($smaBuyLongMin>100) {
		$smaBuyLongMin=100;
	}
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
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
	echo "testing to see if changing the SMA Sell short/long values (".$smaSellShort.", ".$smaSellLong.") improves the figures...\n  ";
	$smaSellShortMin=$smaSellShort-3;
	$smaSellShortMax=$smaSellShort+3;
	$smaSellLongMin=$smaSellLong-3;
	$smaSellLongMax=$smaSellLong+3;
	if ($smaSellShortMin<2) {
		$smaSellShortMin=2;
	}
	if ($smaSellShortMin>99) {
		$smaSellShortMin=99;
	}
	if ($smaSellLongMin<3) {
		$smaSellLongMin=3;
	}
	if ($smaSellLongMin>100) {
		$smaSellLongMin=100;
	}
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
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
	echo "testing to see if changing the EMA Buy short/long values (".$emaBuyShort.", ".$emaBuyLong.") improves the figures...\n  ";
	$emaBuyShortMin=$emaBuyShort-3;
	$emaBuyShortMax=$emaBuyShort+3;
	$emaBuyLongMin=$emaBuyLong-3;
	$emaBuyLongMax=$emaBuyLong+3;
	if ($emaBuyShortMin<2) {
		$emaBuyShortMin=2;
	}
	if ($emaBuyShortMin>99) {
		$emaBuyShortMin=99;
	}
	if ($emaBuyLongMin<3) {
		$emaBuyLongMin=3;
	}
	if ($emaBuyLongMin>100) {
		$emaBuyLongMin=100;
	}
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
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
	echo "testing to see if changing the EMA Sell short/long values (".$emaSellShort.", ".$emaSellLong.") improves the figures...\n  ";
	$emaSellShortMin=$emaSellShort-3;
	$emaSellShortMax=$emaSellShort+3;
	$emaSellLongMin=$emaSellLong-3;
	$emaSellLongMax=$emaSellLong+3;
	if ($emaSellShortMin<2) {
		$emaSellShortMin=2;
	}
	if ($emaSellShortMin>99) {
		$emaSellShortMin=99;
	}
	if ($emaSellLongMin<3) {
		$emaSellLongMin=3;
	}
	if ($emaSellLongMin>100) {
		$emaSellLongMin=100;
	}
	$newBest=runTest();
	resetVals($newBest);
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
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
	// { percent sell test
	echo "testing to see if changing the percentage coins in a sale (".sprintf('%0.02f', $percentToSell)."%) improves the figures...\n  ";
	$sellMin=$percentToSell-$sellInc*15;
	$sellMax=$percentToSell+$sellInc*15;
	if ($sellMin<1) {
		$sellMin=1;
	}
	if ($sellMax>99) {
		$sellMax=99;
	}
	$newBest=runTest();
	resetVals($newBest);
	$sellInc*=0.6;
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$improved=true;
		echo " YES (new value ".sprintf('%0.02f', $percentToSell)."%)\n".getLatestResults();
	}
	else {
		echo " no\n";
	}
	$sellMin=$percentToSell;
	$sellMax=$percentToSell;
	// }
	// { percent buy test
	echo "testing to see if changing the percentage coins in a buy (".sprintf('%0.02f', $percentToBuy)."%) improves the figures...\n  ";
	$buyMin=$percentToBuy-$buyInc*15;
	$buyMax=$percentToBuy+$buyInc*15;
	if ($buyMin<1) {
		$buyMin=1;
	}
	if ($buyMax>99) {
		$buyMax=99;
	}
	$newBest=runTest();
	resetVals($newBest);
	$buyInc*=0.6;
	if ($newBest['holding']>$best['holding']) {
		$best=$newBest;
		$improved=true;
		echo " YES (new value ".sprintf('%0.02f', $percentToBuy)."%)\n".getLatestResults();
	}
	else {
		echo " no\n";
	}
	$buyMin=$percentToBuy;
	$buyMax=$percentToBuy;
	// }
} while($improved);

echo "finished running autoconfig\n";
echo "best config:\n".json_encode($best)."\n";
