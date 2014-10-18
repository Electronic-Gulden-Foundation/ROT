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
Interface : REQUEST
getinfo :	eguldend -getinfo
tx : 		eguldend -getrawtransaction
xdo : 	follow the largest  OUT till unspend
xup :	follow the first IN till at root block
pu:		find public key that ends with last 4 characters
pub:	public key balans and inputs
block:	get blockheight from blockhash
b :		get block at height

It is easy for a client, using one input field, to determine if it concerns a block, a public key or a block.
You can test the results of these functions at efl.nl (to use pu prefix a . befor the 4 input-characters)

function checkkey is hard-coded for e-Gulden/Litecoin

**************************************************************************** */

require_once 'jsonRPCClient.php';

$root="/var/EFL";
if (!file_exists("$root/buildchain.conf")) {die("$root/buildchain.conf missing\n");}

list($user,$ww,$repeat,$mail,$servername)=explode("|",file_get_contents("$root/buildchain.conf"));
$efl = new jsonRPCClient("http://$user:$ww@localhost:21015/");
$mine=$efl->getmininginfo();

if (isset($_REQUEST['getinfo'])) {
	print_r($efl->getinfo()); echo "\n";
	print_r($efl->getmininginfo()); echo "\n";
}
if (isset($_REQUEST['tx'])) {
	$tx=$efl->getrawtransaction($_REQUEST['tx'],1);
	$tx['time']=date("d-m-y h:i:s",$tx['time']);
	$tx['blocktime']=date("d-m-y h:i:s",$tx['blocktime']);
	$b=$efl->getblock($tx['blockhash']);
	$tx['block']=$b['height'];
	print_r($tx);echo "\n";
	die();
}
if (isset($_REQUEST['xdo'])) { // waar gaat het grootste deel van de transactie naar toe
	$xdo=$_REQUEST['xdo'];
	$uit="<h2>$xdo</h2>";
	$uit.= "<table border=1><tr><td>tx</td><td>pub</td><td>n</td></tr>";
	for ($i=0;$i<100;$i++) {
		$tx=$efl->getrawtransaction($xdo,1);
		$old=$xdo;
		$uit.=  "<tr><td>$xdo</td>";
		$max="";$pubmax="";
		foreach ($tx['vout'] as $vout) {
			$address=$vout['scriptPubKey']['addresses'][0];
			$uit.=  "<td>$address</td><td>{$vout['value']}</td>";
			if ($vout['value']>$max) {$max=$vout['value'];$pubmax=$address;}
		}
		$uit.=  "</tr>";
		if ($max=="") {break;}
		$filename=getfile("/var/efl/pub/",$pubmax);
		$lines=file($filename);
		foreach ($lines as $line) {
			$n=count($lines);
			if (strpos($line,":{$xdo}:")>0) {
				$f=explode(":",$line);
				if (count($f)<5) {
					$uit.=  "<tr><td>Unspend</td></tr>";
				} else {
					$xdo=$f[4];
				}
				break;
			}
		}
		if ($old==$xdo) {break;}
	}
	echo $uit;
	file_put_contents("log","$uit\n",FILE_APPEND);
	die();
}
if (isset($_REQUEST['xup'])) {
	$xup=$_REQUEST['xup'];
	echo "<h2>$xup</h2>";
	echo "<table border=1><tr><td>in</td><td>out</td><td>n</td></tr>";
	for ($i=0;$i<100;$i++) {
		$tx=$efl->getrawtransaction($xup,1);	
		$xup=$tx['vin'][0]['txid'];
		echo "<tr><td>$xup</td>";
		foreach ($tx['vout'] as $vout) {
			if ($vout['value']>50) {echo "<td>{$vout['scriptPubKey']['addresses'][0]}</td><td>{$vout['value']}</td>";}
		}
		echo "</tr>";
	}
	die();
}
if (isset($_REQUEST['pu'])) {
	$key=$_REQUEST['pu'];
	$root="$root/pub/";
	$dir="$root".substr($key,-2);
	if (!file_exists($dir)) {return "";}
	$dir=$root.substr($key,-2)."/".substr($key,-4,2);
	if (!file_exists($dir)) {return "";}
	$result=glob($dir."/*");
	echo "<h2>Public key .$key</h2>";
	echo "<table border=1>";
	foreach ($result as $key) {
		if (strpos($key,".")===false) {
			$f=explode("/",$key);
			$key=$f[count($f)-1];
			echo "<tr><td>$key</td></tr>";
		}
	}
	echo "</table>";
	die();
}
if (isset($_REQUEST['pub'])) {
	if (!checkkey($_REQUEST['pub'])) {die("Ongeldige public key");}	
	$root="$root/pub/";
	$filename=getfile($root,$_REQUEST['pub']);
	$h=fopen($filename,"r");
	$n=0;$som=0;$som_uit=0;
	while ($line=fgets($h)) {
		$f=explode(":",$line);
		$n++;
		$som+=$f[3];
		if (count($f)>4) {$som_uit+=$f[3];}
	}
	$balans=$som-$som_uit;
	if (isset($_REQUEST['balans'])) {
		echo "$balans";
		die();
	}
	echo "<table border=1><tr><td>n</td><td>som_in</td><td>som_uit</td><td>balans</td></tr>";
	echo "<tr><td>$n</td><td>$som</td><td>$som_uit</td><td>$balans</td></tr></table>";
	fclose($h);
	echo file_get_contents($filename);
	die();
}
if (isset($_REQUEST['block'])) {
	$b=1*$_REQUEST['block'];
	$block=$efl->getblockhash($b);
	$result=$efl->getblock($block);
	echo "<p>Generatie : <span>".date("d-m-y h:i:s",$result['time'])."</span></p>";
	print_r($result);
	die();
}
if (isset($_REQUEST['b'])) {
	echo "<table border=1><tr><td>Height</td><td>Time</td><td>Delta (s)</td></tr>";
	$b=1*$_REQUEST['b'];
	$block=$efl->getblockhash($b);
	for ($i=0;$i<400;$i++) {
		$item=$efl->getblock($block);
		$block=$item['nextblockhash'];
		if ($i==0) {$delta=0;} else {$delta=$item['time']-$old;}
		$old=$item['time'];
		$tx=$efl->getrawtransaction($item['tx'][0],1);
		$address=$tx['vout'][0]['scriptPubKey']['addresses'][0];
		echo "<tr><td>".$item['height']."</td><td>".date("d-m-Y h:i:s",$item['time'])."</td><td>$delta</td><td>$address</td></tr>";
	}
	echo "</table>";
}
if (isset($_REQUEST['blockhash'])) {	print_r($efl->getblockhash(1*$_REQUEST['blockhash'])); echo "\n"; }
echo "</pre>";


function getfile($root,$key){
	global $root;
	$dir="$root".substr($key,-2);
	if (!file_exists($dir)) {return "";}
	$dir=$root.substr($key,-2)."/".substr($key,-4,2);
	if (!file_exists($dir)) {return "";}
	return "$dir/$key";
}
function appendHexZeros($inputAddress, $hexEncodedAddress){
	for ($i = 0; $i < strlen($inputAddress) && $inputAddress[$i] == "1"; $i++) { $hexEncodedAddress = "00" . $hexEncodedAddress;	}
	if (strlen($hexEncodedAddress) % 2 != 0) { $hexEncodedAddress = "0" . $hexEncodedAddress;}
	return $hexEncodedAddress;
}
function encodeHex($dec){
        $chars="0123456789ABCDEF";
        $return="";
        while (bccomp($dec,0)==1){
                $dv=(string)bcdiv($dec,"16",0);
                $rem=(integer)bcmod($dec,"16");
                $dec=$dv;
                $return=$return.$chars[$rem];
        }
        return strrev($return);
}
function base58_decode($base58){
	$origbase58 = $base58;
	$base58chars = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
	$return = "0";
	for ($i = 0; $i < strlen($base58); $i++) {
		$current = (string) strpos($base58chars, $base58[$i]);
		$return = (string) bcmul($return, "58", 0);
		$return = (string) bcadd($return, $current, 0);
	}
	return $return;
}
function checkkey($inputAddress) {
    if ((substr($inputAddress,0,1)!="L") || (strlen($inputAddress)<27) || (strlen($inputAddress)>34)) {return false;}
    $decodedAddress = base58_decode($inputAddress);
    $hexEncodedAddress = encodeHex($decodedAddress);
    $embeddedCheckSum = substr($hexEncodedAddress,-8);
    $hexEncodedAddress = appendHexZeros($inputAddress, $hexEncodedAddress);
    $encodedAddress = substr($hexEncodedAddress, 0, strlen($hexEncodedAddress) - 8);
    $binaryAddress = pack("H*" , $encodedAddress);
    $hashedAddress = strtoupper(hash("sha256", hash("sha256", $binaryAddress, true)));
    $checkSumAddress = substr($hashedAddress, 0 ,8);
    if ($embeddedCheckSum==$checkSumAddress) {return true;} else {return false;}
}

?>



