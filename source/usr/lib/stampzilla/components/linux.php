#!/usr/bin/php
<?php

require_once "../lib/component.php";

class linux extends component {
    protected $componentclasses = array('audio.controller');
    protected $settings = array();
    protected $commands = array(
    );

	function startup() {
		$this->active = array();

		if ( exec('pidof X') ) 
			$this->active['dpms'] = true;

		if ( exec('pidof gnome-screensaver') ) 
			$this->active['gnome-screensaver'] = true;

		if ( exec('pidof pulseaudio') ) 
			$this->active['pulseaudio'] = true;
		else
			$this->active['alsa'] = true;

		note(notice, 'Active capabilities: '.implode(array_keys($this->active),', ') );
	}

// X11 tools
	function xauth() {
		if ( !isset($this->xauth) ) {
			if ( !$pid = exec('pidof X') )
				return note(warning,'Could not find pid of X');

			if ( !$data = file_get_contents("/proc/$pid/cmdline") )
				return note(warning,"Cound not read pid info (/proc/$pid/line)");

			$data = explode(chr(0),$data);
			foreach($data as $key => $line) {
				if ( $line == "-auth" ) {
					$auth = $data[$key+1];
					break;
				}
			}

			if ( !isset($auth) )
				return note(warning,'Could not find XAUTHORITY in xinit process environment');

			$this->xauth = $auth;
			note(debug,'Found XAUTHORITY in '.$auth);
		}

		return $this->xauth;
	}
	function wake() {
		if ( isset($this->active['gnome-screensaver']) ) {
			note(notice,'Deactivating gnome-screensaver');
			exec("DISPLAY=:0 gnome-screensaver-command -d");
			$this->setState('screensaver',$this->gnome_screensaver_status());

			return true;
		} else {
			note(notice,'Dectivates screen blanking with xset');
			return exec("export DISPLAY=:0; export XAUTHORITY=".$this->xauth()."; export PATH=\${PATH}:/usr/X11R6/bin; xset s reset"); // Blank screen
		}
	}
	function screensaver() {
		if ( isset($this->active['gnome-screensaver']) ) {
			if ( $this->state['screensaver'] == true )
				return $this->wake();

			note(notice,'Activating gnome-screensaver');
			exec("DISPLAY=:0 gnome-screensaver-command -a",$ret);
			$this->setState('screensaver',$this->gnome_screensaver_status());

			return true;
		} else {
			note(notice,'Activates screen blanking with xset');
			return exec("export DISPLAY=:0; export XAUTHORITY=".$this->xauth()."; export PATH=\${PATH}:/usr/X11R6/bin; xset s activate"); // Blank screen
		}
	}

// X11 - DPMS
	function DPMS_status() {
		$status = exec("export DISPLAY=:0; export XAUTHORITY=".$this->xauth()."; export PATH=\${PATH}:/usr/X11R6/bin; xset -q | grep \"DPMS is\"");
		$status = explode(" ",trim($status));
		$dpms = array_pop($status);

		$status = exec("export DISPLAY=:0; export XAUTHORITY=".$this->xauth()."; export PATH=\${PATH}:/usr/X11R6/bin; xset -q | grep \"Monitor is\"");
		$status = explode(" ",trim($status));
		$monitor = array_pop($status);
	
		if ( $dpms == 'Disabled' )
			return 'On';
		else
			return $monitor;
	}

// X11 - Gnome-screensaver
	function gnome_screensaver_status() {
		exec("DISPLAY=:0 gnome-screensaver-command -q",$ret);
	
		if ( isset($ret[0]) && $ret[0] == 'The screensaver is inactive' ) {
			return false;
		}

		if ( isset($ret[0]) && $ret[0] == 'The screensaver is active' ) {
			return true;
		}

		return 'invalid';
	}

// ALSA
	function ALSA_status() {
		exec('amixer 2>/dev/null',$ret);
	
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
		$status = array();

		if ( isset($this->active['dpms']) )
			$status['dpms'] = $this->DPMS_status();

		if ( isset($this->active['alsa']) )
			$status['alsa'] = $this->ALSA_status();

		if ( isset($this->active['gnome-screensaver']) )
			$status['screensaver'] = $this->gnome_screensaver_status();

		if ( $status ) 
			$this->intercom($status);
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
