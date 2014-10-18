<?php
/* ****************************************************************************
MIT License:

Copyright (C) 2014 Gerrit Oerlemans, GOSoftware

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included
in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES
OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.

****************************************************************************
Known issue :
- Whenever the processing of a block is unterupted (reboot, crash) the processing will restart next time.
The next time some inputs will appear as double_spend; It only appears on servers with regular communication problems
I have implemented a recovery procedure by restarting with the last processed IN/OUT (hanging)
Still a single IN or OUT may have been partially processed
That's why I interupt processing and send you a mail to let you investigate such a situation and the chain-health;
Stop cron and manually set $repeat to 1 to rebuild the last block
Probably the interupt can be removed later.

**************************************************************************** */

require_once 'jsonRPCClient.php';

$root="/var/EFL";
if (!file_exists("$root/pub")) {@mkdir("$root/pub",0755);}
if (!file_exists("$root/tx")) {@mkdir("$root/pub",0755);}
if (!file_exists("$root/buildchain.conf")) {die("$root/buildchain.conf missing\n");}

/* buildchain.conf
  * Third parameter $repeat tells how many blocks are allowed to be processed in one run (* to continue to current blockheight)
 */
list($user,$ww,$repeat,$mail,$servername)=explode("|",file_get_contents("$root/buildchain.conf"));

if (file_exists("$root/lock")) {die("Already running or previous exit with error status");}
touch("$root/lock");  // Lock

$blockstate=""; $tx_count="";
if (file_exists("$root/blockstate")) { // Interupted during processing
	if ($repeat!=1) {
		$subject="Blockchain processing halted on $servername (".date("d-m-Y").")";
		$message=file_get_contents("$root/blockstate");
		$headers = 'MIME-Version: 1.0 ' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		mail($mail, $subject, $message, $headers);
		die("Processing halted due to interupted block");
	}
	list($tx_hanging,$hanging_state,$tx_nhanging)=explode("|",file_get_contents("$root/blockstate"));
}

error_reporting(E_ALL);
$start=microtime(true); 

// Start with block 1 or last block processed
if (file_exists("$root/block")) {$block=file_get_contents("$root/block")+1;} else {$block=1;}
try {
	$efl = new jsonRPCClient("http://$user:$ww@localhost:21015/");
	$mine=$efl->getmininginfo();
} catch(Exception $e0) {
	DBG("RPC-initialisation or get-info failed");
	unlink("$root/lock");  // Release
	die("RPC-initialisation or get-info failed");
}

$blocks=$mine['blocks'];
DBG2("$user,$ww,$repeat,$mail,$servername");
DBG2("From $block to $blocks\n");
$error_count=0;$nrepeat=0;
if ($repeat=="*") {$repeat=-1;}
for ($blok=$block;$blok<=$blocks;$blok++) {
	if ($repeat==$nrepeat) {break;}
	$nrepeat++;
	if ($block%1000==1) {DBG2($block."\n");}
	try {
		$b=$efl->getblock($efl->getblockhash(1*$block));
		if ($b['confirmations']<4 ){
			// This is the most important 51% attack probleem;
			// If you wait longer the explorer becomes less up-to date
			// TODO :
			// - wait 20 blocks, but build a buffer of the 20 most recent blocks using blocknotify that runs in parallel
			// - Detect forks
//			DBG2($blok.": ".$block['confirmations']."\n");
			break;
		}
		// Buffer the input transactions
		$TX=$b['tx'];
		foreach ($TX as $txi =>$tx) {
			$TX_set[$txi]=$efl->getrawtransaction($tx,1);
		}
		try {
			$n=0;
			foreach ($TX as $txi =>$tx) {
				$state="INPUT";
				$TX_raw=$TX_set[$txi];
				$VIN=$TX_raw['vin'];        //<...b191>
				// process each input
				foreach ($VIN as $vini =>$vin) {
					file_put_contents("$root/blockstate","$tx|$state|$vini");
					if ($hanging_state=="" || (($hanging_state==$state) && ($vini==$tx_nhanging))) {
						$hanging_state="";
						if (isset($vin['txid'])) { // process spend output  (unless root-transaction) (txid;vout)
							$filename=getfile("$root/tx/",$vin['txid']);  //<0:...0553
							$vout=$vin['vout'];
							if (!file_exists($filename)) {
								DBG("Lost output file:$block:$txi:{$vin['txid']}:$vout");
							} else {
								// format <out|xut>:<n>:<tx>:<value>  n=sequence number of out; the x means not spend yet
								$file=file_get_contents($filename);
								$x=strpos($file,"out:$vout:");  // Jump to output linenr=vout (You could add the transaction that causes this ($TX)) but for now we do this in  pubkey
								if ($x===false) {
									$x=strpos($file,"xut:$vout:");
									if ($x!==false) { 
										DBG("Double spend :$block:$txi:{$vin['txid']}:$vout"); // Or the block was already (partly) processed
									}else{
										DBG("Lost output :$block:$txi:{$vin['txid']}:$vout");
									}
								} else {
									$pub=explode(":",substr($file,$x));  // cut the whole file from this point and read the pubkey
									$pubkey=$pub[2];
									$value=$pub[3]; // vout we know already
									$file[$x]="x"; // Mark this line/transaction as Spend ()
									file_put_contents($filename,$file);
									$filename=getfile("$root/pub/",$pubkey);
									if (!file_exists($filename)) {
										DBG("!Lost pub file:$block:$tx:$txi:{$vin['txid']}:$vout:$pubkey");
									} else {
										// format <in|xn>:<n>:<tx>:<value>[:txout:vinnr]
										// When the input is spend we mark the transaction that is causing it. This way we know where is is going
										// ... save memory because there will be large pubkeys
										$h=fopen($filename,"r");$ok=false;
										$o=fopen($filename."_","w");
										while ($line=fgets($h)) {
											if (strpos($line,":{$vin['txid']}:")>0) {
												$line=substr($line,0,-1).":$tx:$txi\n";
												$ok=true;
											}
											fputs($o,$line);
										}
										fclose($h);
										fclose($o);
										unlink($filename);
										rename($filename."_",$filename);
										if (!$ok) {DBG("Lost input :$block:$txi:{$vin['txid']}:$vout:$pubkey");}
									}
								}
							}
						}
					}
				}
				
				// Process each output
				$state="OUTPUT";
				$VOUT=$TX_raw['vout'];$nout=0;
				foreach ($VOUT as $vouti => $vout) {
					if (isset($vout['value']) && isset($vout['scriptPubKey'])) { // process new input
						file_put_contents("$root/blockstate","$tx|OUTPUT|$vouti");
						if ($hanging_state=="" || (($hanging_state==$state) && ($vouti==$tx_nhanging))) {
							$hanging_state="";
							$pub=$vout['scriptPubKey'];
							if (substr($pub['type'],0,6)!='pubkey') {
								DBG("Unknown output :$block:$txi:{$vout['n']}");
							} else {
								if (count($pub['addresses'])>1) {
									DBG("Multiple output ? :$block:$txi:{$vout['n']} don't know what to do");
								} else {
									$filename=getfile("$root/tx/",$tx);
									$address=$pub['addresses'][0];
									file_put_contents($filename,"out:{$vout['n']}:$address:{$vout['value']}\n",FILE_APPEND);
									$filename=getfile("$root/pub/",$address);
									file_put_contents($filename,"in:{$vout['n']}:$tx:{$vout['value']}\n",FILE_APPEND);
								}
							}
						}
					}
					$nout++;
				}
			}
			file_put_contents("$root/block",$block);
			unlink("$root/blockstate");
			$block+=1;
			$error_count=0;
		} catch(Exception $e2) {
			// Een fout die niet door RPC/daemon veroorzaakt wordt
			// Ga toch maar door maar de keten is waarschijnlijk niet meer in orde
			DBG("Something is wrong at block $block !  State $state");
			if ($state=="INPUT") { DBG("tx:".$vin['txid']); }
			if ($state=="OUTPUT") { DBG("n:$nout"); }
		}
	} catch(Exception $e1) {
		// Two types of errors : getblock of getrawtransaction
		// I suspect because daemon is "busy" OR a network-error;
		//
		// Op windows heb ik er meer last van dan op unix;
		// Op de kleine Virtual server en de dedicated geen enkel probleem, maar daar draaide geen http
		// op de e-gulden-server om de 15000 blokken
		$error_count++;
		sleep(1);
		if ($error_count<10) {$blok--;
		} else {
			DBG("retry block $block");
			break;
		}
		DBG("retry block $block");
	}
}
DBG2("\n".(microtime(true)-$start).":$block:$blocks\n");

unlink("$root/lock");  // Release

function DBG($txt) {
	global $root;
	$stamp=date("d-m-y h:i:s ");
	file_put_contents("$root/build.log","$stamp $txt\n",FILE_APPEND);
}
function DBG2($txt) {
	global $root;
	$stamp=date("d-m-y h:i:s ");
	file_put_contents("$root/new.log","$stamp $txt\n",FILE_APPEND);
}
function getfile($root,$key){ // ...abcd => $root/cd/ab
	$dir=$root.substr($key,-2);
	if (!file_exists($dir)) {mkdir($dir,0755);}
	$dir=$root.substr($key,-2)."/".substr($key,-4,2);
	if (!file_exists($dir)) {mkdir($dir,0755);}
	return "$dir/$key";
}
?>
</pre>
OK