<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
use Hellovoid\Gdax\Configuration;
use Hellovoid\Gdax\Client;
$configuration = Configuration::apiKey($apiKey, $apiSecret, $apiPassphrase);
$client = Client::create($configuration);
$orderRecords=[];

function buy($avg) {
	global $percentToBuy;
	$accountsByCurrency=getAccountsByCurrency();
	$amtToTransfer=($accountsByCurrency['EUR']['available']*$percentToBuy*.01)/$avg;
	$amtToTransfer=floor($amtToTransfer*10000000)/10000000;
	if ($amtToTransfer>=.01) {
		$buyPrice=floor($avg*100)/100;
		$params=[
		    'size'       => $amtToTransfer,
		    'price'      => $buyPrice,
		    'side'       => 'buy',
		    'product_id' => 'LTC-EUR'
		];
		placeOrder($params, -$amtToTransfer*$buyPrice, $amtToTransfer);
		return 1;
	}
	return 0;
}
function sell($avg) {
	global $percentToSell;
	$accountsByCurrency=getAccountsByCurrency();
	$amtToTransfer=$accountsByCurrency['LTC']['available']*$percentToSell*.01;
	$amtToTransfer=floor($amtToTransfer*10000000)/10000000;
	if ($amtToTransfer>=.01) {
		$params=[
		    'size'       => $amtToTransfer,
		    'price'      => floor($avg*100)/100,
		    'side'       => 'sell',
		    'product_id' => 'LTC-EUR'
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
	global $blocks, $macdLongMax;
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
				for ($i=count($history)-2;$i>-1;--$i) {
					for ($j=1;$j<=$macdLongMax;++$j) {
						$history[$i][9][$j]=isset($history[$i+1][9][$j])
							?($history[$i+1][9][$j]*($j-1)+$history[$i][3])/$j
							:$history[$i][3];
					}
				}
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
	global $blocks, $volatility, $macdShort, $macdLong;
	$str='';
	$sell=0;
	$buy=0;
	// { LTC/EUR
	$history=getProductHistoricRates('LTC-EUR', 200); // get some data
	$avg=$history[0][4]; // get current coin value;
	$chandelierStop=$history[0][2]-$history[0][7]*$volatility; // high - ATR*volitility
	$history[0][10]='';
	$str.='Close: '.$avg.', Chandelier Exit: '.$chandelierStop.', MACD Short: '.$history[0][9][$macdShort].', MACD Long: '.$history[0][9][$macdLong]."\n";
	if ($avg<$chandelierStop) {
		$sell=sell($avg);
		$str.='SELL: current close is lower than Chandelier Exit. Cut your losses and wait for the next Buy signal'."\n";
		if ($sell) {
			$history[0][10]='sell (Chandelier exit)';
		}
	}
	else if (
		$history[0][9][$macdShort]>$history[0][9][$macdLong]
		&& $history[1][9][$macdShort]<=$history[1][9][$macdLong]
	) { // MACD
		$str.='BUY: the short/long term moving averages have crossed over. MACD Short is higher now'."\n";
		$buy=buy($avg);
		if ($buy) {
			$history[0][10]='buy (MACD)';
		}
	}
	else if (
		$history[0][9][$macdShort]<$history[0][9][$macdLong]
		&& $history[1][9][$macdShort]>=$history[1][9][$macdLong]
	) { // MACD
		$str.='SELL: the short/long term moving averages have crossed over. MACD Long is higher now'."\n";
		$sell=sell($avg);
		if ($sell) {
			$history[0][10]='sell (MACD)';
		}
	}
	// }
	$accountsByCurrency=getAccountsByCurrency();
	$report=date('Y-m-d H:i:s', $history[0][0]) // {
		.'	'.($accountsByCurrency['EUR']['balance']+($accountsByCurrency['LTC']['balance']*$avg))
		.'	'.$accountsByCurrency['EUR']['balance']
		.'	'.$accountsByCurrency['LTC']['balance']."\n"; // }
	return [
		'str'=>$str,
		'report'=>$report,
		'sell'=>$sell,
		'buy'=>$buy,
		'block'=>$history[0],
	];
}
