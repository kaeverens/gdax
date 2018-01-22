<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
use Hellovoid\Gdax\Configuration;
use Hellovoid\Gdax\Client;
$configuration = Configuration::apiKey($apiKey, $apiSecret, $apiPassphrase);
$client = Client::create($configuration);
$orderRecords=[];

function buy($avg) {
	global $percentToBuy, $currency;
	$accountsByCurrency=getAccountsByCurrency();
	$amtToTransfer=($accountsByCurrency[$currency]['available']*$percentToBuy*.01)/$avg;
	$amtToTransfer=floor($amtToTransfer*10000000)/10000000;
	if ($amtToTransfer>=.1) {
		$buyPrice=floor($avg*100)/100;
		$params=[
		    'size'       => $amtToTransfer,
		    'price'      => $buyPrice,
		    'side'       => 'buy',
		    'product_id' => 'LTC-'.$currency
		];
		placeOrder($params, -$amtToTransfer*$buyPrice, $amtToTransfer);
		return 1;
	}
	return 0;
}
function sell($avg) {
	global $percentToSell, $currency;
	$accountsByCurrency=getAccountsByCurrency();
	$amtToTransfer=$accountsByCurrency['LTC']['available']*$percentToSell*.01;
	$amtToTransfer=floor($amtToTransfer*10000000)/10000000;
	if ($amtToTransfer>=.1) {
		$params=[
		    'size'       => $amtToTransfer,
		    'price'      => floor($avg*100)/100,
		    'side'       => 'sell',
		    'product_id' => 'LTC-'.$currency
		];
		placeOrder($params, $amtToTransfer*$avg, -$amtToTransfer);
		return 1;
	}
	return 0;
}
function getAccountsByCurrency() {
	if (TEST) {
		return isset($GLOBALS['accountsByCurrency'])?$GLOBALS['accountsByCurrency']:[];
	}
	else {
		do {
			$ok=0;
			try {
				$accounts=$GLOBALS['client']->getAccounts();
				$ok=1;
			}
			catch (Exception $e) {
				echo $e->getMessage()."\n";
				echo "will try again in 10s\n";
				sleep(10);
			}
		} while(!$ok);
		$accountsByCurrency=[];
		foreach ($accounts as $acc) {
			$accountsByCurrency[$acc['currency']]=$acc;
		}
		return $accountsByCurrency;
	}
}
function getProductHistoricRates($currency, $num) {
	global $blocks, $emaBuyLongMax, $emaSellLongMax, $smaBuyLongMax, $smaSellLongMax;
	if (TEST) {
		$GLOBALS['data_at']++;
		$history=[];
		for ($i=0;$i<$num;++$i) {
			$history[]=$GLOBALS['data'][$currency][$GLOBALS['data_at']-$i];
		}
	}
	else {
		do {
			try {
				$history=$GLOBALS['client']->getProductHistoricRates($currency);
				for ($i=0;$i<count($history)-1;++$i) { // calculate true range
					$m1=abs($history[$i][2]-$history[$i][1]);
					$m2=abs($history[$i][2]-$history[$i+1][4]);
					$m3=abs($history[$i][1]-$history[$i+1][4]);
					$history[$i][6]=max($m1, $m2, $m3);
					$history[$i][7]=0; // will be ATR
					$history[$i][8]=0; // will be max highs
					$history[$i][9]=[0]; // will be avgs
				}
				// { simple moving averages
				for ($i=count($history)-2;$i>-1;--$i) {
					for ($j=1;$j<=$smaBuyLongMax||$j<=$smaSellLongMax;++$j) {
						$history[$i][9][$j]=isset($history[$i+1][9][$j])
							?($history[$i+1][9][$j]*($j-1)+$history[$i][3])/$j
							:$history[$i][3];
					}
				}
				// }
				// { exponential moving averages
				for ($i=count($history)-1;$i>-1;--$i) {
					$history[$i][11]=[0];
					if ($i==count($history)-1) {
						for ($j=1; $j<=$emaBuyLongMax || $j<=$emaSellLongMax; ++$j) {
							$history[$i][11][$j]=$history[$i][3];
						}
					}
					else {
						for ($j=1; $j<=$emaBuyLongMax || $j<=$emaSellLongMax; ++$j) {
							$multiplier=2/($j+1);
							$history[$i][11][$j]=
								($history[$i][3] - $history[$i+1][11][$j])
								* $multiplier+$history[$i+1][11][$j];
						}
					}
				}
				// }
				// { calculate average true ranges (ATRs)
				$history[count($history)-2][7]=$history[count($history)-2][6];
				for ($i=count($history)-3;$i>-1;--$i) { // calculate rolling ATRs
					$history[$i][7]=($history[$i][6]+$history[$i+1][7]*($num-1))/$num;
				}
				// }
				$highs=[];
				for ($i=count($history)-1;$i>-1;--$i) { // calculate max values
					$highs[]=$history[$i][2];
					if (count($highs)>$blocks) {
						array_shift($highs);
					}
					$history[$i][8]=max($highs);
				}
				array_splice($history, $num);
				$done=1;
			}
			catch (Exception $e) {
				echo $e->getMessage()."\n";
				echo "will try again in 10s\n";
				sleep(10);
			}
		} while(!$done);
	}
	return $history;
}
function placeOrder($params, $cash, $crypto) {
	global $activeTrade;
	if (TEST) {
		$fee=abs($cash)*.001;
		$currencies=explode('-', $params['product_id']);
		$GLOBALS['accountsByCurrency'][$currencies[1]]['balance']+=$cash-$fee;
		$GLOBALS['accountsByCurrency'][$currencies[1]]['available']+=$cash-$fee;
		$GLOBALS['accountsByCurrency'][$currencies[0]]['balance']+=$crypto;
		$GLOBALS['accountsByCurrency'][$currencies[0]]['available']+=$crypto;
	}
	else {
		try {
			if ($activeTrade) {
				$GLOBALS['client']->placeOrder($params);
			}
		}
		catch(Exception $e) {
			echo 'ERROR: '.$e->getMessage()."\n";
		}
	}
	$GLOBALS['orderRecords'][]=$params;
}
function runOne() {
	global $blocks, $volatility, $emaBuyShort, $emaSellShort, $emaBuyLong, $emaSellLong, $smaBuyShort, $smaSellShort, $smaBuyLong, $smaSellLong, $currency, $smaEmaMix;
	$str='';
	$sell=0;
	$buy=0;

	$history=getProductHistoricRates('LTC-'.$currency, 200); // get some data
	$avg=$history[0][4]; // get current coin value;
	$chandelierStop=$history[0][2]-$history[0][7]*$volatility; // high - ATR*volitility
	$history[0][10]='';
	$str.='Close: '.$avg
		.', Chandelier Exit: '.sprintf('%.02f', $chandelierStop)
		.', SMA Buy Short: '.sprintf('%.02f', $history[0][9][$smaBuyShort])
		.', SMA Buy Long: '.sprintf('%.02f', $history[0][9][$smaBuyLong])
		.', SMA Sell Short: '.sprintf('%.02f', $history[0][9][$smaSellShort])
		.', SMA Sell Long: '.sprintf('%.02f', $history[0][9][$smaSellLong]);
	if ($smaEmaMix&1) {
		$str.=', EMA Buy Short: '.sprintf('%.02f', $history[0][11][$emaBuyShort])
		.', EMA Buy Long: '.sprintf('%.02f', $history[0][11][$emaBuyLong]);
	}
	if ($smaEmaMix&2) {
		$str.=', EMA Sell Short: '.sprintf('%.02f', $history[0][11][$emaSellShort])
		.', EMA Sell Long: '.sprintf('%.02f', $history[0][11][$emaSellLong]);
	}
	$str.="\n";
	if ($avg<$chandelierStop) {
		$sell=sell($avg);
		$str.='SELL: current close is lower than Chandelier Exit. Cut your losses and wait for the next Buy signal'."\n";
		$history[0][10]='sell (Chandelier exit)';
	}
	else if (
		$smaEmaMix&1 // use EMA for buys
		&& $history[0][11][$emaBuyShort]>$history[0][11][$emaBuyLong]
		&& $history[1][11][$emaBuyShort]<=$history[1][11][$emaBuyLong]
	) { // EMA Crossover
		$str.='BUY: the short/long term moving averages have crossed over. EMA Short is higher now'."\n";
		$buy=buy($avg);
		$history[0][10]='buy (EMA)';
	}
	else if (
		$smaEmaMix&2 // use EMA for sells
		&& $history[0][11][$emaSellShort]<$history[0][11][$emaSellLong]
		&& $history[1][11][$emaSellShort]>=$history[1][11][$emaSellLong]
	) { // MACD
		$str.='SELL: the short/long term moving averages have crossed over. EMA Long is higher now'."\n";
		$sell=sell($avg);
		$history[0][10]='sell (EMA)';
	}
	else if (
		$smaEmaMix&4 // use SMA for buys
		&& $history[0][9][$smaBuyShort]>$history[0][9][$smaBuyLong]
		&& $history[1][9][$smaBuyShort]<=$history[1][9][$smaBuyLong]
	) { // MACD
		$str.='BUY: the short/long term moving averages have crossed over. SMA Short is higher now'."\n";
		$buy=buy($avg);
		$history[0][10]='buy (SMA)';
	}
	else if (
		$smaEmaMix&8 // use SMA for sells
		&& $history[0][9][$smaSellShort]<$history[0][9][$smaSellLong]
		&& $history[1][9][$smaSellShort]>=$history[1][9][$smaSellLong]
	) { // MACD
		$str.='SELL: the short/long term moving averages have crossed over. SMA Long is higher now'."\n";
		$sell=sell($avg);
		$history[0][10]='sell (SMA)';
	}

	$accountsByCurrency=getAccountsByCurrency();
	$report=date('Y-m-d H:i:s', $history[0][0]) // {
		.'	'.($accountsByCurrency[$currency]['balance']+($accountsByCurrency['LTC']['balance']*$avg))
		.'	'.$accountsByCurrency[$currency]['balance']
		.'	'.$accountsByCurrency['LTC']['balance']."\n"; // }
	return [
		'str'=>$str,
		'report'=>$report,
		'sell'=>$sell,
		'buy'=>$buy,
		'block'=>$history[0],
	];
}
