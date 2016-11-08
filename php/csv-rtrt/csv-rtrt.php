#!/usr/bin/php
<?php
/**
*
* @file		        csv-rtrt.php
* @copyright		Copyright 2016 Dilltree Inc.
* @version			1.2
* @license 			See README.txt
* @author			Jeremy Dill
*
* usage: csv-rtrt.php [event name] [location name] [csv file path] [rewind (1 to prevent resume)] [logfile]
**/

/*=========CONFIGURATION=======*/

$silent = false;
$appid='[your appid here]';
$token='[your token here]';
$client_name = 'MyCSVStreamer Version 1.2'; // tell us what app is connecting to RTRT

// RTRT.me AUTH STRING
//auth~<client software name>~<application id>~<application token>\r\n
$auth = 'auth~'.$client_name.'~'.$appid.'~'.$token;

// RTRT.me SERVER
$host = '[rts host]'; //contact us for host and port info
$port = '[contact us]';


/*=========END CONFIGURATION=======*/

date_default_timezone_set('GMT');
ini_set('error_reporting', E_ALL & ~E_NOTICE);

if(!empty($_SERVER["argv"][1]))	$eventname=$_SERVER["argv"][1];
if(!empty($_SERVER["argv"][2]))	$loc=$_SERVER["argv"][2];
if(!empty($_SERVER["argv"][3]))	$file=$_SERVER["argv"][3];
if(!empty($_SERVER["argv"][4]))	$reset=$_SERVER["argv"][4];

if (empty($eventname)||empty($loc)||empty($file)) die("invalid options: \n usage: [event name] [location name] [csv file path] [rewind (1 to prevent resume)]\n");

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
ce('CSV-to-RTRT script version '.$version);
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
		stream_set_timeout($sp, 10);
		ce('Connected to '.$host);

		// AUTHENTICATE
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

		ce('Init Complete...Awaiting Start Command');

		$resp = getResp($sp);
		if(!$resp['cmd']=='start') {
			ce($resp);
			exit();
		}



		$resume_seqnr=(int) $resp['lastseq'];
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
		        	$cols=explode(',',$row);
					$seqnr++;

		            if (empty($row)) {
		            	// note, on seek, first row will always be blank--getting \r\n of last record: ignore
		            	if($buffer!=="\r\n") ce('!!!WARNING--EMPTY ROW IN FILE - '.$seqnr.'['.$buffer.']');
						$seqnr--;
		            	continue;
		            }

					$tag=$cols[0];
			    	$rtime=$cols[1];

					$read='read~'.$tag.'~'.$loc.'~'.$rtime.'~'.$seqnr."\r\n";

		            // DO SOME ROW VALIDITY CHECKS
					if(empty($tag) || empty($rtime)) {
						ce('!!!WARNING--INCOMPLETE ROW IN FILE: ['.$row.']');
						$seqnr--;
						break;
					}
					// ADVANCE FOR RESUME
					if($seqnr <= $resume_seqnr) {
						// KEEP TRACK OF FILE SIZE SO THAT WE CAN CHECK FOR FILE CHANGES
						$size = ftell($fh);
						continue;
					}

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
				case 'stop':
				break;
				case 'disconnect':
					send($sp,'ack-disconnect');
					fclose ($sp);
				break;
				case 'start':
				break;
				case 'error':
					ce("Got an error..please verify that we are streaming correct file!");
					#break 2;
				break 2;
			}

			// IDLE MESSAGE
	        echo ".";
	        //sleep(1);
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
			case 'disconnect':
				fputs ($sp, "ack~disconnect\r\n");
				$action='disconnect';
			break;
			case 'stop':
				fputs ($sp, "ack~stop\r\n");
				$action='stop';
			break;
			case 'start':
				fputs ($sp, "ack~start\r\n");
				$action='start';
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
