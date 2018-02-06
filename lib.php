<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
use Hellovoid\Gdax\Configuration;
use Hellovoid\Gdax\Client;
use Hellovoid\Gdax\Exception;

$configuration = Configuration::apiKey($apiKey, $apiSecret, $apiPassphrase);
$client = Client::create($configuration);
$orderRecords=[];
define('MAXAVGS', 100);
$argvHasBeenRun=0;

function buy($avg) {
	return buyLimit($avg);
}
function buyLimit($avg) {
	global $currency, $activeTrade;
	$accountsByCurrency=getAccountsByCurrency();
	$amtToTransfer=floor((($accountsByCurrency[$currency]['available']*0.99)/$avg)*10000000)/10000000;
	if ($amtToTransfer>=.1) {
		$buyPrice=floor($avg*100)/100;
		$params=[ // {
				'type'       => 'limit',
		    'size'       => sprintf('%0.08f', $amtToTransfer),
		    'price'      => sprintf('%0.08f', $buyPrice),
		    'side'       => 'buy',
		    'product_id' => 'LTC-'.$currency
		]; // }
		if (!TEST && $activeTrade) {
			try {
				$orderId='';
				$bought=0;
				do {
					$orderBook=0;
					if ($orderId) { // check status of order
						sleep(1); // give the thing time to trade
						try {
							$order=$GLOBALS['client']->getOrder($orderId);
							if (floatval($order['filled_size']!=0)) { // filled
								$bought=1;
							}
							else { // if the market has moved up, then cancel the order and make a new one.
								$orderBook=$GLOBALS['client']->getProductOrderBook('LTC-'.$currency, ['level'=>1]);
								if ($buyPrice<floatval($orderBook['asks'][0][0])-.01) { // market price shifted upwards. cancel the order and make a new one
									$ret=$GLOBALS['client']->orderCancel($orderId);
									$orderId=0;
								}
							}
						}
						catch (HttpException $e) {
							echo $e->getMessage()."\n";
						}
					}
					if (!$bought && !$orderId) {
						if (!$orderBook) {
							$orderBook=$GLOBALS['client']->getProductOrderBook('LTC-'.$currency, ['level'=>1]);
						}
						$buyPrice=floatval($orderBook['asks'][0][0])-.01;
						$amtToTransfer=floor((($accountsByCurrency[$currency]['available']*0.99)/$buyPrice)*10000000)/10000000;
						$params['size']=sprintf('%0.08f', $amtToTransfer);
						$params['price']=sprintf('%0.08f', $buyPrice);
						$order=placeOrder($params, -$amtToTransfer*$buyPrice, $amtToTransfer);
						$orderId=$order['id'];
					}
				} while (!$bought);
			}
			catch (HttpException $e) {
				echo $e->getMessage()."\n";
			}
		}
		else {
			$ret=placeOrder($params, -$amtToTransfer*($buyPrice+$GLOBALS['limitTradeOffset']), $amtToTransfer);
		}
		return 1;
	}
	return 0;
}
function buyMarket($avg) {
	global $currency;
	$accountsByCurrency=getAccountsByCurrency();
	$funds=floor($accountsByCurrency[$currency]['available']*.99*10000000)/10000000;
	$amtToTransfer=($accountsByCurrency[$currency]['available']*.99)/$avg;
	$amtToTransfer=floor($amtToTransfer*10000000)/10000000;
	if ($amtToTransfer>=.1) {
		$buyPrice=floor($avg*100)/100;
		$params=[
				'type'       => 'market',
				'funds'      => $funds,
		    'side'       => 'buy',
		    'product_id' => 'LTC-'.$currency
		];
		placeOrder($params, -$amtToTransfer*$buyPrice, $amtToTransfer);
		return 1;
	}
	return 0;
}
function sell($avg) {
	return sellLimit($avg);
}
function sellLimit($avg) {
	global $currency, $activeTrade;
	$accountsByCurrency=getAccountsByCurrency();
	$amtToTransfer=floor($accountsByCurrency['LTC']['available']*0.99*10000000)/10000000;
	if ($amtToTransfer>=.1) {
		$params=[ // {
				'type'       => 'limit',
		    'size'       => $amtToTransfer,
		    'price'      => floor($avg*100)/100,
		    'side'       => 'sell',
		    'product_id' => 'LTC-'.$currency
		]; // }
		if (!TEST && $activeTrade) {
			$orderId='';
			$sold=0;
			try {
				do {
					$orderBook=0;
					if ($orderId) { // check status of order
						sleep(1); // give the thing time to trade
						try {
							$order=$GLOBALS['client']->getOrder($orderId);
							if (floatval($order['filled_size']!=0)) { // filled
								$sold=1;
							}
							else { // if the market has moved down, then cancel the order and make a new one.
								$orderBook=$GLOBALS['client']->getProductOrderBook('LTC-'.$currency, ['level'=>1]);
								if ($sellPrice>floatval($orderBook['asks'][0][0])+.01) { // market price shifted down. cancel the order and make a new one
									$ret=$GLOBALS['client']->orderCancel($orderId);
									$orderId=0;
								}
							}
						}
						catch (HttpException $e) {
							echo $e->getMessage()."\n";
						}
					}
					if (!$sold && !$orderId) {
						if (!$orderBook) {
							$orderBook=$GLOBALS['client']->getProductOrderBook('LTC-'.$currency, ['level'=>1]);
						}
						$sellPrice=floatval($orderBook['bids'][0][0])+.01;
						$params['price']=sprintf('%0.08f', $sellPrice);
						$order=placeOrder($params, $amtToTransfer*$avg, -$amtToTransfer);
						$orderId=$order['id'];
					}
				} while (!$sold);
			}
			catch (HttpException $e) {
				echo $e->getMessage()."\n";
			}
		}
		else {
			$ret=placeOrder($params, $amtToTransfer*($avg-$GLOBALS['limitTradeOffset']), -$amtToTransfer);
		}
		return 1;
	}
	return 0;
}
function sellMarket($avg) {
	global $currency;
	$accountsByCurrency=getAccountsByCurrency();
	$amtToTransfer=$accountsByCurrency['LTC']['available']*0.99;
	$amtToTransfer=floor($amtToTransfer*10000000)/10000000;
	if ($amtToTransfer>=.1) {
		$params=[
				'type'       => 'market',
		    'size'       => $amtToTransfer,
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
			catch (HttpException $e) {
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
		$history=array_reverse(array_slice($GLOBALS['data'][$currency], $GLOBALS['data_at']-$num, $num));
#		$history=[];
#		for ($i=0;$i<$num;++$i) {
#			$history[]=$GLOBALS['data'][$currency][$GLOBALS['data_at']-$i];
#		}
	}
	else {
		do {
			try {
				$history=$GLOBALS['client']->getProductHistoricRates($currency);
				$tick=$GLOBALS['client']->getProductTicker($currency);
				$val=floatval($tick['price']);
				array_unshift($history, [time(), min($val, $history[0][1]), max($val, $history[0][2]), $history[0][4], $val, $history[0][5]]);
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
					for ($j=1;$j<=MAXAVGS;++$j) {
						$history[$i][9][$j]=isset($history[$i+1][9][$j])
							?($history[$i+1][9][$j]*($j-1)+$history[$i][4])/$j
							:$history[$i][4];
					}
				}
				// }
				// { exponential moving averages
				for ($i=count($history)-1;$i>-1;--$i) {
					$history[$i][11]=[0];
					if ($i==count($history)-1) {
						for ($j=1; $j<=MAXAVGS; ++$j) {
							$history[$i][11][$j]=$history[$i][4];
						}
					}
					else {
						for ($j=1; $j<=MAXAVGS; ++$j) {
							$multiplier=2/($j+1);
							$history[$i][11][$j]=
								($history[$i][4] - $history[$i+1][11][$j])
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
			catch (HttpException $e) {
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
	$GLOBALS['orderRecords'][]=$params;
	if (TEST) {
		$fee=abs($cash)*$GLOBALS['brokerFee']*.01;
		$currencies=explode('-', $params['product_id']);
		$GLOBALS['accountsByCurrency'][$currencies[1]]['balance']+=$cash-$fee;
		$GLOBALS['accountsByCurrency'][$currencies[1]]['available']+=$cash-$fee;
		$GLOBALS['accountsByCurrency'][$currencies[0]]['balance']+=$crypto;
		$GLOBALS['accountsByCurrency'][$currencies[0]]['available']+=$crypto;
	}
	else {
		try {
			if ($activeTrade) {
				$ret=$GLOBALS['client']->placeOrder($params);
				return $ret;
			}
		}
		catch(HttpException $e) {
			echo 'ERROR: '.$e->getMessage()."\n";
		}
	}
}
function runOne() {
	global $volatility, $emaBuyShort, $emaSellShort, $emaBuyLong, $emaSellLong, $smaBuyShort, $smaSellShort, $smaBuyLong, $smaSellLong, $currency, $smaEmaMix, $tradeAtAtrBuy, $tradeAtAtrSell, $tradeHistory, $argvHasBeenRun, $stopGainMultiplier, $stopGain;
	$str='';
	$sell=0;
	$buy=0;

	$history=getProductHistoricRates('LTC-'.$currency, 200); // get some data
	$avg=$history[0][4]; // get current coin value;
	$chandelierStop=$history[0][2]-$history[0][7]*$volatility; // high - ATR*volitility
	$history[0][10]='';
	if (!$argvHasBeenRun) {
		if (isset($GLOBALS['argv'][1])) {
			switch ($GLOBALS['argv'][1]) {
				case 'buy': // {
					buy($avg);
				break; // }
				case 'sell': // {
					sell($avg);
				break; // }
			}
		}
		$argvHasBeenRun=1;
	}
	$str.='Close:'.$avg
		.', Chandelier:'.sprintf('%.02f', $chandelierStop)
		.', SMA Buy:'.sprintf('%.02f', $history[0][9][$smaBuyShort]).'|'.sprintf('%.02f', $history[0][9][$smaBuyLong]).'|'.sprintf('%.02f', $history[0][9][$smaBuyLong]-$history[0][9][$smaBuyShort]);
	if ($smaEmaMix&1) {
		$str.=', EMA Buy:'.sprintf('%.02f', $history[0][11][$emaBuyShort]).'|'.sprintf('%.02f', $history[0][11][$emaBuyLong]).'|'.sprintf('%.02f', $history[0][11][$emaBuyLong]-$history[0][11][$emaBuyShort]);
	}
	$str.=', SMA Sell:'.sprintf('%.02f', $history[0][9][$smaSellShort]).'|'.sprintf('%.02f', $history[0][9][$smaSellLong]).'|'.sprintf('%.02f', $history[0][9][$smaSellLong]-$history[0][9][$smaSellShort]);
	if ($smaEmaMix&2) {
		$str.=', EMA Sell:'.sprintf('%.02f', $history[0][11][$emaSellShort]).'|'.sprintf('%.02f', $history[0][11][$emaSellLong]).'|'.sprintf('%.02f', $history[0][11][$emaSellLong]-$history[0][11][$emaSellShort]);
	}
	$str.="\n";
	$tradeMade=0;

	// { sell
	if ($stopGain && $history[0][2]>=$stopGain) { // high in last minute triggered the stopGain
		$sell=sell($avg);
		$str.='SELL: stopGain triggered'."\n";
		$history[0][10]='sell (stopGain)';
		$tradeMade=1;
	}
	else if ($chandelierStop && $avg<=$chandelierStop) {
		$sell=sell($avg);
		$str.='SELL: current close is lower than Chandelier Exit. Cut your losses and wait for the next Buy signal'."\n";
		$history[0][10]='sell (Chandelier exit)';
		$tradeMade=1;
	}
	else if ($history[0][7]/$avg>=$tradeAtAtrSell) { // only allow trades if market is volatile enough to cover fees
		if (
			$smaEmaMix&2 // use EMA for sells
			&& $history[0][11][$emaSellShort]<$history[0][11][$emaSellLong]
			&& $history[1][11][$emaSellShort]>=$history[1][11][$emaSellLong]
		) { // MACD
			$str.='SELL: the short/long term moving averages have crossed over. EMA Long is higher now'."\n";
			$sell=sell($avg);
			$history[0][10]='sell (EMA)';
			$tradeMade=1;
		}
		else if (
			$smaEmaMix&8 // use SMA for sells
			&& $history[0][9][$smaSellShort]<$history[0][9][$smaSellLong]
			&& $history[1][9][$smaSellShort]>=$history[1][9][$smaSellLong]
		) { // MACD
			$str.='SELL: the short/long term moving averages have crossed over. SMA Long is higher now'."\n";
			$sell=sell($avg);
			$history[0][10]='sell (SMA)';
			$tradeMade=1;
		}
	}
	// }
	// { buy
	if (!$tradeMade && $history[0][7]/$avg>=$tradeAtAtrBuy) { // only allow trades if market is volatile enough to cover fees
		if (
			$smaEmaMix&1 // use EMA for buys
			&& $history[0][11][$emaBuyShort]>$history[0][11][$emaBuyLong]
			&& $history[1][11][$emaBuyShort]<=$history[1][11][$emaBuyLong]
		) { // EMA Crossover
			$str.='BUY: the short/long term moving averages have crossed over. EMA Short is higher now'."\n";
			$buy=buy($avg);
			$history[0][10]='buy (EMA)';
			$tradeMade=1;
		}
		else if (
			$smaEmaMix&4 // use SMA for buys
			&& $history[0][9][$smaBuyShort]>$history[0][9][$smaBuyLong]
			&& $history[1][9][$smaBuyShort]<=$history[1][9][$smaBuyLong]
		) { // MACD
			$str.='BUY: the short/long term moving averages have crossed over. SMA Short is higher now'."\n";
			$buy=buy($avg);
			$history[0][10]='buy (SMA)';
			$tradeMade=1;
		}
	}
	// }

	$stopGain=$avg+$history[0][7]*$stopGainMultiplier; // low + ATR*multiplier
	$accountsByCurrency=getAccountsByCurrency();
	$report=date('Y-m-d H:i:s', $history[0][0]) // {
		.'	'.($accountsByCurrency[$currency]['balance']+($accountsByCurrency['LTC']['balance']*$avg))
		.'	'.$accountsByCurrency[$currency]['balance']
		.'	'.$accountsByCurrency['LTC']['balance']
		.'	'.$history[0][10]."\n"; // }
	if ($tradeMade) {
		$tradeHistory[]=$report;
	}
	return [
		'str'=>$str,
		'report'=>$report,
		'sell'=>$sell,
		'buy'=>$buy,
		'block'=>$history[0],
	];
}
