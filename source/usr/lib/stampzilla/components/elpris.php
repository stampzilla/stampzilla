#!/usr/bin/php
<?php

// http://driftsdata.statnett.no/snps/?language=se&country=se

require_once "../lib/component.php";

class elpris extends component {
    protected $componentclasses = array('data');
    protected $settings = array();
    protected $commands = array(
    );

  function intercom_event($data) {
    $data = json_decode($data,true);
  
    $state = array();

    foreach($data as $key => $line) {
      if ( $line['x'] > 600 && $line['x'] < 700 && $line['y'] > 600 && $line['y'] < 700 ) {
        $state['SE4'] = $line['label'];
      }
      if ( $line['x'] > 700 && $line['x'] < 720 && $line['y'] > 550 && $line['y'] < 650 ) {
        $state['SE3'] = $line['label'];
      }
      if ( $line['x'] > 700 && $line['x'] < 750 && $line['y'] > 350 && $line['y'] < 400 ) {
        $state['SE2'] = $line['label'];
      }
      if ( $line['x'] > 750 && $line['x'] < 800 && $line['y'] > 220 && $line['y'] < 250 ) {
        $state['SE1'] = $line['label'];
      }
    }

    ksort($state);

    $state['updated'] = time();
    $this->setState($state);
  }

  function _child() {
    $data = file_get_contents("http://driftsdata.statnett.no/snpsrestapi/MapData/PriceLayers");

    if ( $data )
      $this->intercom($data);

    sleep(60);
  }
}

$t = new elpris();
$t->start('elpris','_child');


?>
