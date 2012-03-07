#!/usr/bin/php
<?php

require_once "../lib/component.php";


class beckhoff extends component {

    protected $componentclasses = array('controller');/*{{{*/
    protected $settings = array(
        'tpy'=>array(
            'type'=>'text',
            'name' => 'TPY file',
            'required' => true
        ),
        'ip'=>array(
            'type'=>'text',
            'name' => 'PLC ip',
            'required' => true
        ),
        'source'=>array(
            'type'=>'text',
            'name' => 'Source net id',
            'required' => true
        ),
        'interface'=>array(
            'type'=>'text',
            'name' => 'Interface tag',
            'required' => true
        )
	);
    protected $commands = array(
        'set' => 'Turns on leds.',
        'reset' => 'Turns off leds.',
        'random' => 'Selects a random color',
    );/*}}}*/
    public $source = null;
    public $id = 0;
    public $tags = array();
    private $prev = '';
    public $commandnames = array(
        1 => 'Read device info',
        2 => 'Read tag',
        3 => 'Write tag',
        4 => 'Read device state',
        5 => 'Write device state',
        6 => 'Add notification',
        7 => 'Delete notification',
        8 => 'Notification',
        9 => 'Read Write',
    );

    function startup() {/*{{{*/
        $ip = $this->setting('ip');
        $host = $this->setting('source');
        $tpy = $this->setting('tpy');
        $interface = $this->setting('interface');

        if ( !($ip && $host && $tpy && $interface) ) 
            $this->emergency('Not configured');

        $this->tpy = new tpy($tpy);

        $to = explode(".",$ip.".1.1");
        $from = explode(".",$host.".1.1");

        $this->ip = $ip;

        foreach($to as $line)
            $this->to .= $this->toChr($line);

        foreach($from as $line)
            $this->from .= $this->toChr($line);

        $this->to .= $this->toChr(801,2);
        $this->from .= $this->toChr(803,2);

        if ( ($this->s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false ) {
            die("socket_create() failed, reason: " .
                socket_strerror(socket_last_error()) . "\n");
        }
        $to = array_slice($to,0,4);

        global $args;
        if ( in_array('s',$args['flags']) ) {
            $this->show = true;
        }

        $this->connect();
        $this->deviceInfo();
        $this->readAdsState('communication_ready');
    }/*}}}*/

    function communication_ready() {
        $interface = $this->setting('interface');
        $this->read($interface);
        $this->addDeviceNotification($interface);
    }

// COMMANDS/*{{{*/
	function toggle($pkt) {
		$this->write('.Interface.'.$pkt['tag'],!$this->readState('.Interface.'.$pkt['tag']),$pkt);
	}
	function set($pkt) {
		if ( !isset($pkt['value']) )
			$pkt['value'] = true;

		$this->write('.Interface.'.$pkt['tag'],$pkt['value'],$pkt);
	}
	function reset($pkt) {
		$this->write('.Interface.'.$pkt['tag'],0,$pkt);
	}/*}}}*/
    function kill($pkt) {/*{{{*/
        $this->deleteNotificationHandles('kill');

        if ($pkt) 
            $this->ack($pkt);

        sleep(1);

        $this->kill_child();

        socket_close($this->s);
    }/*}}}*/

// CONNECTION
    function connect() {/*{{{*/
        note(notice,"Connecting to PLC: ".$this->ip);
        if ( socket_connect($this->s, $this->ip, 48898) === false ) {
            $this->emergency("socket_select() failed, reason: " .
                socket_strerror(socket_last_error()) . "\n");
        }
    }/*}}}*/
    function __destruct() {/*{{{*/
        if ( isset($this->s) )
            socket_close($this->s);
    }/*}}}*/

// ADS COMMANDS
    function read( $otag ) {/*{{{*/
        if ( !isset($this->tpy) )
            return !trigger_error("No TPY file is loaded",E_USER_ERROR);

        if ( !$tag = $this->tpy->tag($otag) )
            return false;

        $offs = (int)$tag->BitOffs;
        $size = (int)$tag->BitSize;

        $data = $this->command( 
            2, 
            $this->toChr((int)$tag->Group,4).
                $this->toChr($offs/8,4).
                $this->toChr($size/8,4),
            $tag
        );

        return null;
    }/*}}}*/
    function write( $tago, $data, $pkt = null ) {/*{{{*/
        if ( !isset($this->tpy) )
            return !trigger_error("No TPY file is loaded",E_USER_ERROR);

        if ( !$tag = $this->tpy->tag($tago) )
            return false;

        if ( is_array($data) ) {
           $this->tpy->source = '';
        }

        $data = $this->tpy->encode($data,$tag);

        $tmp = $this->command( 
            3, 
            $this->toChr((int)$tag->Group,4).
                $this->toChr((int)$tag->BitOffs/8,4).
                $this->toChr(strlen($data),4).
                $data,
            null,
            $pkt
        );

        return $tmp;

    }/*}}}*/

    function addDeviceNotification( $otag ) {/*{{{*/

        if ( !isset($this->tpy) )
            return !trigger_error("No TPY file is loaded",E_USER_ERROR);

        if ( !$tag = $this->tpy->tag($otag) )
            return false;

        $mode = 4;
        /*
            ADSTRANS_NOTRANS        0
            ADSTRANS_CLIENTCYCLE    1
            ADSTRANS_CLIENTONCHA    2
            ADSTRANS_SERVERCYCLE    3
            ADSTRANS_SERVERONCHA    4
            ADSTRANS_CLIENT1REQ     5
        */

        note(debug,'Requesting add notification on: '.$otag);

        $data = $this->command( 
            6, 
            $this->toChr((int)$tag->Group,4). // Index group
            $this->toChr((int)$tag->BitOffs/8,4). // Index offset
            $this->toChr((int)$tag->BitSize/8,4). // Length
            $this->toChr($mode,4). // Transmission mode
            $this->toChr(1000,4). // Max delay (ms)
            $this->toChr(1000,4). // Cycle time (ms)
            $this->toChr('',16), // reserved
            $tag
        );

        return null;
    }/*}}}*/

    function deleteNotificationHandles() {/*{{{*/
        if ( $this->notificationHandles )
            foreach( $this->notificationHandles as $key => $line ) 
                $this->deleteNotificationHandle($key);
    }/*}}}*/
    function deleteNotificationHandle($handle) {/*{{{*/
        note(debug,'Sending delete notification handle: '.$handle);
        $data = $this->command( 
            7, 
            $this->toChr($handle,4) // the handle to be removed
        );

        return null;
    }/*}}}*/


    function getNotificationHandles() {/*{{{*/
        $tags = array(
            ".Interface",
            ".Interface.Yoda.Roof",
            ".Interface.Bedroom",
            ".Interface.Pump",
        );

        $commands = '';
        $data = '';

        foreach($tags as $key => $line) {
            $commands .= 
                $this->toChr(0xF003,4). // Index group
                $this->toChr(0,4). // Index offset
                $this->toChr(4,4). // Notification handle size
                $this->toChr(strlen($line),4); // Tag length
            $data .= $line;
        }

        $data = $this->command( 
            9, 
            $this->toChr(0xF082,4). // ADS list-read-write command
            $this->toChr(count($tags),4). // number of ADS-sub commands
            $this->toChr(0x18,4). // we expect an ADS-error-return-code for each ADS-sub command
            $this->toChr(strlen($commands.$data),4). // length of write data
            $commands.$data // send bytes (IG1, IO1, RLen1, WLen1,
                                        // IG2, IO2, RLen2, WLen2, 
                                        // Data1, Data2)
        );

        return null;
    }/*}}}*/

    function rawRead( $group, $offset, $length ) {/*{{{*/
        return $this->command( 
            2, 
            $this->toChr($group,4).
            $this->toChr($offset,4).
            $this->toChr($length,4)
        );
    }/*}}}*/
    function rawWrite( $group, $offset, $data,$tago='Unknown' ) {/*{{{*/
        return $this->command( 
            3, 
            $this->toChr($group,4).
            $this->toChr($offset,4).
            $this->toChr(strlen($data),4).
            $data,
            $tago
        );
    }/*}}}*/
    function deviceInfo() {/*{{{*/
        return $this->command(1);
    }/*}}}*/
    function readAdsState($whendone) {/*{{{*/
        return $this->command(4,'',$whendone);
    }/*}}}*/

// ADS HELP FUNCTIONS
    function decode( $data, $start = 0, $len = null,$split = null ) {/*{{{*/
        $ret = '';
        $charlist = array();

        if ( $len == null )
            $len = strlen($data)-$start;

        $str = substr($data,$start,$len);
        $cnt = 0;

        do {
            $chr = substr($str,0,1);
            $str = substr($str,1);
            $hex = str_pad(dechex(ord($chr)),2,"0",STR_PAD_LEFT );
            $charlist[] = "[$hex]";

            if ( $split != null )
                $ret[] = ord($chr);
            else {
                //$ret *= 256;
                /*if ( $cnt > 0 ) {*/
                /*} else
                    $ret += ord($chr);*/


                $ret += ord($chr) * pow(256,$cnt);
                $cnt++;
            }
        } while ($str != "");

        if ( $split != null ) 
            $ret = implode($ret,$split);
        /*else {
            $data = unpack('i*',substr($data,$start,$len));
            return $data[0];
        }*/

        return $ret;
        return implode($charlist,'')."|".$ret;
    }/*}}}*/

    function parseADS( $package ) {/*{{{*/
        if ( !strlen($package) )
            return null;

        $head = $this->parseADSHeader( $package );

        $data = substr($package,32);
        //$this->show($data,"DATA");

        switch($head['command']) {
            case 0: // Invalid
                return array(
                    'head' => $head,
                    'error'=>'INVALID PACKAGE'
                );
            case 1: // ADS Read Device info
                return array(
                    'head' => $head,
                    'error' => $this->decode($data,0,4),
                    'majorVersion' => $this->decode($data,4,1),
                    'minorVersion' => $this->decode($data,5,1),
                    'buildVersion' => $this->decode($data,6,2),
                    'name' => substr($data,8,15)
                );
            case 2: // ADS Read
                $len = $this->decode($data,4,4);
                return array(
                    'head' => $head,
                    'error' => $this->decode($data,0,4),
                    'length' => $len,
                    'rlength' => strlen(substr($data,8,$len)),
                    'data' => substr($data,8,$len)
                );

            case 3: // ADS Write
                if ( $head['state'] == 4 ) 
                    return array(
                        'head' => $head,
                        'indexGroup' => $this->decode($data,0,4),
                        'indexOffset' => $this->decode($data,4,4),
                        'length' => $this->decode($data,8,4),
                        'data' => $this->decode($data,12),
                    );
                else
                    return array(
                        'head' => $head,
                        'error' => $this->decode($data,0,4),
                    );
            case 4: // ADS Read state
                return array(
                    'head' => $head,
                    'error' => $this->decode($data,0,4),
                    'adsState' => $this->decode($data,4,2),
                    'deviceState' => $this->decode($data,6,2)
                );
            case 5: // ADS Write control
                return array(
                    'head' => $head,
                    'error' => $this->decode($data,0,4),
                );
            case 6: // ADS Add device notification
                if ( $head['state'] == 4 ) 
                    return array(
                        'head' => $head,
                        'indexGroup' => $this->decode($data,0,4),
                        'indexOffset' => $this->decode($data,4,4),
                        'length' => $this->decode($data,8,4),
                        'mode' => $this->decode($data,12,4),
                        'maxDelay' => $this->decode($data,16,4),
                        'cycleTime' => $this->decode($data,20,4),
                        'reservedLength' => strlen($this->decode($data,24)),
                    );
                else {
                    return array(
                        'head' => $head,
                        'error' => $this->decode($data,0,4),
                        'handle' => $this->decode($data,4,4)
                    );
                }
            case 7: // ADS Delete device notification
                return array(
                    'head' => $head,
                    //'error' => $this->decode($data,0,4),
                );
            case 8: // ADS Device notification
                $length = $this->decode($data,0,4);
                $stamps = $this->decode($data,4,4);

                $p = 8;

                $tags = array();

                for( $stamp=0;$stamp<$stamps;$stamp++ ) {
                    //$timestamp  = $this->decode($data,$p,8);
                    $timestamp = $stamp;
                    $samples = $this->decode($data,$p+8,4);
                    $p += 12;

                    $tags[$timestamp] = array();
                    for( $i=0;$i<$samples;$i++ ) {

                        $tags[$timestamp][$i]['handle'] = $this->decode($data,$p,4);
                        $tags[$timestamp][$i]['length'] = $len = $this->decode($data,$p+4,4);
                        $value = substr($data,$p+8,$len);

                        if ( isset($this->notificationHandles[$tags[$timestamp][$i]['handle']]) ) {
                            $tag = $this->notificationHandles[$tags[$timestamp][$i]['handle']];
                            $tags[$timestamp][$i]['data'] = $this->tpy->decode($value,$tag);
                            $tags[$timestamp][$i]['path'] = $tag->path;
                        } else {
                            $this->deleteNotificationHandle($tags[$timestamp][$i]['handle']);
                            unset($tags[$timestamp][$i]);
                        }

                        $p += $len+8;
                    }
                }

                return array(
                    'head' => $head,
                    'samples' => $tags,
                    'stamps' => $stamps,
                );
            case 9: // ADS Read Write
                if ( $head['state'] == 4 ) 
                    return array(
                        'head' => $head,
                        'indexGroup' => $this->decode($data,0,4),
                        'indexOffset' => $this->decode($data,4,4),
                        'readLength' => $this->decode($data,8,4),
                        'writeLength' => $this->decode($data,12,4),
                        'dataLength' => strlen($this->decode($data,16)),
                        'data' => substr($data,16),
                    );
                else {
                    $len = $this->decode($data,4,4);
                    return array(
                        'head' => $head,
                        'error' => $this->decode($data,0,4),
                        'length' => $len,
                        'rlength' => strlen(substr($data,8,$len)),
                        'data' => substr($data,8,$len)
                    );
                }
            default:
                return array(
                    'head' => $head,
                    'data' => $data

                );
        }
    }	/*}}}*/
    function parseADSHeader( $package ) {/*{{{*/
        return array(
            'target' => array(
                'netid' => $this->decode($package,0,6,'.'),
                'port' => $this->decode($package,6,2)
            ),
            'source' => array(
                'netid' => $this->decode($package,8,6,'.'),
                'port' => $this->decode($package,14,2)
            ),
            'command' => $this->decode($package,16,2),
            'state' => $this->decode($package,18,2),
            'length' => $this->decode($package,20,4),
            'error' => $this->decode($package,24,4),
            'invoke' => $this->decode($package,28,4),
        );
    }/*}}}*/
    function command( $cmd, $data='',$tag='',$pkt=null ) {/*{{{*/
        $this->id++;

        note(debug,'Sending "'.$this->commandnames[$cmd].'" with invoke '.$this->id);

        error_reporting(E_ALL);
        $flags = 4;

        $this->tags[$this->id] = array(
            'tag' => $tag,
            'pkt' => $pkt
        );

        if ( $cmd == 7 ) {
            $this->tags[$this->id]['handle'] = $this->decode($data);
        }

        $package = 
            substr($this->to,0,8).
            substr($this->from,0,8).
            $this->toChr($cmd,2). // command
            $this->toChr($flags,2). // flags 0x00=request|0x01=response|0x04=ads command
            $this->toChr(strlen($data),4). // data length
            $this->toChr('',4). // error code
            $this->toChr($this->id,4). // invoke
            $data;

        //if ( isset($this->show) ) 
        //    print_r($this->parseADS($package));

        $this->send( $package );
    }/*}}}*/
    function send( $package ) {/*{{{*/

        $len = $this->toChr(strlen($package),4);
        $package = "\x00\x00".$len.$package;

        //$this->show(substr($package,0,6),'SEND TCP Header');
        //$this->show(substr($package,6,32),'SEND AMS Header');
        //$this->show(substr($package,38),'SEND ADS Data');

        if ( !socket_write($this->s,$package,strlen($package)) ) {

            trigger_error("socket_write() failed, reason: " .
            socket_strerror(socket_last_error()));

            socket_close($this->s);
            $this->s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

            if ( !socket_connect($this->s, $this->ip, 48898) )
                return trigger_error("Failed to connect"); // ADS port

            socket_write($this->s,$package,strlen($package));
        }
    }/*}}}*/

    function show($str, $head = '##') {/*{{{*/
        if ( !isset($this->show) )
            return;

        $ret  = "";
        $in = $str;

        do {
            $chr = substr($str,0,1);
            $str = substr($str,1);
            $hex = str_pad(dechex(ord($chr)),2,"0",STR_PAD_LEFT );
            if ( ord($chr) > 32 && ord($chr) < 127 )
                $ret .= " [".$chr." ".$hex."]";
            else 
                $ret .= " [".$hex."]";
        } while ($str != "");

        $data = explode("]",$ret);
        $ret = '';

        while ( count($data)>1 ) {
            $row = array_slice($data,0,16);
            $ret .= implode($row,']')."]\n";
            $data = array_slice($data,16);
        }

        echo "$head: ".strlen($in)."byte\n$ret\n";
    }/*}}}*/
    function toChr( $str, $len = 0 ) {/*{{{*/
        $ret = '';
        if ( is_numeric($str) ) {
            do {
                $chr = $str % 256;
                $str = intval($str/256);
                $ret .= chr($chr);
            } while ($str != "");
        } else {
            do {
                $chr = substr($str,0,1);
                $str = substr($str,1);
                $ret .= chr($chr);
            } while ($str != "");
        }
        if ( $len > 0 )
            $ret = str_pad($ret,$len,"\x00",STR_PAD_RIGHT);

        return $ret;
    }/*}}}*/
	private $globalErrorCode = array(/*{{{*/
		'No error',
		'Internal error',
		'No runtime',
		'Allocating locked memory error',
		'Insert Mailbox error',
		'Wrong recive HMSG',
		'Target port not found',
		'Target machine not found',
		'Unkown command ID',
		'Bad task ID',
		'No IO',
		'Unknown AMS command',
		'Win 32 error',
		'Port not connected',
		'Invalid AMS length',
		'Invalid AMS Net id',
		'Low installation level',
		'No debug available',
		'Port disabled',
		'Port already connected',
		'AMS sync Win32 error',
		'AMS sync timeout',
		'AMS sync AMS error',
		'AMS sync no index map',
		'Invalid AMS port',
		'No memory',
		'TCP send error',
		'Host unreachable',
        1792 => 'error class <device error>',
        1793 => 'Service is not supported by server',
        1794 => 'invalid index group',
        1795 => 'invalid index offset',
        1796 => 'reading/writing not permitted',
        1797 => 'parameter size not correct',
        1798 => 'invalid parameter value(s)',
        1799 => 'device is not in a ready state',
        1800 => 'device is busy',
        1801 => 'invalid context (must be in Windows)',
        1802 => 'out of memory',
        1803 => 'invalid parameter value(s)',
        1804 => 'not found (files, ...)',
        1805 => 'syntax error in command or file',
        1806 => 'objects do not match',
        1807 => 'object already exists',
        1808 => 'symbol not found',
        1809 => 'symbol version invalid',
        1810 => 'server is in invalid state',
        1811 => 'AdsTransMode not supported',
        1812 => 'Notification handle is invalid',
        1813 => 'Notification client not registered',
        1814 => 'no more notification handles',
        1815 => 'size for watch too big',
        1816 => 'device not initialized',
        1817 => 'device has a timeout',
        1818 => 'query interface failed',
        1819 => 'wrong interface required',
        1820 => 'class ID is invalid',
        1821 => 'object ID is invalid',
        1822 => 'request is pending',
        1823 => 'request is aborted',
        1824 => 'signal warning',
        1825 => 'invalid array index',
        1826 => 'symbol not active -> release handle and try again',
        1827 => 'access denied',
        1856 => 'Error class <client error>',
        1857 => 'invalid parameter at service',
        1858 => 'polling list is empty',
        1859 => 'var connection already in use',
        1860 => 'invoke ID in use',
        1861 => 'timeout elapsed',
        1862 => 'error in win32 subsystem',
        1863 => 'Invalid client timeout value',
        1864 => 'ads-port not opened',
        1872 => 'internal error in ads sync',
        1873 => 'hash table overflow',
        1874 => 'key not found in hash',
        1875 => 'no more symbols in cache',
        1876 => 'invalid response received',
        1877 => 'sync port is locked',
	);/*}}}*/

    function intercom_event($package) {/*{{{*/
        $package = base64_decode($package);

        /*$this->show(substr($package,0,6),'READ TCP Header');
        $this->show(substr($package,6,32),'READ AMS Header');
        $this->show(substr($package,38),'READ ADS Data');*/

        $package = substr($package,6); // Remove the tcp header 6 bytes

        $data = $this->parseADS($package);

        if ( $data['head']['error'] ) {
            note(warning,'- Negative "'.$this->commandnames[$data['head']['command']].'" with invoke '.$data['head']['invoke']);

            trigger_error($this->globalErrorCode[$data['head']['error']].' Error '.$data['head']['error'],E_USER_ERROR);
            return;
        }

        note(debug,'+ Positive "'.$this->commandnames[$data['head']['command']].'" with invoke '.$data['head']['invoke']);

        if ( $data['head']['invoke'] != 0 && isset($this->tags[$data['head']['invoke']]) ) {
            $cmd = $this->tags[$data['head']['invoke']];
        } else
            $cmd = array('tag'=>'','pkt'=>'');

        if ( isset($data['error']) && $data['error'] ) {
            if ( $cmd['pkt'] )
                $this->nak($cmd['pkt'],'ADS error: 0x'.strtoupper(dechex($data['error'])).' for tag '.$cmd['tag']->Name);

            return !trigger_error('ADS error: 0x'.strtoupper(dechex($data['error'])).' for tag '.$cmd['tag']->Name,E_USER_NOTICE);
        }

        switch ( $data['head']['command']  ) {
            case 1: // device info
                unset($data['head']);

                $this->setState('node.device',$data);
                return;
            case 2: // read
                if ( !isset($data['data']) ) 
                    return null;

                if ( $cmd['pkt'] )
                    $this->ack($cmd['pkt'],$data['data']);
                else
                    $this->setState($data['data']);
                return;
            case 3: // write
                if ( $cmd['pkt'] )
                    $this->ack($cmd['pkt']);
                return;
            case 4: // state info
                unset($data['head']);

                $this->setState('node.state',$data);

                if ( $cmd['tag'] )
                    call_user_func(array($this,$cmd['tag']));
                return;
            case 6: // add notification handle
                note(notice,'Saving new notification handle: '.$data['handle']);
                $this->notificationHandles[$data['handle']] = $cmd['tag'];
                return ;
            case 7: // delete notification handle
                note(notice,'Deleting notification handle: '.$cmd['handle']);
                unset($this->notificationHandles[$cmd['handle']]);
                return ;
            case 8: // notification
                foreach($data['samples'] as $sample)
                    foreach($sample as $value)
                        $this->setState($value['path'],$value['data']);
                return;
            default:
                print_r($data);
        }
    }/*}}}*/

    function _child_setup() {/*{{{*/
        pcntl_signal( SIGALRM,array($this,'_child_alarm') );
        pcntl_alarm(5);
    }/*}}}*/
    function _child_alarm() {/*{{{*/
        pcntl_alarm(9);

        $package = 
            substr($this->to,0,8).
            substr($this->from,0,8).
            $this->toChr(4,2). // command
            $this->toChr(0x04,2). // flags 0x00=request|0x01=response|0x04=ads command
            $this->toChr(0,4). // data length
            $this->toChr(0,4). // error code
            $this->toChr(0,4); // invoke

        //if ( isset($this->show) ) 
        //    print_r($this->parseADS($package));

        //die();

        $this->send( $package );
    }/*}}}*/
    function _child() {/*{{{*/
        //if ( socket_select(null,NULL,NULL,0) ) {
        //}


        if ( !isset($this->buff) )
            $this->buff = '';

        if ( $line = socket_read($this->s,1024,PHP_BINARY_READ) ) {
            $this->buff .= $line;
            
        //if (false !== ($chars = socket_recv($this->s, $line, 1024, MSG_DONTWAIT))) {
           // if( !$chars) 
           //     usleep(10000);
           // else {
               // $this->show($this->buff,"NEW");
           //}
        } elseif ($error = socket_last_error() ) {
            if ( $error != 4 && $error != 11 ) {
                $this->emergency("socket_recv() failed, reason: " .
                    socket_strerror($error));
            }
        }

        if ( $this->prev != $this->buff ) {
            $this->prev = $this->buff;

            while ( $start = strpos($this->buff,"\x00\x00") !== false && strlen($this->buff)>38 ) {
                $len = $this->decode($this->buff,$start+1,4) + 6;

                $package = substr($this->buff,$start-1,$len);

                if( substr($package,6,16) != $this->from.$this->to ) {
                    $this->show($package,"TROWING");
                    //$this->show($this->from.$this->to,"TROWING");

                    $this->buff = substr($this->buff,$start+1);
                    return;
                }

                if ( strlen($package) < ($len) ) {
                    $this->show($package,"SHORT");
                    echo "To short $start - ".strlen($package)."<$len\n";
                    return;
                }

                $this->buff = substr($this->buff,$start+$len-1);

                //$this->show($this->buff,"found: ".$start);
                //echo "---------------------------- SEND (".$len.") \n";
                $this->intercom(base64_encode($package));
                //echo "---------------------------- SEND\n\n";
            }
        }
    }/*}}}*/
}

class tpy {/*{{{*/
    function __construct($file) {
        $this->x = simplexml_load_file($file);
    }

    function tag($name) {/*{{{*/
        foreach($this->x->Symbols->Symbol as $key => $line) {
            if ( preg_match('/^'.$line->Name.'(\.|$)/',$name) ) {
//            if ( (string)$line->Name == substr($name,0,strlen((string)$line->Name)) ) {
                if ( !isset($line->BitOffs) )
                    $line->BitOffs = $line->IOffset*8;

                if ( !isset($line->Group) )
                    $line->Group = $line->IGroup;

                //if ( substr($name,strlen((string)$line->Name)) <> '' )
                    $line = $this->typeOffset($line,substr($name,strlen((string)$line->Name)));

                $line->path = $name;

                return $line;
            }
        }

        return !trigger_error('Cound not find tag "'.$name.'"',E_USER_WARNING);
    }/*}}}*/
    function type($name) {/*{{{*/
        $ret = new stdClass();
        if ( substr($name,0,6) == 'STRING' ) {
            $ret->BitSize = substr($name,7,-1)*8+8;
            $ret->strSize = substr($name,7,-1);
            return $ret;
        }

        switch($name) {
            CASE 'DWORD':
            CASE 'UDINT':
            CASE 'DINT':
                $ret->BitSize = 32;
                return $ret;
            CASE 'UINT':
            CASE 'INT':
            CASE 'WORD':
            CASE 'UINT':
                $ret->BitSize = 16;
                return $ret;
            CASE 'BYTE':
            CASE 'BOOL':
                $ret->BitSize = 8;
                return $ret;
            CASE 'TIME':
                $ret->BitSize = 32;
                return $ret;

            CASE 'LREAL':
                $ret->BitSize = 64;
                return $ret;
        }

        foreach($this->x->Symbols->Symbol as $key => $line) {
            if ( $this->checkName( (string)$line->Name, $name ) ) {
                return $line;
            }
        }
        foreach($this->x->DataTypes->DataType as $key => $line) {
            if ( $this->checkName( (string)$line->Name, $name ) ) {
                return $line;
            }
        }

        foreach($this->x->Functions->Function as $key => $line) {
            if ( $this->checkName( (string)$line->Name, $name ) ) {
                return $line;
            }
        }

        return !trigger_error('Cound not find type "'.$name.'"',E_USER_WARNING);
    }/*}}}*/
    function checkName( $part, $full ) {/*{{{*/
        $full = ltrim($full,'.');
        $part = ltrim($part,'.');

        if ( $part == $full )
            return true;

        $part2 = $part.'.';
        if ( $part2 == substr($full,0,strlen($part2)) ) 
            return true;

        $part3 = $part.'[';
        if ( $part3 == substr($full,0,strlen($part3)) ) 
            return true;
    }/*}}}*/
    function typeOffset($tag,$sub) {/*{{{*/
        if ( in_array($tag->Type,array('INT','BOOL','WORD','TIME')) ) {
            $ret = new stdClass();
            $ret->Name = (string)$tag->Name;
            $ret->Tag = $tag;
            $ret->TypeTag = $this->type($tag->Type);
            $ret->Type = (string)$tag->Type;
            $ret->Group = (int)$tag->Group;
            $ret->BitOffs = (int)$tag->BitOffs;
            $ret->BitSize = (int)$tag->BitSize;
            return $ret;
		}

        if ( !$sub ){
            $ret = new stdClass();
            $ret->Name = (string)$tag->Name;
            $ret->Tag = $tag;
            $ret->TypeTag = $this->type($tag->Type);
            $ret->Type = (string)$tag->Type;
            $ret->Group = (int)$tag->Group;
            $ret->BitOffs = (int)$tag->BitOffs;
            $ret->BitSize = (int)$tag->BitSize;
            return $ret;
        }

        $sub = trim($sub,'.');
        // Om det är en array, leta då upp rätt index
        if ( preg_match('/array \[([\-\d]+)\.\.([\-\d]+)\] of (.*)/i',(string)$tag->Type,$match) ) {
            $type = $this->type($match[3]);

            if ( preg_match('/([\d]+).*/i',$sub,$match2) ) {
                $index = $match2[1];
                if ( $index < $match[1] || $index > $match[2] )
                    return !trigger_error("Index is outside array size $index<>({$match[1]}..{$match[2]})",E_USER_ERROR);
            } else
                return !trigger_error('Cant find array index in "'.$sub.'"',E_USER_ERROR);

            $sub = substr($sub,strlen("[$index]"));

            $ret = new stdClass();
            $ret->Tag = $tag;
            $ret->Type = (string)$type->Name;
            $ret->Group = (int)$tag->Group;
            $ret->BitOffs = (int)$tag->BitOffs+($type->BitSize*($index-$match[1]));
            $ret->Index = $index;
            $ret->BitSize = (int)$type->BitSize;

            return $this->typeOffset($ret,$sub);
        }

        // Sök i subitems
        if ( $type = $this->type($tag->Type) )
            foreach($type->SubItem as $key => $line) {
                if ( $this->checkName( (string)$line->Name, $sub ) ) {
                    if ( isset($line->Type['Pointer']) ) // Ignore pointers
                        return !trigger_error('Found a pointer, cancel',E_USER_ERROR);
                    $sub = substr($sub,strlen($line->Name));

                    $ret = new stdClass();
                    $ret->Name = (string)$line->Name;
                    $ret->Tag = $tag;
                    $ret->TypeTag = $type;
                    $ret->Type = (string)$line->Type;
                    $ret->Group = (int)$tag->Group;
                    $ret->BitOffs = (int)$tag->BitOffs+((int)$line->BitOffs);
                    $ret->BitSize = (int)$line->BitSize;
                    return $this->typeOffset($ret,$sub);
                }
            }

        return !trigger_error('Could not find "'.$sub.'" in "'.$tag->Name.'"',E_USER_WARNING);
        return array(
            $tag,
            "'$sub'",
            $type
        );
    }/*}}}*/
    function decode( $data, $tag, $type = NULL) {/*{{{*/
        if( !is_array($data) )
            $data = array('data'=>$data);

        if ( substr((string)$tag->Type,0,6) == 'STRING' ) {
            return utf8_encode(substr($data['data'],0,strpos($data['data'],chr(0))));
        }

        switch( $tag->Type ) {
            case 'BYTE':
                return ord($data['data']);
            case 'BOOL':
                return (ord($data['data'])>0)?true:false;
            case 'REAL':
                list($unpacked) = array_values(unpack('f', $data['data']));
                return $unpacked;

            case 'LREAL':
                list($unpacked) = array_values(unpack('d', $data['data']));
                return $unpacked;

            case 'UDINT':
                list($unpacked) = array_values(unpack('L', $data['data']));
                return $unpacked;

            case 'UINT':
                list($unpacked) = array_values(unpack('S', $data['data']));
                return $unpacked;

            case 'TIME':
            case 'DINT':
            case 'DWORD':
                list($unpacked) = array_values(unpack('I', $data['data']));
                return $unpacked;
            case 'WORD': // UNSIGNED
                list($unpacked) = array_values(unpack('S', $data['data']));
                return $unpacked;
            case 'INT':
                list($unpacked) = array_values(unpack('s', $data['data']));
                return $unpacked;


            default:
                $ret = array();

                if ( !isset($type) )
                    if ( !$type = $this->type((string)$tag->Type) )
                        return !trigger_error('UNKNOWN type "'.$tag->Type.'"',E_USER_ERROR);

                if ( preg_match('/array \[(.+)\.\.(.+)\] of (.*)/i',(string)$tag->Type,$match) ) {
                    if ( !$type2 = $this->type($match[3]) )
                        return false;

                    $type->Type[0] = $match[3];
                    for($i=$match[1];$i<=$match[2];$i++) {
                        $ret[$i] = $this->decode(
                            array(
                                'data'=>substr(
                                    $data['data'],
                                    (($type2->BitSize/8)*($i-$match[1])),
                                    ($type2->BitSize/8)
                                ) 
                            ),
                            $type,
                            $type2
                        );
                    }
                    return $ret;
                }

                foreach( $type->SubItem as $key => $line ) {
                    if ( isset($line->Type['Pointer']) ) // Ignore pointers 
                        continue;

                    $name = (string)$line->Name;
                    $txt = substr(
                         $data['data'],
                         ($line->BitOffs/8),
                         ($line->BitSize/8)
                    );

                    $ret[$name] = $this->decode(
                        array(
                            'data'=>$txt
                        ),
                        $line
                    );
                }
                return $ret;
        }
    }/*}}}*/

    function encode( $data, $tag, $type = NULL) {
        if ( substr((string)$tag->Type,0,6) == 'STRING' ) {
            return utf8_decode($data);
        }

        switch( $tag->Type ) {
            case 'BYTE':
                return chr($data);
            case 'BOOL':
                return ($data)?chr(1):chr(0);
            case 'REAL':
                return pack('f', $data);
            case 'LREAL':
                return pack('d', $data);
            case 'UDINT':
                return pack('L', $data);
            case 'UINT':
                return pack('S', $data);
            case 'TIME':
            case 'DINT':
            case 'DWORD':
                return pack('I', $data);
            case 'WORD': // UNSIGNED
                return pack('S', $data);
            case 'INT':
                return pack('s', $data);

            default:
                $ret = '';

                if ( !isset($type) )
                    if ( !$type = $this->type((string)$tag->Type) )
                        return !trigger_error('UNKNOWN type "'.$tag->Type.'"',E_USER_ERROR);

                if ( preg_match('/array \[(.+)\.\.(.+)\] of (.*)/i',(string)$tag->Type,$match) ) {
                    if ( !$type2 = $this->type($match[3]) )
                        return false;

                    $type->Type[0] = $match[3];
                    for($i=$match[1];$i<=$match[2];$i++) {
                        if ( isset($data[$i]) ) {
                            $ret .= str_pad(
                                $this->encode(
                                    $data[$i],
                                    $type,
                                    $type2
                                ),
                                ($type2->BitSize/8),
                                chr(0)
                            );
                        } else {
                            $ret .= str_pad(
                                substr(
                                    $this->source,
                                    (($type2->BitSize/8)*($i-$match[1])),
                                    ($type2->BitSize/8)
                                ),
                                ($type2->BitSize/8),
                                chr(0)
                            );
                        }
                    }
                    return $ret;
                }

                foreach( $type->SubItem as $key => $line ) {
                    if ( isset($line->Type['Pointer']) ) // Ignore pointers 
                        continue;

                    $name = (string)$line->Name;

                    if ( !isset($data[$name]) ) {
                        note(warning,"$name is missing from tag\n");
                        $ret .= str_pad(
                            substr(
                                $this->source,
                                ($line->BitOffs/8),
                                ($line->BitSize/8)
                            ),
                            ($line->BitSize/8),
                            chr(0)
                        );
                    } else {
                        $ret .= str_pad(
                            $this->encode(
                                $data[$name],
                                $line
                            ),
                            ($line->BitSize/8),
                            chr(0)
                        );
                    }
                }
                return $ret;
        }
    }
}/*}}}*/

$b = new beckhoff();
$b->start('beckhoff','_child','_child_setup');

?>
