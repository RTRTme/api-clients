#!/usr/bin/php
<?php
/**
*
* @file		        tcp-api-demo.php
* @copyright		Copyright 2012 Dilltree Inc.
* @version			1.0
* @license 			See README.txt
* @author			Jeremy Dill
*
**/

/*=========CONFIGURATION=======*/
date_default_timezone_set('America/Chicago');
ini_set('error_reporting', E_ALL & ~E_NOTICE);
if(!empty($_SERVER["argv"][1]))	$reset=$_SERVER["argv"][1];

// RTRT.me SERVER
$host = 'tcp.rtrt.me';
$port = 39399;

// RTRT.me AUTH STRING
$auth = 'auth~MyDemoApp~4c5d9d5ef469f69057f7766a~89ad3f6700e2e68e6431315bdab00f54';

// IF SET, WILL STORE LAST POLLING TIME IN FILE FOR RESUME PURPOSES.  WHEN STARTING UP THIS SCRIPT AGAIN, IF FILE EXISTS, IT WILL RESUME FROM VALUE IN FILE AS (IGT/AGT/UGT) OPTION.
$resume_file="./resume-tcp.txt"; 

// READ FILE NOW AND GET RESUME VALUE OR SET TO 0
if (!$reset && is_file($resume_file)) {
    $farr=file($resume_file);
    $resume=$farr[0]; //first line of file.
} else {
    $resume=0;
}

// OPTIONS (SEE http://rtrt.me/docs/api/tcp)
$max = 100;

// STREAM EXAMPLES...DO ONE OR THE OTHER
// command to stream profile changes (AGT to get updates and inserts)
$stream = 'stream~/events/EVENTDEMO/profiles~'.$resume.'~agt';

// --OR--

// command to stream profile splits for NIKE category (IGT mode, we don't care about updates, just want first time somebody is read) 
#$stream = 'stream~/events/EVENTDEMO/categories/top-men/splits~'.$resume.'~igt';


// BASE PATH/NAME OF LOG FILES
$write_to_file="./rtrt-tcp"; 

/**
* METHOD handleRecord
*
* Perform some custom action on each record received. 
*
* @param multi $record - Each record is an associative array of values from streamed list object, for example:
         array( 
            "i":"1326414720_000019",
            "pid":"RZ9ZWPFR",
            "tag":"103",
            "fname":"Jamal",
            "lname":"Nash",
            "city":"New Rochelle",
            "sex":"M",
            "race":"Marathon",
            "course":"marathon",
            "name":"Jamal Nash"
        );
*
*/

function handleRecord($record){
    
    // DO YOUR ACTION HERE...PERHAPS STORE IN DB/ETC.
    #print_r($record);

}

/*=========END CONFIGURATION=======*/


// open client connection
$fp = fsockopen ($host, $port, $errno, $errstr);
if (!$fp){
	$result = "Error: could not open socket connection";
}else{
	echo 'Connected to '.$host.'\n';
	
	if ($write_to_file) echo "Output to ".$write_to_file."\n";
	
	//authorize
	fputs ($fp, $auth."\r\n");
	$result = fgets ($fp, 1024);
	
	// set max 
	fputs ($fp, "query_max~".$max."\r\n");
	
	// start stream
    fputs ($fp, $stream."\r\n");
	
	$i = 0;
	$count = 0;
	if($write_to_file) {
	    $fh = fopen($write_to_file.'.json', 'w') or die("can't open file");
	    $ferr = fopen($write_to_file.'.err', 'w') or die("can't open file");
	}
	stream_set_timeout($fp, 3);	
	while(!feof($fp)){
		$i++;
		$result = fgets ($fp);
		$result = trim($result);
		$tst= json_decode($result, 1);
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
		    if($ferr) fwrite($ferr, $err);
		    echo $err;
		}
		$batch_count=count($tst['list']);
		$count += $batch_count;
		if ($fh && $tst['list']){
			foreach ($tst['list'] as $obj){
				$str=json_encode($obj)."\n";
				if($fh) fwrite($fh, $str);				
				handleRecord($obj);				
			}
		}

		if ($batch_count) {
		    if(isset($tst['info']['lasti'])) $last=$tst['info']['lasti'];
		    if(isset($tst['info']['lasta'])) $last=$tst['info']['lasta'];
		    if(isset($tst['info']['lastu'])) $last=$tst['info']['lastu'];		    
			echo "\nbatch: ".$batch_count.", total: ".$count." - last: ".$last."\n";
			if($resume_file) file_put_contents($resume_file,$last) or die("can't open file");
			$idle=0;
		} else {
			print_r($tst);
			echo ".";
			// PING ONCE IN A WHILE
            if($idle % 5 ===0) {
                fputs ($fp, "ping\r\n");			
                $idle=0;
            }
			$idle++;
		}
	}
	fclose ($fp);
}
echo "completed";

?>