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

**************************************************************************** */
error_reporting(E_ALL);
/* buildchain.conf
  * $repeat: tells how many blocks are allowed to be processed in one run (* to continue to current blockheight)
  * $conf: number of confirmations before accepting block
 */
list($user,$ww,$port,$repeat,$mail,$servername,$conf)=explode("|",file_get_contents("$root/buildchain.conf"));

$start=microtime(true); 
$root="/var/erc";
define("ROOT",$root);

require_once 'jsonRPCClient.php';
try {
	$efl = new jsonRPCClient("http://$user:$ww@127.0.0.1:$port/");
	$balance=$efl->getbalance("");
} catch (Exception $e) {
	$error=" [".$e->getMessage()."]";
	echo "\No contact (JSON) ".$error; 
	die("");
}

if (!file_exists(ROOT."/sema")) {
	file_put_contents(ROOT."/sema","lock");
} else {
	if (filesize(ROOT."/sema")==$conf) {die("sema");}
	file_put_contents(ROOT."/sema","lock");
}

if (file_exists(ROOT."/block")) {$block=file_get_contents(ROOT."/block")+1;} else {$block=1;}
$mine=$efl->getmininginfo();
$blocks=$mine['blocks'];
echo "From $block to $blocks\n";
for ($blok=$block;$blok<=$blocks;$blok++) {
	if (file_exists(ROOT."/stop")) {unlink(ROOT."/sema");die();}
	
	if ($block%1000==1) {echo $block.":".date("H:i:s")."\n";}
	
	$b=$efl->getblock($efl->getblockhash(1*$block));
	if ($b['confirmations']<$conf ){ // || (microtime(true)-$start)>30
		break;
	}
	$once=true;
	try { $TX=$b['tx'];
		foreach ($TX as $txi =>$tx) {
			try {$TX_raw=$efl->getrawtransaction($tx,1);
				$VIN=$TX_raw['vin'];
				foreach ($VIN as $vin) {   // process each input
					if (isset($vin['txid'])) { // process spend output  (unless root-transaction) (txid;vout)
						$filename=getfile(ROOT."/tx/",$vin['txid']); 
						$vout=$vin['vout'];
						if (!file_exists($filename)) {
							DBG("Lost output file:$block:$txi:{$vin['txid']}:$vout");
						} else {
							// format <out|xut>:<n>:<tx>:<value>  n=seq out
							$file=file_get_contents($filename);
							$x=strpos($file,"out:$vout:");  // Jump to output linenr=vout ($TX is added in pubkey file)
							if ($x===false) {
								$x=strpos($file,"xut:$vout:");
								if ($x!==false) { 
									DBG("Double spend :$block:$txi:{$vin['txid']}:$vout"); // Or previous run was interupted while block being geprocessed
								}else{
									DBG("Lost output :$block:$txi:{$vin['txid']}:$vout");
								}
							} else {
								$pub=explode(":",substr($file,$x));  // cut the entire file from here and read pubkey
								$pubkey=$pub[2];
								$value=$pub[3]; // and vout is already known
								$file[$x]="x"; // Spend marker
								$x=strpos($file,"out:");
								if ($x===false) {unlink($filename);} else {file_put_contents($filename,$file);}
								$filename=getfile(ROOT."/pub/",$pubkey);
								if (!file_exists($filename)) {
									DBG("!Lost pub file:$block:$tx:$txi:{$vin['txid']}:$vout:$pubkey");
								} else {
									// format <in|xn>:<n>:<tx>:<value>[:txout:vinnr]
									// If input is being spent we remember in which tx. This way we know where it went
									$lines=file($filename);$rest="";$ok=false;
									foreach ($lines as $line) {
										if ($ok) {
											$rest.=$line;
										} else {
											if (strpos($line,":{$vin['txid']}:")>0) {
												$line=trim($line).":$tx:$txi\n";
												file_put_contents($filename.".x",$line,FILE_APPEND);
												$ok=true;
											} else {
												$rest.=$line;
											}
										}
									}
									if ($rest=="") {
										unlink($filename);
									}else{
										file_put_contents($filename,$rest);
									}
									if (!$ok) {DBG("Lost input :$block:$txi:{$vin['txid']}:$vout:$pubkey");}
								}
							}
						}
					}
				}
				
				$VOUT=$TX_raw['vout'];
				foreach ($VOUT as $vout) {
					if (isset($vout['value']) && isset($vout['scriptPubKey'])) { // process new input
						$pub=$vout['scriptPubKey'];
						if ((substr($pub['type'],0,6)!='pubkey')&&($pub['type']!='checklocktimeverify')&&($pub['type']!='scripthash')) {
							if ($pub['type']!='nulldata'){
								DBG("Unknown output :$block:$txi:{$vout['n']}");
							}
						} else {
							if (count($pub['addresses'])>1) {
								DBG("Multiple output ? :$block:$txi:{$vout['n']} don't know what to do");
							} else {
								$filename=getfile(ROOT."/tx/",$tx);
								$address=$pub['addresses'][0];
								file_put_contents($filename,"out:{$vout['n']}:$address:{$vout['value']}\n",FILE_APPEND);
								$filename=getfile(ROOT."/pub/",$address);
								file_put_contents($filename,"in:{$vout['n']}:$tx:{$vout['value']}\n",FILE_APPEND);
							}
						}
					}
				}
			} catch(Exception $e2) {DBG("tx not accessable:$block:$txi");}		
		}
		file_put_contents(ROOT."/block",$block);
		$block+=1;
	} catch(Exception $e1) {DBG("No tx:$block");}
}
//echo ("\n".microtime(true)-$start).":$block:$blocks\n";

file_put_contents(ROOT."/sema","release");
function trap($wie,$blok, $tx) {
		$to="monitor@domain.com";
		$subject="ALARM: TXout movement $wie";
		$message="<p>signaled in blok: $blok<br>Txin:$tx";
		$mime_boundary="==Multipart_Boundary_x".md5(mt_rand())."x";
		$headers = "From: noreply@e-gulden.org\r\n" .
			"MIME-Version: 1.0\r\n" .
			"Content-Type: multipart/mixed;\r\n" .
			" boundary=\"{$mime_boundary}\"";
		$message = "This is a multi-part message in MIME format.\n\n" .
			"--{$mime_boundary}\n" .
			"Content-Type: text/html; charset=\"iso-8859-1\"\n" .
			"Content-Transfer-Encoding: 7bit\n\n" .$message. "\n\n";
		$message.="--{$mime_boundary}--\n";
		mail($to, $subject, $message, $headers);
}
function DBG($txt) {file_put_contents(ROOT."/build.log","$txt\n",FILE_APPEND);}
function DBG2($txt) {file_put_contents(ROOT."/new.log","$txt\n",FILE_APPEND);}
function getfile($root,$key){
	$dir=$root.substr($key,-2);
	if (!file_exists($dir)) {mkdir($dir,0755);}
	$dir=$root.substr($key,-2)."/".substr($key,-4,2);
	if (!file_exists($dir)) {mkdir($dir,0755);DBG2($dir);}
	return "$dir/$key";
}
?>
