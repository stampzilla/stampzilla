<?php

// Get the current working dir of a process
function getPwdX( $pid ) {
    exec("pwdx $pid",$ret);
    $dir = explode(': ',$ret[0],2);

    if ( !count($dir) == 2 )
        return false;
    return $dir[1];
}

// List all active php processes and return those who belongs to stampzilla
function listActive() {/*{{{*/
    // USER       PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
    exec("ps auxf|grep php",$out);

    $active = array();
    foreach ( $out as $line ) {
        //if ( substr($line,65,4) == 'php ' ) {
        if ( !strstr(substr($line,65),'grep') && !strstr(substr($line,65),'init.php') ) {
            $pid = trim(substr($line,9,7));

            // Ignore self
            if ( $pid == getmypid() )
                continue;

	    	$pwd = getPwdX($pid);

            // Ignore scripts that dont belong to stampzilla (different pwd)
            if ( $pwd != getcwd() )
                continue;

            $active[] = array(
                'owner' => trim(substr($line,0,9)),
                'pid' => $pid,
                'cpu' => trim(substr($line,16,5)),
                'mem' => trim(substr($line,19,5)),
                'vsz' => trim(substr($line,24,7)),
                'rss' => trim(substr($line,31,6)),
                'tty' => trim(substr($line,37,6)),
                'stat' => trim(substr($line,43,8)),
                'start' => trim(substr($line,51,6)),
                'time' => trim(substr($line,57,7)),
                'command' => trim(substr($line,65)),
                'file' => trim(substr($line,65))
            );
        }
    }
    return $active;
}/*}}}*/

function arguments($args ) {
    $ret = array(
        'exec'      => '',
        'options'   => array(),
        'flags'     => array(),
        'arguments' => array(),
    );

    $ret['exec'] = array_shift( $args );

    while (($arg = array_shift($args)) != NULL) {
        // Is it a option? (prefixed with --)
        if ( substr($arg, 0, 2) === '--' ) {
            $option = substr($arg, 2);

            // is it the syntax '--option=argument'?
            if (strpos($option,'=') !== FALSE)
                array_push( $ret['options'], explode('=', $option, 2) );
            else
                array_push( $ret['options'], $option );
           
            continue;
        }

        // Is it a flag or a serial of flags? (prefixed with -)
        if ( substr( $arg, 0, 1 ) === '-' ) {
            for ($i = 1; isset($arg[$i]) ; $i++)
                $ret['flags'][] = $arg[$i];

            continue;
        }

        // finally, it is not option, nor flag
        $ret['arguments'][] = $arg;
        continue;
    }
    return $ret;
}

function md5sums( $path, $hash=true ,$data = array(), $root = '' ) {
	if ( !$root )
		$root = $path;

	if( is_dir($path) ) {
		$content = scandir($path);
		foreach($content as $key => $line) {
			if ( substr($line,0,1) == '.' )
				continue;

			$data = md5sums($path."/$line",$hash,$data,$root);
		}
	} else {
		if ( $hash ) 
			$data[] = array(
				md5(file_get_contents($path)),
				substr($path,strlen($root)+1),
			);
		else 
			$data[] = '/etc/'.substr($path,strlen($root)+1);
	}

	return $data;
}

// Remove a directory recursive
function rrmdir($dir) {
	if (is_dir($dir)) {
     	$objects = scandir($dir);
     	foreach ($objects as $object) {
       		if ($object != "." && $object != "..") {
         		if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
       		}
     	}
     	reset($objects);
    	rmdir($dir);
   	}
} 

// Copy recursive to a dir and create target dir if it dont exists
function cpr( $from, $to ) {

	$from = rtrim($from,'/');
	$to = rtrim($to,'/');

    echo "$from\n";

    /*if ( is_link($from) )
        $from = readlink($from);

    if ( is_link($to) )
        $to = readlink($to);*/

	if ( is_dir($from) ) {
		$content = scandir($from);
		foreach($content as $key => $line) {
			if ( substr($line,0,1) == '.' )
				continue;

			cpr($from."/$line",$to);
		}
	} elseif( is_file($from) ) {
		// Check dirs, and create them
		$target = $to;
		$dirs = explode("/",dirname(ltrim($from,'/')));
		foreach($dirs as $key => $line) {
			$target .= "/$line";
			if ( !is_dir($target) )
				if ( !mkdir($target) )
					return trigger_error("Failed creating dir ($target)",E_USER_ERROR);

		}
		if ( !copy($from,$target.'/'.basename($from)) )
			return trigger_error("Failed to copy file ($from -> $target)",E_USER_ERROR);
	} else {
        return trigger_error("Dir/file $from do not exist",E_USER_ERROR);
    }

	return true;
}

?>
