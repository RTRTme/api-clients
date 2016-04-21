#!/usr/bin/php
<?php
/**
*
* @file		        web-api-demo.php
* @copyright		Copyright 2012 Dilltree Inc.
* @version			1.0
* @license 			See README.txt
* @author			Jeremy Dill
*
**/

date_default_timezone_set('America/Chicago');
ini_set('error_reporting', E_ALL & ~E_NOTICE);
if(!empty($_SERVER["argv"][1])) $reset=$_SERVER["argv"][1];

// RTRT.me SERVER
$host = 'api.rtrt.me';

// RTRT.me AUTH INFO
$appid = '4c5d9d5ef469f69057f7766a';

// USE SECRET IF PROVIDED
#$secret = 'mysecret';

// USE HTTPS PROTOCOL (required if using secret)
$secure = false;

// https protocol (required if using secret)
if(isset($secret)) $secure=true;

// ------------EXAMPLE CALLS (SEE http://rtrt.me/docs/api/rest)

// FIRST, REGISTER A TOKEN
$request=call('/register');
$token=$request['token'];

// GET A LIST OF EVENTS FOR MY APPID
$request=call('/events','max=30');
print_r($request);

// GET LIST OF POINTS FOR AN EVENT
$request=call('/events/EVENTDEMO/points','max=30');
print_r($request);

// GET LIST OF CATEGORIES FOR EVENT
$request=call('/events/EVENTDEMO/categories','max=30');
print_r($request);


// NOW, JUST START POLLING FOR NEW PROFILES.  WE WILL USE AGT METHOD TO GET UPDATES AND INSERTS.

// SETUP SOME LOGGING AND RESUME STUFF---
// IF SET, WILL STORE LAST POLLING TIME IN FILE FOR RESUME PURPOSES.  WHEN STARTING UP THIS SCRIPT AGAIN, IF FILE EXISTS, IT WILL RESUME FROM VALUE IN FILE AS (IGT/AGT/UGT) OPTION.
$resume_file="./resume-web.txt"; 

// READ FILE NOW AND GET RESUME VALUE OR SET TO 0
if (!$reset && is_file($resume_file)) {
    $farr=file($resume_file);
    $last=$farr[0]; //first line of file.
    echo "Resuming from $last\n";
} else {
    $last=0;
}
// BASE PATH/NAME OF LOG FILES
$write_to_file="./rtrt-web"; 
if($write_to_file) {
    $fh = fopen($write_to_file.'.json', 'w') or die("can't open file");
}

$max=30;

// START POLLING FOR PROFILES
while(1){
    $request=call('/events/EVENTDEMO/profiles','max='.$max.'&agt='.$last);
    $batch_count=count($request['list']);
    $count += $batch_count;
    if ($fh && $request['list']){
        foreach ($request['list'] as $obj){
            $str=json_encode($obj)."\n";
            if($fh) fwrite($fh, $str);		
            handleProfile($obj);		
        }
    }

    if ($batch_count) {
        if(isset($request['info']['lasta'])) $last=$request['info']['lasta'];
        echo "\nbatch: ".$batch_count.", total: ".$count." - last: ".$last."\n";
        if($resume_file) file_put_contents($resume_file,$last) or die("can't open file");
        $idle=0;
    } else {
        echo ".";
        $idle++;
        $totIdle++;        
        if($idle % 5 ===0) {
            echo "idle, nothing new\n";
            $idle=0;
            
        }
        
        // break out after waiting for a long time so we can move onto next demo.
        if($totIdle>10) {
            echo "moving on\n";
            break;
        }
    }
    
    // IF WE DIDN'T GET MAX RESULTS, START SLEEPING AND DO POLLING EVERY 1 SECONDS.
    if($batch_count<$max) sleep(1);
}

//reset vars for next demo
$totIdle=$idle=$last=$count=0;

// START POLLING SPLITS
while(1){
    // will use 'igt' polling method
    $request=call('/events/EVENTDEMO/points/ALL_POINTS/splits','max='.$max.'&igt='.$last);
    $batch_count=count($request['list']);
    $count += $batch_count;
    if ($fh && $request['list']){
        foreach ($request['list'] as $obj){
            $str=json_encode($obj)."\n";
            if($fh) fwrite($fh, $str);		
            handleSplit($obj);		
        }
    }

    if ($batch_count) {
        if(isset($request['info']['lasti'])) $last=$request['info']['lasti'];
        echo "\nbatch: ".$batch_count.", total: ".$count." - last: ".$last."\n";
        if($resume_file) file_put_contents($resume_file,$last) or die("can't open file");
        $idle=0;
    } else {
        echo ".";
        $idle++;
        $totIdle++;        
        if($idle % 5 ===0) {
            echo "idle, nothing new\n";
            $idle=0;
            
        }
        
        // break out after waiting for a long time.
       if($totIdle>300) {
            echo "moving on\n";
            break;
        }
    }
    
    // IF WE DIDN'T GET MAX RESULTS, START SLEEPING AND DO POLLING EVERY 1 SECONDS.
    if($batch_count<$max) sleep(1);
}

echo "completed";




/**
* METHOD handleProfile
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

function handleProfile($record){
    
    // DO YOUR ACTION HERE...PERHAPS STORE IN DB/ETC.
    echo 'Got #'.$record['tag'].' - '.$record['name']."\n";

}

/**
* METHOD handleSplit
*
* Perform some custom action on each record received. 
*
* @param multi $record - Each record is an associative array of values from streamed list object, for example:
         array( 
             "time":"00:00:00.00",
             "point":"START-H",
             "tag":"1182",
             "name":"Linus Cochran",
             "pid":"RV56BV8E",
             "timestamp":"1304146500.50",
             "timeOfDay":"6:55:01 am",
             "course":"halfmarathon",
             "epc":"100",
             "startTime":"6:55:01 am",
             "i":"1326414800_000025"
        );
*
*/

function handleSplit($record){
    
    // DO YOUR ACTION HERE...PERHAPS STORE IN DB/ETC.
    echo 'Got #'.$record['tag'].' at point '.$record['point'].".  Time of day is ".$record['timeOfDay']." and time was ".$record['time']."\n";

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

    #echo "Calling $url\n";
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

?>