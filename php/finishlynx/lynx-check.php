<?php
/**
/**
@file		        lynx-check.php
@copyright		Copyright 2016 Dilltree Inc.
@version			1.0
@license 			See README.txt
@author			Jeremy Dill
@desc  			Given a FinishLynx file with format of 'place','bib','lane','lname','fname','team','time' as input, this script will compare places and times against RTRT.me by making a call for each tag.  It will output a report of the differences as a .csv.
@usage 

lynx-check.php [event name] [finish point name] [path to lynx file] [path to output file] [admin app id for RTRT API] [admin token id for RTRT API]

ADMIN API and TOKEN is available in the RTRT.me console in your Account.

**/

if (count($_SERVER["argv"])!==7)  die("invalid options: \n usage: lynx-check.php [event name] [finish point name] [path to lynx file] [path to output file] [admin app id for RTRT API] [admin token id for RTRT API] ");

if(!empty($_SERVER["argv"][1]))	$event_name=$_SERVER["argv"][1];
if(!empty($_SERVER["argv"][2]))	$point=$_SERVER["argv"][2];
if(!empty($_SERVER["argv"][3]))	$lynxfile=$_SERVER["argv"][3];
if(!empty($_SERVER["argv"][4]))	$output=$_SERVER["argv"][4];
if(!empty($_SERVER["argv"][5]))	$appid=$_SERVER["argv"][5];
if(!empty($_SERVER["argv"][6]))	$token=$_SERVER["argv"][6];

$host = 'api-dev2.rtrt.me';

// RTRT.me SERVER
$secure=true;

try{
	if(!is_file($lynxfile)) throw new Exception('Could not locate file '.$lynxfile);
	if (($handle = fopen($lynxfile, "r")) !== FALSE) {
		$c=0;
		$headers=array('place','bib','lane','lname','fname','team','time'); // lynx file headers
		while (($d = fgetcsv($handle, 10000, ",")) !== FALSE) {
			//assume no heading row in lynx file.
			//if($c==0){
			//	$headers=$d;
			//}else{
				foreach ($headers as $k => $v) {
					$boad[$d[1]][$v]=$d[$k];
				}
			//}
			$c++;
		}
		fclose($handle);
	}

	//compare file to API
	$tc=0;
	$td=0;
	$nc=0;
	$insplit='pid,tag,bib,time,waveTime,netTime,timeOfDay,legTime,paceAvg,isStart,isFinish,label,pace,results';
	foreach ($boad as $k => $v) {
		// get rtrt data
		if($k==0) continue;
		//if($i++>10) break;
		$path='/events/'.$event_name.'/profiles/'.$k.'/splits/'.$point;
		$qs='insplit='.$insplit.'&notslag=1&nohide=1&gunplace=1';


		ce('checking '.$k);
		$request=call($path,$qs);
		$split=$request['list'][0];
		if($request['error'] && $request['error']['type']!=='no_results') throw new Exception(json_encode($request));
		//print_r($split);
		if($split['time'] || $boad[$k]['time']){
			//compare times
			//if(empty($boad[$k]['time'])) 
			if(!$split['waveTime']) $out[$k]['rtrt_time']=false;
			else $out[$k]['rtrt_time'] = $split['waveTime'];
			$out[$k]['lynx_time']=$boad[$k]['time'];
			if($split['waveTime']) $out[$k]['rtrt_sec']=tosec($split['waveTime']);
			if($boad[$k]['time']) $out[$k]['lynx_sec']=tosec($boad[$k]['time']);
			if($split['results']) $out[$k]['rtrt_place']=$split['results']['gunplace']['p'];
			if($boad[$k]['place']) $out[$k]['lynx_place']=$boad[$k]['place'];
			//calculate diff
			if($out[$k]['lynx_sec'] && $out[$k]['rtrt_sec']){
				$out[$k]['diff_place']=$out[$k]['rtrt_place']-$out[$k]['lynx_place'];
				$d=round($out[$k]['rtrt_sec']-$out[$k]['lynx_sec'],2);
				$out[$k]['diff_time']=$d;
				if($d==0) unset($out[$k]);
				//sum differences
				$td+=$d;
				$tc++;
			}else{
				$out[$k]['diff']=false;
				$nc++;
			}
			ce(json_encode($out[$k]));
		}else{
			$out[$k]=false;
		}
	}

	// build headings
	$headings=array('tag');
	foreach($out AS $tag=>$record){
		if(is_array($record)){
			$keys=array_keys($record);
			foreach ($keys AS $h) if(!in_array($h,$headings)) $headings[]=$h;
			#ce('built headings');
			break;
		}
	}

	$temp_csv = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+');

	//make csv
	//heading
	fputcsv($temp_csv, $headings);
	fseek($temp_csv, 0);
	$csv= fgets($temp_csv);
	foreach($out AS $tag=>$record){
		unset($row);
		fseek($temp_csv, 0);
		$record['tag']=$tag;
		foreach($headings AS $field){
			$row[$field]=$record[$field]; //build full row, even if a field doesnt exist in this row
		}
		fputcsv($temp_csv, $row);
		fseek($temp_csv, 0);
		$csv.= fgets($temp_csv);
	}
	$csv.=("\n\nAverage Time Diff:".round($td/$tc));
	file_put_contents($output,$csv);

} catch (Exception $e) {
	die('Error occured: '.$e->getMessage());
}

/**
* METHOD call
*
* Make a call to the RTRT API.
*
* @param str $path - RTRT.me API path
* @param str $qs - Querystring params (don't include token and appid, will get from global vars)
* @return multi $res - multi dimensional array with result
*/
function call($path,$qs=''){
    global $host,$secure,$appid,$token;
    
    if($secure) $site="https://".$host;
    else $site="http://".$host;
    if(!empty($appid)) $qs.='&appid='.$appid;
    if(!empty($token)) $qs.='&token='.$token;
    $url=$site.$path."?".$qs;

    #ce("Calling $url");
    $opts = array(
        CURLOPT_URL =>$url,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 50,
        CURLOPT_SSL_VERIFYPEER => FALSE,
        CURLOPT_SSL_VERIFYHOST => FALSE
    );

    $ch = curl_init();
    curl_setopt_array($ch,$opts);
    if( ! $result = curl_exec($ch))
    {
            return curl_error($ch).' at '.$url;
    }
    curl_close($ch);

    $res=json_decode($result,1);

    return $res;
}

function tosec($t){
	$d=explode('.',$t);
	$tt=explode(':', $d[0]);
	$s=$tt[sizeof($tt)-1];
	$m=$tt[sizeof($tt)-2];
	$h=0;
	if($d[sizeof($tt)-3]) $h=$tt[sizeof($tt)-3];
	$ts=$s+60*$m+3600*$h;
	$ts.='.'.$d[1];
	return $ts;
}

/**
* METHOD ce
* CLIENT ECHO
* @param string OR array $msg : What to echo.
*/
function ce($msg){
	if(is_array($msg)) $msg=json_encode($msg);
	$msg=trim($msg)."\r\n";
	echo $msg;
}
?>