#!/usr/bin/php -q
<?php

define("VERSION",'0.0.5');

require "lib/functions.php";

$args = arguments($_SERVER['argv']);
$pwd = getcwd();
chdir('/usr/lib/stampzilla/components/');

if ( isset($_SERVER["argv"][1]) ) {
    command($args['arguments'],$pwd);
} else {
    if ( $args['exec'] == '/etc/init.d/stampzilla' )
	echo "Usage: /etc/init.d/stampzilla {start|stop|help}\n";
    else
	command("help");
}

//if ( !is_file('/etc/stampzilla') )
//    if ( file_put_contents('/etc/stampzilla','') === false )
//        trigger_error("Failed to create /etc/stampzilla, check access\n",E_USER_ERROR);

function command($cmd,$pwd = '') {
    if ( is_string($cmd) )
        $arg = explode(" ",$cmd);
    else
        $arg = $cmd;

    switch($arg[0]) {
        case '--help':
        case 'help':/*{{{*/
            passthru("man stampzilla|col");
            break;/*}}}*/
        case 'list': /*{{{*/
            echo "Active processes:\n";
            if ( ($active = listActive()) ) {
                echo "PID     CPU  MEM  TIME   PROCESS\n";
                foreach( $active as $line ) {
                    echo str_pad($line['pid'],8,' ');
                    echo str_pad($line['cpu'],5,' ');
                    echo str_pad($line['mem'],5,' ');
                    echo str_pad($line['time'],7,' ');
                    echo $line['file']."\n";
                }
            }
            break;/*}}}*/
        case 'simplelist': /*{{{*/
            if ( ($active = listActive()) ) {
                foreach( $active as $line ) {
                    echo $line['file']."\n";
                }
            }
            break;/*}}}*/
        case 'debug':/*{{{*/
            echo "Not available yet!\n";
            break;
/*}}}*/
        case 'show':/*{{{*/
            echo "/etc/stampzilla/autostart.list:\n";
            echo file_get_contents('/etc/stampzilla/autostart.list');
            break;/*}}}*/
        case 'add':/*{{{*/
            if ( !isset($arg[1]) ) {
                echo "Wrong syntax: add <process name> <args>\n";
                break;
            }

            $arg = array_slice($arg,1);

            file_put_contents('/etc/stampzilla/autostart.list',implode($arg," ")."\n",FILE_APPEND);
            break;/*}}}*/
        case 'remove':/*{{{*/
            if ( !isset($arg[1]) ) {
                echo "Wrong syntax: remove <process name>\n";
                break;
            }

            $c = explode("\n",file_get_contents('/etc/stampzilla/autostart.list'));
            foreach( $c as $key => $line ) {
                if ( substr($line,0,strlen($arg[1])) == $arg[1] )
                    unset($c[$key]);
            }
            file_put_contents('/etc/stampzilla/autostart.list',implode($c,"\n"));
            break;/*}}}*/
        case 'restart':
        case 'stop':/*{{{*/
            $p = $arg;
            unset($p[0]);

            if ( ($active = listActive()) ) {
                require_once('../lib/udp.php');
                $udp = new udp('0.0.0.0',8282);
                foreach( $active as $line ) {
                    $node = str_replace('.php','',trim(substr(ltrim($line['file'],'\_'),4)));
                    if ( !isset($p[1]) || str_replace('.php','',$p[1]) == $node ) {
                        echo "Sending kill signal to {$node}\n";
                        $udp->broadcast(array(
                            'to'=>$node,
                            'cmd'=>'kill',
                            'from'=>'stampzilla'
                        ));
                    }
                }
            }

            $stop = time()+2;
            while ( $active = listActive() && time()<$stop ) {
                usleep(1000000);
            }

            if ( ($active = listActive()) ) {
                foreach( $active as $line ) {
                    $node = str_replace('.php','',trim(substr(ltrim($line['file'],'\_'),4)));
                    if ( !isset($p[1]) || str_replace('.php','',$p[1]) == $node ) {
                        echo "Force kill pid: {$line['pid']} ({$line['file']})\n";
                        exec("kill -9 ".$line['pid'],$ret);
                    }
                }
            }

            if ( $arg[0] != 'restart' )
                break;/*}}}*/
        case 'start':/*{{{*/
            unset($arg[0]);
            if ( isset($arg[1]) ) {
                $arg[1] = str_replace(".php",'',$arg[1]).".php";
                echo "Starting ".$arg[1]."\n";
                exec('nohup php '.implode($arg,' ').'> /dev/null&',$ret);
                //foreach( $ret as $line )
                //    echo "   ".$line."\n";
            } else {
                $c = explode("\n",file_get_contents('/etc/stampzilla/autostart.list'));
                foreach( $c as $key => $line ) {
                    if ( !$line )
                        continue;
                    echo "Starting ".$line."\n";
                    exec('nohup php '.str_replace(".php",'',$line).'.php> /dev/null&',$ret);
                 //   foreach( $ret as $line )
                 //       echo "   ".$line."\n";
                }
            }
			break;/*}}}*/
		case 'make':/*{{{*/
			echo "Collecting files...";
			echo getcwd()."\n";
			$tmp = '/tmp/stampzilla';

			// Clean dir if it exists
			if ( is_dir($tmp) )
				rrmdir("$tmp");

			// Create a temp dirs
			if ( !mkdir($tmp) )
				return !trigger_error("Failed to create tmp dir ($tmp)",E_USER_WARNING);

			if ( !mkdir("$tmp/DEBIAN") )
				return !trigger_error("Failed to create tmp dir ($tmp/control)",E_USER_WARNING);

			// Copy all files
			if ( !cpr('/usr/share/man/man1/stampzilla.1.gz',$tmp) ) 
				return false;

			if ( !cpr('/usr/share/doc/stampzilla',$tmp) ) 
				return false;

			if ( !cpr('/usr/share/stampzilla',$tmp) ) 
				return false;

			if ( !cpr('/usr/lib/stampzilla/',$tmp) ) 
				return false;
			if ( !chmod("$tmp/usr/lib/stampzilla/cli.php",0755) )
				return trigger_error("Failed to set file mode ($tmp/usr/lib/stampzilla/cli.php,755)",E_USER_ERROR);

			if ( !cpr('/etc/init.d/stampzilla',$tmp) ) 
				return false;
			if ( !chmod("$tmp/etc/init.d/stampzilla",0755) )
				return trigger_error("Failed to set file mode ($tmp/etc/init.d/stampzilla,755)",E_USER_ERROR);

			if ( !cpr('/usr/bin/stampzilla',$tmp) ) 
				return false;
			if ( !chmod("$tmp/usr/bin/stampzilla",0755) )
				return trigger_error("Failed to set file mode ($tmp/usr/bin/stampzilla,755)",E_USER_ERROR);

			// Write control
				// MD5 sums
				$md5 = md5sums("$tmp");
				foreach($md5 as $key => $line)
					$md5[$key] = implode($line,'  ');
				if ( file_put_contents("$tmp/DEBIAN/md5sums",implode($md5,"\n")."\n") === false )
					return trigger_error("Failed to write file ($tmp/DEBIAN/md5sums)",E_USER_ERROR);

				// configfiles
				$md5 = md5sums("$tmp/etc/",false);
				//$md5[] = '/etc/stampzilla';
				if ( file_put_contents("$tmp/DEBIAN/conffiles",implode($md5,"\n")."\n") === false )
					return trigger_error("Failed to write file ($tmp/DEBIAN/conffiles)",E_USER_ERROR);

				// Control
				if ( !file_put_contents("$tmp/DEBIAN/control",
					"Package: stampzilla\n".
					"Version: ".VERSION."\n".
					"Section: misc\n".
					"Priority: optional\n".
					"Architecture: all\n".
					"Depends: php5-cli\n".
					"Maintainer: Jonathan S-K <stampzilla@stamp.se>\n".
					"Description: The awsome stampzilla homeautomation is a PHP script based network. \n".
					" It uses UDP broadcast to communicate between nodes and gateways.\n"
				) )
					return trigger_error("Failed to write file ($tmp/DEBIAN/control)",E_USER_ERROR);

				if ( !file_put_contents("$tmp/DEBIAN/postinst",
					"#!/bin/bash -e\n".
					"update-rc.d stampzilla defaults\n"
				) )
					return trigger_error("Failed to write file ($tmp/DEBIAN/postint)",E_USER_ERROR);
				if ( !chmod("$tmp/DEBIAN/postinst",0755) )
					return trigger_error("Failed to set file mode ($tmp/DEBIAN/postinst,755)",E_USER_ERROR);

				if ( !file_put_contents("$tmp/DEBIAN/postrm",
					"#!/bin/bash -e\n".
					"update-rc.d -f stampzilla remove\n"
				) )
					return trigger_error("Failed to write file ($tmp/DEBIAN/postrm)",E_USER_ERROR);
				if ( !chmod("$tmp/DEBIAN/postrm",0755) )
					return trigger_error("Failed to set file mode ($tmp/DEBIAN/postrm,755)",E_USER_ERROR);
			echo "OK\n";			

			chdir($pwd);

			// Remove prev file
			if ( is_file("stampzilla_".VERSION."_all.deb" ) ) {
				echo "Removing old file...";
				if ( !unlink("stampzilla_".VERSION."_all.deb") )
					return trigger_error("Failed remove old file (stampzilla_".VERSION."_all.deb)",E_USER_ERROR);
				else
					echo "OK\n";
			}

			// Make the package
			echo "Building package...";
			exec("dpkg-deb --build /tmp/stampzilla stampzilla_".VERSION."_all.deb",$ret);
	
			// Check if success
			if ( !is_file("stampzilla_".VERSION."_all.deb") ) {
				die("FAIL\n\n".implode($ret,"\n"));
			} else {
				echo "OK (stampzilla_".VERSION."_all.deb)\n";
			}

			// Check integrity
			echo "Running lintian integrity check...";
			exec("lintian --allow-root stampzilla_".VERSION."_all.deb",$ret2);
			if ( !$ret2 ) 
				echo "OK\n\nGreat success!\n";

			// Clean up
			rrmdir("$tmp");

			//passthru("dpkg --info stampzilla_".VERSION."_all.deb");
        	break;/*}}}*/
        case 'send':/*{{{*/
            unset($arg[0]);
            print_r($arg);
            passthru("php send.php \"".implode($arg,"\" \"").'"');
            break;/*}}}*/
		case 'log':/*{{{*/
			require_once("lib/udp.php");
			require_once("lib/errorhandler.php");

			$udp = new udp('0.0.0.0','255.255.255.255',8281);
			while(1) {
				if ( !$pkt = $udp->listen() )
					continue;

				// Ignore packages that arent errors
				if (!isset($pkt['type']) || $pkt['type'] != 'log' ) 
					continue;

				// Format message
				echo format($pkt['level'],$pkt['message']);
			}
/*}}}*/
		case 'changelog':/*{{{*/
            $log = file_get_contents('https://api.github.com/networks/stampzilla/stampzilla/events');
            $log = json_decode($log,true);
            //print_r($log);
            krsort($log);
            foreach($log as $line) {
                if ( isset($line['payload']['commits']) ) {
                    foreach($line['payload']['commits'] as $com) {
                        echo date('Y-m-d H:i:s',strtotime($line['created_at']))." (".$com['author']['name'].")\n";
                        $m = explode("\n",$com['message']);
                        foreach($m as $l)
                            echo "\t".$l."\n";
                    }
                }
            }
            break;/*}}}*/
        default:
            return !trigger_error("Unknown command '{$arg[0]}'",E_USER_ERROR);
    }
}

?>
