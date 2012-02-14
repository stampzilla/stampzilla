#!/usr/bin/php
<?php

require_once "../lib/component.php";

class linux extends component {
    protected $componentclasses = array('audio.controller');
    protected $settings = array();
    protected $commands = array(
    );

// X11 tools
	function xauth() {
		if ( !isset($this->xauth) ) {
			if ( !$pid = exec('pidof xinit') )
				return note(warning,'Could not find pid of xinit');

			if ( !$data = file_get_contents("/proc/$pid/environ") )
				return note(warning,"Cound not read pid info (/proc/$pid/environ)");

			$data = explode(chr(0),$data);
			foreach($data as $line) {
				if ( substr($line,0,10) == 'XAUTHORITY' ) {
					$auth = substr($line,11);
					break;
				}
			}

			if ( !isset($auth) )
				return note(warning,'Could not find XAUTHORITY in xinit process environment');

			$this->xauth = $auth;
		}
	}
	function wake() {
		exec("gnome-screensaver-command -d");
		return true;
		return exec("export DISPLAY=:0; export XAUTHORITY=".$this->xauth()."; export PATH=\${PATH}:/usr/X11R6/bin; xset s reset"); // Blank screen
	}
	function screensaver() {
		exec("gnome-screensaver-command -a");
		return true;
		return exec("export DISPLAY=:0; export XAUTHORITY=".$this->xauth()."; export PATH=\${PATH}:/usr/X11R6/bin; xset s activate"); // Blank screen
	}

// X11 - DPMS
	function DPMS_status() {
		return exec("export DISPLAY=:0; export XAUTHORITY=".$this->xauth()."; export PATH=\${PATH}:/usr/X11R6/bin; xset -q | grep \"Monitor is\" | awk '{print $3}'");
	}

// X11 - Screensaver
	function screensaver_status() {
		exec("gnome-screensaver-command -q",$ret);
		return $ret;
	}

// ALSA
	function ALSA_status() {
		exec('amixer 2>1',$ret);
	
		// Only parse if content have changed
		if ( !isset($this->prev_alsa) || $this->prev_alsa != $ret )
			$this->alsa = $this->ALSA_parse($ret);

		$this->prev_alsa = $ret;

		return $this->alsa;
	}
	function ALSA_parse($ret) {
		$data = array();
		foreach($ret as $line) {
			if ( substr($line,0,2) == '  ' ) {
				if ( !isset($data[$node]) )
					$data[$node] = array();
				$line = explode(':',trim($line),2);
				$data[$node][$line[0]] =  trim($line[1]);
			} else {
				$line = explode("'",$line);
				$node = $line[1];
			}
		}

		$ret = array();
		foreach($data as $name => $dev) {
			$data[$name]['Capabilities'] = array_flip(explode(' ',$dev['Capabilities']));

			if ( isset($data[$name]['Capabilities']['pvolume']) ) {
				if ( !preg_match("/Playback (\d+) - (\d+)/",$data[$name]['Limits'],$lim) || $lim[2] < 1 )
					continue;

				$data[$name]['Playback channels'] = array_flip(explode(' - ',$dev['Playback channels']));
				foreach($data[$name]['Playback channels'] as $channel => $tmp) {
					if ( !isset($ret[$name]) )
						$ret[$name] = array();

					if ( preg_match("/Playback (\d+)/",$data[$name][$channel],$r) ) {
						$ret[$name][$channel] = round(100 * ($r[1] - $lim[1]) / $lim[2]);
					}
				}

				if ( !$ret[$name] )
					continue;

				$sum = 0;
				foreach($ret[$name] as $key => $val) {
					$sum += $val;
				}
				$ret[$name]['All'] = round($sum / count($ret[$name]));
			}
		}

		return $ret;
	}

// _child
	function intercom_event($status) {
		$this->setState($status);
	}
	function _child() {
		$this->intercom(array(
			'DPMS' => $this->DPMS_status(),
			'ALSA' => $this->ALSA_status(),
			'Screensaver' => $this->screensaver_status(),
		));
		sleep(1);
	}
}

$t = new linux();

$hostname = exec('hostname');

if ( !$hostname ) {
	note(critical,'Failed to get hostname (exec hostname)');
	die();
}

$t->start($hostname,'_child');

?>
