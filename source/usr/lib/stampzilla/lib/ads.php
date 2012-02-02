<?php

class ads {
    public $source = null;

    function __construct( $ip,$to,$tport, $from, $fport ) {/*{{{*/
        $to = explode(".",$to);
        $from = explode(".",$from);

        $this->ip = $ip;

        foreach($to as $line)
            $this->to .= $this->toChr($line);

        foreach($from as $line)
            $this->from .= $this->toChr($line);

        $this->to .= $this->toChr($tport,2);
        $this->from .= $this->toChr($fport,2);
	
        if ( ($this->s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false ) {
            die("socket_create() failed, reason: " .
                socket_strerror(socket_last_error()) . "\n");
        }
        $to = array_slice($to,0,4);

	$this->connect();
    }/*}}}*/

    function connect() {
        if ( socket_connect($this->s, $this->ip, 48898) === false ) {
            die("socket_select() failed, reason: " .
                socket_strerror(socket_last_error()) . "\n");
        }
    }
    function __destruct() {/*{{{*/
        socket_close($this->s);
    }/*}}}*/

    function read( $otag ) {
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
		$this->toChr($size/8,4)
    	);

        if ( $data['error'] ) {
            !trigger_error('ADS error: 0x'.strtoupper(dechex($data['error'])).' for tag '.$tag->Name,E_USER_NOTICE);
		print_r($data);
	}

		if ( !isset($data['data']) ) 
			return null;

        if ( strlen($data['data']) > 0 )
            return $this->tpy->decode($data,$tag);

        return null;
    }

    function write( $tago, $data, $complete = false ) {
        if ( !isset($this->tpy) )
            return !trigger_error("No TPY file is loaded",E_USER_ERROR);
        
        if ( !$tag = $this->tpy->tag($tago) )
            return false;

        if ( !$complete && false) {
            $tmp = $this->command( 
                2, 
                $this->toChr((int)$tag->Group,4).
                $this->toChr((int)$tag->BitOffs/8,4).
                $this->toChr((int)$tag->BitSize/8,4)
                ,$tago
            );
			if ( isset($tmp['data']) )
	            $this->tpy->source = $tmp['data'];
        }

        $data = $this->tpy->encode($data,$tag);
        //print_r($tag);
//        print_r(ord($data));
  //      print_r(ord($this->tpy->source));
        $this->tpy->source = null;
        return $this->rawWrite( (int)$tag->Group,(int)$tag->BitOffs/8,$data );
    }

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
    function readState() {/*{{{*/
        return $this->command(4);
    }/*}}}*/


    function decode( $data, $start, $len,$split = '' ) {/*{{{*/
        $ret = '';
        $str = substr($data,$start,$len);
        $cnt = 0;

        do {
            $chr = substr($str,0,1);
            $str = substr($str,1);
            $hex = str_pad(dechex(ord($chr)),2,"0",STR_PAD_LEFT );
            if ( $split )
                $ret[] = ord($chr);
            else {
                //$ret *= 256;
                if ( $cnt > 0 )
                    $ret += ord($chr) * (256*$cnt);
                else
                    $ret += ord($chr);
                $cnt++;
            }
        } while ($str != "");

        return $ret;
    }/*}}}*/

    function parseADS( $package ) {/*{{{*/
        if ( !strlen($package) )
            return null;

        $head = $this->parseADSHeader( $package );
        $data = substr($package,32);
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
                    'name' => substr($data,8,16)
                );
            case 2: // ADS Read
            case 9: // ADS Read Write
                $len = $this->decode($data,4,4);
                return array(
                    'head' => $head,
                    'error' => $this->decode($data,0,4),
                    'length' => $len,
                    'rlength' => strlen(substr($data,8,$len)),
                    'data' => substr($data,8,$len)
                );
            case 3: // ADS Write
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
                return array(
                    'head' => $head,
                    'error' => $this->decode($data,0,4),
                    'handle' => $this->decode($data,4,4)
                );
            case 7: // ADS Delete device notification
                return array(
                    'head' => $head,
                    'error' => $this->decode($data,0,4),
                );
            case 8: // ADS Device notification
                $cnt = $this->decode($data,8,4);
                $timestamp  = $this->decode($data,0,8);
                $data = substr($data,12);
                $samples = array();

                for( $i=0;$i<$cnt;$i++ ) {
                    $samples[$i]['handle'] = $this->decode($data,0,4);
                    $samples[$i]['length'] = $this->decode($data,4,4);
                    $samples[$i]['data'] = $this->decode($data,8,$samples[$i]['length']);

                    $data = substr($data,$samples[$i]['length']);
                }			

                return array(
                    'head' => $head,
                    'timestamp' => $timestamp,
                    'samples' => $samples,
                );
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
    function command( $cmd, $data='',$tag='' ) {/*{{{*/
        error_reporting(E_ALL);

        $state = "\x04";
        $package = 
            $this->to.
            $this->from.
            str_pad($this->toChr($cmd),2,"\x00",STR_PAD_RIGHT).
            str_pad($state,2,"\x00",STR_PAD_RIGHT).
            str_pad($this->toChr(strlen($data)),4,"\x00",STR_PAD_RIGHT).
            str_pad("\x00",4,"\x00",STR_PAD_RIGHT).
            str_pad(substr(microtime(),-4),4,"\x00",STR_PAD_RIGHT).
            $data;
	
	//print_r($this->parseADS($package));
	//die();

        $this->send( $package );
        $res = "";
        do{
            //if ( socket_select(null,NULL,NULL,0) ) {
            //}

            if ( $line = socket_read($this->s,1024,PHP_BINARY_READ) ) {
            //if (false !== ($bytes = socket_recv($this->s, $res, 1024, MSG_DONTWAIT))) {
                $res .= $line;
            } else {
                trigger_error("socket_recv() failed, reason: " .
                    socket_strerror(socket_last_error()));
                return null;
            }

            if ( $start = strpos($res,"\x00\x00") !== false && strlen($res)>6 ) {
                $len = $this->decode($res,2,4);

                if ( strlen($res) < ($len+6) )
                    continue;

                $package = substr($res,6,$len);
                
                $resp = $this->parseADS($package);

                if ( $resp['head']['error'] ) {
                    trigger_error($this->globalErrorCode[$resp['head']['error']].' Error '.$resp['head']['error'],E_USER_ERROR);
		    return $resp;
                } else
                    return $resp;

                break;
            }
        } while( $line != "" );
    }/*}}}*/
    function send( $package ) {/*{{{*/

        $len = $this->toChr(strlen($package),4);
        $package = "\x00\x00".$len.$package;

        //$this->show(substr($package,0,6),'TCP Header');
        //$this->show(substr($package,6,32),'AMS Header');
        //$this->show(substr($package,38),'ADS Data');
	
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
		'Host unreachable'
	);/*}}}*/

    function tpy( $file ) {
        $this->tpy = new tpy($file);
    }
}


class tpy {
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

}
//$o = new ads( "192.168.1.107.1.1",800,"192.168.1.250.1.1",800 );
//$o = new ads( "10.10.1.131.1.1",801,"0.0.0.0.0.0",0 );
//$o = new ads( "10.10.10.129.1.1",801,"10.10.10.1.1.1",800 );
//$o = new ads( "192.168.1.47.1.1",801,"0.0.0.0.0.0",0 );

?>
