#!/usr/bin/php
<?php
/**
*
* @file		        ipico-rtrt.php
* @copyright		Copyright 2013 Dilltree Inc.
* @version			1.2
* @license 			See README.txt
* @author			Jeremy Dill
*
* usage: ipico-rtrt.php [event name] [location name] [ipico file path] [rewind (1 to prevent resume)] [logfile]
**/

/*=========CONFIGURATION=======*/

$silent = false;

$appid='[your appid here]';
$token='[your token here]';


// RTRT.me AUTH STRING
//auth~<client software name>~<application id>~<application token>\r\n
$auth = 'auth~IpicoStreamer Version '.$version.'~'.$appid.'~'.$token;

// RTRT.me SERVER
$host = 'rts.rtrt.me';
$port = 3490;
$timezone='America/Chicago'; // not important

/*=========END CONFIGURATION=======*/

$version = '1.2';
date_default_timezone_set($timezone);
ini_set('error_reporting', E_ALL & ~E_NOTICE);

if(!empty($_SERVER["argv"][1]))	$eventname=$_SERVER["argv"][1];
if(!empty($_SERVER["argv"][2]))	$loc=$_SERVER["argv"][2];
if(!empty($_SERVER["argv"][3]))	$file=$_SERVER["argv"][3];
if(!empty($_SERVER["argv"][4]))	$reset=$_SERVER["argv"][4];

if (empty($eventname)||empty($loc)||empty($file)) die("invalid options: \n usage: [event name] [location name] [ipico file path] [rewind (1 to prevent resume)]\n");

if(!is_file($file)) die("Could not find file: [ $file ].  Please check path.\n");

// IF SET, WILL STORE CONNID IN FILE FOR RESUME PURPOSES.  WHEN STARTING UP THIS SCRIPT AGAIN, IF FILE EXISTS, IT WILL USE VALUE IN FILE AS CONNECTION ID
$key=md5($eventname.'-'.$loc.'-'.$file);

$resume_file=sys_get_temp_dir().'connid-'.$key;


// READ FILE NOW AND GET RESUME VALUE OR SET TO 0
if (empty($reset) && is_file($resume_file)) {
    $farr=file($resume_file);
    $connid=$farr[0]; //first line of file.
    $resume=true;
} else {
    $connid=md5($eventname.'-'.$loc.'-'.$file.'-'.time()); // make a new one
	if($resume_file) file_put_contents($resume_file,$connid) or die("can't open file");
}

//init~<event_name>~<location list>~<connection id>\r\n
$init = 'init~'.$eventname.'~'.$loc.'~'.$connid;
ce('=======================================');
ce('iPico-to-RTRT script version '.$version);
ce('=======================================');
if($resume) ce('Resuming with connection id:'.$connid);
if(!$silent) anykey("About to start stream for,\n\nevent: ".$eventname.", location: ".$loc.", file: ".$file."\n\nPress enter to continue, or ctrl-c to quit...");

try{
	
	// open client connection
	ce('Trying Connect to '.$host.'...');

	$sp = fsockopen ($host, $port, $errno, $errstr);

	if (!$sp){
		ce("Error: could not open socket connection");
		exit();
	} else {
		ce('Connected to '.$host);
		
		#if ($write_to_file) echo "Output to ".$write_to_file."\n";
		
		//authorize
		ce('Trying to Authenticate...');
		send($sp, $auth);
		$resp = getResp($sp);
		if(!$resp['ack']['resp']['success']) {
			ce($resp);
			exit();
		}
		ce('Authentication Successful');

		// init
		ce('Trying Initialization...');
		send($sp, $init);
		
		$resp = getResp($sp);
		if(!$resp['ack']['resp']['success']) {
			ce($resp);
			exit();
		}
		$resume_seqnr=(int) $resp['ack']['resp']['success']['msg'];

		ce('Initialization Complete');

		ce('Starting Stream...');
		if($resume_seqnr>0) ce('Resuming from seqnr '.$resume_seqnr);

		$seqnr = 0;
		$size = 0;
		stream_set_timeout($sp, 1);	

		while(!feof($sp)){

			clearstatcache();
	        $currentSize = filesize($file);

	        if ($size != $currentSize) {
				// FILE CHANGED, GET ANY NEW DATA FROM FILE
		        $fh = fopen($file, "r");
		        fseek($fh, $size); //start where we left off.
				#ce('start at '.$size);
		        while ($buffer = fgets($fh)) {
		        	$row=trim($buffer);
					$seqnr++;

		            if (empty($row)) {
		            	// note, on seek, first row will always be blank--getting \r\n of last record: ignore
		            	if($buffer!=="\r\n") ce('!!!WARNING--EMPTY ROW IN FILE - '.$seqnr.'['.$buffer.']');
						$seqnr--;
		            	continue;
		            }

		            if($seqnr < $resume){
		            	// skipping to resume point
		            	continue;
		            }

					$tag=substr($row, 4,12);
					$time=substr($row, 20,14);
					if(strlen($tag)!==12 || strlen($time)!==14) {
						#ce('!!!WARNING--INCOMPLETE ROW IN FILE: ['.$row.']');
						$seqnr--;
						break;
						//break;
					}
					if($seqnr <= $resume_seqnr) {
						$size = ftell($fh);
						continue;
					}
					$rtime=parseTime($time);

					$read='read~'.$tag.'~'.$loc.'~'.$rtime.'~'.$seqnr."\r\n";

					if($idle) {
						ce('found new records');
						$idle=0;
					}

					ce('sending '.$read);

		        	$size = ftell($fh);
		        	send($sp, $read);
		        }
	       		fclose($fh);
	        }

			// dual purpose, checks for commands and also puts a pause into loop
			$action=checkForCommands($sp);
			switch($action){
				case 'pause':
				break;
				case 'terminate':
				break;
				case 'restart':
				break;
				case 'error':
					ce("Got an error..please verify that we are streaming correct file!");
					#break 2;
				break;
			}

			// IDLE MESSAGE
	        echo ".";
	        $idle++;
	        if($idle % 10 ===0) {
	            ce("Idle, nothing new to send. Doing ping.");
	            send($sp, "ping");
	            $idle=0;
	        }
		}
		send($sp,'goodbye');
		ce(getResp($sp));
		fclose ($sp);

	}
} catch(Exception $e) {
	ce($e->getMessage());
}
ce("completed");

/** =========================HELPERS=========================== **/

/**
* METHOD parseTime
* convert iPico time time of day.
* @param $time (iPico format)
*/
function parseTime($time,$iso=false){
	$yy=substr($time,0,2);
	$mm=substr($time,2,2);
	$dd=substr($time,4,2);
	$hr=substr($time,6,2);
	$mn=substr($time,8,2);
	$ss=substr($time,10,2);
	$hh=hexdec(substr($time,12,2)).'';
	if(strlen($hh)===1) $hh='0'.$hh;
	if($iso) return '20'.$yy.'-'.$mm.'-'.$dd.' '.$hr.':'.$mn.':'.$ss.'.'.$hh;
	else return $hr.':'.$mn.':'.$ss.'.'.$hh;
}

/**
* METHOD checkForCommands
* CHECK FOR COMMANDS FROM SERVER
* @param stream handle
*/
function checkForCommands($sp){
	$result = fgets ($sp);
	$result = trim($result);
	$item = json_decode($result, 1);

	$err="";
	// LOOK FOR ANY JSON DECODING ERRORS.
	switch(json_last_error())
	{
		  case JSON_ERROR_DEPTH:
			    $err = 'JSON ERROR - Maximum stack depth exceeded';
		  break;
		  case JSON_ERROR_CTRL_CHAR:
				$err = 'JSON ERROR - Unexpected control character found';
		  break;
		  case JSON_ERROR_SYNTAX:
				$err = 'JSON ERROR - Syntax error, malformed JSON';
				$err.= "\n[".$result."]\n";
		  break;
		  case JSON_ERROR_NONE:
		  		//OK
		  break;
	}
	if($err){
	    ce($err);
	}
	if(!empty($item['ack']['cmd'])){
		ce($item);
		switch($item['ack']['cmd']){
			case 'ping':
			break;
			default:
				$action='error';
			break;
		}
	}

	if(!empty($item['cmd'])){
		ce('Received command ['.$item['cmd'].']');
		switch($item['cmd']){
			case 'ping':
				fputs ($sp, "ack~ping\r\n");
			break;
			case 'terminate':
				fputs ($sp, "ack~terminate\r\n");
				$action='terminate';
			break;
			case 'pause':
				fputs ($sp, "ack~pause\r\n");
				$action='pause';
			break;
			case 'restart':
				fputs ($sp, "ack~restart\r\n");
				$action='restart';
			break;
		}
	}
	return $action;
}

/**
* METHOD send
* Transmit a row to the sever.
* @param $cmd - command to send
* @param $sp - connection resource
*/
function send($sp,$cmd){
	if(!strpos($cmd, "\r\n")) $cmd.="\r\n";

	while (strlen($cmd) > 0) {		
		$recursion++;
      	$result = fwrite($sp,$cmd);
		if ($result === false) {
			throw new Exception("ERR:Unable to write cmd: [$cmd]");
			return false;
		}
		
		if ($result < strlen($cmd)) {
			$msg="WRN:Network Issues?  Partially transmitted row.  Retry $recursion for ".(strlen($cmd)-$result)." more bytes.";
			ce($msg);
			$cmd = substr($cmd, $result);
			// WAIT A BIT BEFORE TRYING TO SEND THE REST
			usleep(1000000);
		} else {
			$cmd = "";
		}

		if ($recursion>=60) {
			$msg="ERR:Failed to write $cmd after $recursion attempts.";
			ce($msg);
			throw new Exception($msg);
			return false;
		}
    }
}

/**
* METHOD getResp
* Get Response and return as array.
* @param $sp - connection resource
*/
function getResp($sp){
	$result=fgets ($sp, 1024);
	$item = json_decode($result, 1);
	$err="";
	// LOOK FOR ANY JSON DECODING ERRORS.
	switch(json_last_error())
	{
		  case JSON_ERROR_DEPTH:
			    $err = 'JSON ERROR - Maximum stack depth exceeded';
		  break;
		  case JSON_ERROR_CTRL_CHAR:
				$err = 'JSON ERROR - Unexpected control character found';
		  break;
		  case JSON_ERROR_SYNTAX:
				$err = 'JSON ERROR - Syntax error, malformed JSON';
				$err.= "\n[".$result."]\n";
		  break;
		  case JSON_ERROR_NONE:
		  		//OK
		  break;
	}
	if($err){
	    ce($err);
	}	
	return $item;
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

function anykey($s='Press enter to continue...') { 
    echo $s."\n"; 
    fgetc(STDIN); 
}  

?>