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
  protected $states = array();
  protected $vars = array();
  protected $values = array();

  function startup() {
    $this->vars = explode(',',$this->setting('log'));
    foreach($this->vars as $key => $line) {
      unset($this->vars[$key]);
      $line = trim($line);
      $line = explode('.',$line,2);

      if ( !isset($this->vars[trim($line[0])]) )
        $this->vars[trim($line[0])] = array();

      $this->vars[trim($line[0])][] = $line[0].'.'.$line[1];
    }

    if ( !mysql_ping() ) {
      $host = $this->setting('server');
      $user = $this->setting('username');
      $pass = $this->setting('password');
      $db = $this->setting('database');

      if ( !mysql_connect($host,$user,$pass) )
        return "Invalid credentials";

      if ( !mysql_select_db($db) )
        return;

      if ( !$res = mysql_query('SHOW TABLES LIKE "stateLogger"') )
        $this->emergency('Failed to check if table stateLogger exists in database');

      if ( !mysql_fetch_assoc($res) ) {
        note(notice,'Creating new database table named "stateLogger"');
        if ( !mysql_query("CREATE TABLE IF NOT EXISTS `stateLogger` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `field` varchar(255) NOT NULL,
          `numeric` float NOT NULL,
          `string` varchar(100) NOT NULL,
          `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        )") )
          $this->emergency('Failed to create table stateLogger in mysql database');
      }

      $res = mysql_query("SELECT field FROM stateLogger GROUP BY field");
      while($row = mysql_fetch_assoc($res)) {
        $this->values[$row['field']] = 0;
      }

      foreach($this->values as $key => $line) {
        $res = mysql_query("SELECT `numeric`,`string` FROM stateLogger WHERE field='".mysql_real_escape_string($key)."' ORDER BY id DESC LIMIT 1");
        while($row = mysql_fetch_assoc($res)) {
          if ( $row['string'] <> '' ) {
            $this->values[$key] = $row['string'];
          } else {
            $this->values[$key] = $row['numeric'];
          }
        }
      }
    }
  }

  function setting_saved($key,$value) {
    switch($key) {
      case 'log':
        $this->startup();
        break;
    }

    return true;
  }


  function event($pkt) {
    $prev = $this->values;

    if ( isset($pkt['type']) && $pkt['type'] == 'state' && isset($this->vars[trim($pkt['from'])]) ) {
      $this->states[$pkt['from']] = $pkt['data'];

      foreach($this->vars[$pkt['from']] as $key => $line) {
        $this->values[$line] = $this->readStates($line);

        if ( !isset($prev[$line]) || $this->values[$line] <> $prev[$line] ) {
          note(notice,'Logging value '.$line.'='.$this->values[$line] );
          if ( is_numeric($this->values[$line]) )
            $res = mysql_query('INSERT INTO stateLogger SET `field`="'.mysql_real_escape_string($line).'", `numeric`='.$this->values[$line].', `timestamp`=now()');
          else
            $res = mysql_query('INSERT INTO stateLogger SET `field`="'.mysql_real_escape_string($line).'", `string`="'.mysql_real_escape_string($this->values[$line]).'", `timestamp`=now()');

          if (!$res) {
            note(error,'Invalid query: ' . mysql_error());
          }
        }
      }
    }
  }

    function readStates( $path ) {/*{{{*/
        $path = explode('.',$path);
        $path = array_filter($path, 'strlen'); // Remove empty

        $a = '$this->states';
        foreach($path as $key => $line) {
            if ( !eval("return isset($a);") ) {
                eval("$a = array();");
            }

            if ( eval("return is_object($a);") ) {
                $a .= '->'.$line;
            } else {
                $a .= '["'.$line.'"]';
            }
        }
        return eval("
            if ( isset($a) ) {
                return $a;
            }"
        );
    }/*}}}*/

  function setting_validate($key,$value) {/*{{{*/
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
  }/*}}}*/
}


$l = new stateLogger();
$l->start('stateLogger');

?>
