#!/usr/bin/php
<?php

require_once "../lib/component.php";

class stateLogger extends component {
    protected $componentclasses = array('logger');
    protected $settings = array(
        'server'=>array(
            'type'=>'text',
            'name' => 'Database server',
            'required' => true
        ),
        'username'=>array(
            'type'=>'text',
            'name' => 'Database username',
            'required' => true
        ),
        'password'=>array(
            'type'=>'text',
            'name' => 'Database password',
            'required' => true
        ),
        'database'=>array(
            'type'=>'text',
            'name' => 'Database name',
            'required' => true
        ),
        'log'=>array(
            'type'=>'text',
            'name' => 'States to log (comma separated)'
        )
	);
  protected $commands = array();


  function event($pkt) {
    if ( isset($pkt['type']) && $pkt['type'] == 'state' && !isset($this->sends[$pkt['from']]) ) {
        //$this->states[$pkt['from']] = $pkt['data'];
    }
  }


  function setting_validate($key,$value) {
    switch($key) {
      case 'server':
        if ( ($ip = gethostbyname($value)) && $ip != $value ) {
          note(debug,'Valid! Found ip-address: '.$ip);
          return;
        }

        return "Not a valid hostname";
      case 'username':
        return;
      case 'password':
        $host = $this->setting('server');
        $user = $this->setting('username');

        if ( mysql_connect($host,$user,$value) )
          return;

        return "Invalid credentials";
      case 'database':
        $host = $this->setting('server');
        $user = $this->setting('username');
        $pass = $this->setting('password');

        if ( !mysql_connect($host,$user,$pass) )
          return "Invalid credentials";

        if ( mysql_select_db($value) )
          return;

        note(debug,'Try to create database'.$value);
        if ( !mysql_query("CREATE DATABASE $value") )
          return "Failed to create database!";

        if ( mysql_select_db($value) )
          return;

        return "Access denied or invalid database name";
      case 'log':
        return;
      default:
        return "Unknown field";
    }
  }
}


$l = new stateLogger();
$l->start('stateLogger');

?>
