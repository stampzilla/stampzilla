<?php

require_once("../../lib/stampzilla/lib/spyc.php");

$file = '/etc/stampzilla/stateLogger.yml';

// Check if file exists
if ( !is_file($file) )
    return !trigger_error("Config file ($file) is missing!",E_USER_ERROR);

// Try to read the settings file (yml)
$settings = spyc_load_file($file);

if ( !mysql_connect($settings['server'],$settings['username'],$settings['password']) )
  return "Invalid credentials";

mysql_select_db($settings['database']);

echo "Date,Sec,Min,Hour,Day\n";
if ( $res = mysql_query("SELECT * FROM data ORDER BY timestamp") )
  while($row = mysql_fetch_assoc($res)) {
    echo $row['timestamp'].",0;".$row['sec'].";0,0;".$row['min'].";0,0;".$row['hour'].";0,0;".$row['day'].";0\n";
    //Month,Nominal,Real
    //1913-01-15 ,59.740  ; 61.330 ; 64.880,609.591836734694 ; 625.816326530612 ; 662.04081632653
  }



?>
