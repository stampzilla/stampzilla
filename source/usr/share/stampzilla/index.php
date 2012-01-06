<?php
$layout = array(
	'rooms' => array(
		0 => 'Rooms',
	),
	'media' => array(
		0 => 'Media',
		'movie' => array(
			0 => 'Movie'
		),
		'series' => array(
			0 => 'Series'
		),
		'sound' => array(
			0 => 'Sound'
		),
		'pictures' => array(
			0 => 'Pictures'
		),
		'downloads' => array(
			0 => 'Downloads'
		)
	),
	'climate' => array(
		0 => 'Climate',
		'weather' => array(
			0 => 'Weather'
		),
		'heat' => array(
			0 => 'Heat'
		),
	),
	'alarm' => array(
		0 => 'Alarm',
		'status' => array(
			0 => 'Status'
		),
		'users' => array(
			0 => 'Users'
		),
		'log' => array(
			0 => 'Log'
		)
	),
	'consumption' => array(
		0 => 'Consumption'
	),
	'settings' => array(
		0 => 'Settings'
	)
);

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="chrome=1">
        <meta http-equiv="Expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
        <meta http-equiv="Pragma" content="no-cache"> 
        <title>stampzilla</title>

        <meta name="viewport" content="width=device-width,user-scalable=no" />

        <meta name="apple-mobile-web-app-capable" content="yes">
        <link rel="apple-touch-icon" href="images/icon.png" />
        <link rel="apple-touch-startup-image" href="img/splash.png" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black" /> 

        <script type="text/javascript" src="js/all.php"></script>

        <link href="css/base.css" rel="stylesheet" />
        <link href="css/editmode.css" rel="stylesheet" />
        <link rel="stylesheet" media="all and (orientation:portrait)" href="css/portrait.css">
        <link rel="stylesheet" media="all and (orientation:landscape)" href="css/landscape.css">
        <script language="javascript">
			toggle = function(obj, on, off) {
				if ( obj.hasClass('true') ) {
					obj.removeClass('true');
					cmd = off;
				} else {
					obj.addClass('true');
					cmd = on;
				}
				//$('iframe').src="send.php?"+cmd;
			}
            radio = function(obj, on) {
				for(var i=0;i<obj.parentNode.childNodes.length;i++) {
					obj.parentNode.childNodes[i].className="radio";
				};

                obj.addClass('true');
                //$('iframe').src="send.php?"+on;
            }

			menu.layout = <?php echo json_encode($layout,JSON_FORCE_OBJECT); ?>;

			updateClock = function() {
				setTimeout('updateClock()',1000);
				now = new Date();
				months = new Array('Januari','Februari','Mars','April','Maj','Juni','Juli','Augusti','September','Oktober','November','December');
				$('time').innerHTML = (now.getHours()<10?'0':'')+now.getHours()+':'+(now.getMinutes()<10?'0':'')+now.getMinutes()+'<span>'+(now.getSeconds()<10?'0':'')+now.getSeconds()+'</span>';
				$('date').innerHTML = now.getDate()+':e '+months[now.getMonth()]+' '+now.getFullYear();
			}

			makeFastOnClick = function() {
				if( !window.Touch )
					return false;

				var elem = document.getElementsByTagName("*");
				var len = elem.length;

				var found =0;
				for(var i=0;i<len;i++) {
					if ( elem[i].onclick != undefined ) {
						found++;

						elem[i].ontouchstart = elem[i].onclick;
						elem[i].onclick = undefined;
					}
				}

				return true;
			}

            
			pageLoad = function() {
				Request.prototype.addEvents({
					'onComplete': makeFastOnClick,
				});
				setTimeout(function() { window.scrollTo(0, 1); }, 1000);
				updateClock();
				makeFastOnClick();
				$('iframe').src="incoming.php";

				if ( location.hash > '' ) {
					menu.showPage(location.hash.substring(1,location.hash.length));
				}

                window.addEvent('mouseup',function(){
                  clearTimeout(pressTimer);
                  // Clear timeout
                });

                window.addEvent('mousedown',function(){
                  // Set timeout
                  pressTimer = window.setTimeout(editmode.longpress,1000);
                });
                window.addEvent('touchend',function(){
                  clearTimeout(pressTimer);
                  // Clear timeout
                });

                window.addEvent('touchstart',function(){
                  // Set timeout
                  pressTimer = window.setTimeout(editmode.longpress,1000);
                });

				addEventListener("orientationchange", room.orient);

			}
            
        </script>
    </head>
    <body onload="pageLoad();">
        <script language="javascript">
            var isiPad = navigator.userAgent.match(/iPad/i) != null;
            if (isiPad) {
                document.body.addClass('iPad');
				if ( !navigator.standalone ) {
                	document.body.addClass('embedded');
				}
            }

            var isiPhone = navigator.userAgent.match(/iPhone/i) != null;
            if (isiPhone) {
                document.body.addClass('iPhone');
				if ( !navigator.standalone ) {
                	document.body.addClass('embedded');
				}
            }
            document.ontouchmove = function(e){ e.preventDefault(); }

        </script>
        <div class="container" id="container">

            <div class="bg"></div>
            <div class="status">

                <div id="temp"></div>
                <div id="time" onClick="location.reload();"></div>
                <div id="date"></div>
                <div id="larm"></div>
				<div class="editmode" id="editmodehead">Edit mode active</div>
            </div>
            <div class="main" id="main">
			<?php

				foreach($layout as $key => $line) {
					echo '<div class="page" id="page_'.$key.'">';
					include('pages/'.$key.'.php');
					echo '</div>';

					foreach($line as $key2 => $sub) {
						if ( is_numeric($key2) )
							continue;
						echo '<div class="page" id="page_'.$key2.'">';
						include('pages/'.$key.'/'.$key2.'.php');
						echo '</div>';
					}
				}

			?>
            </div>
			<div id="settings_pane" style="visibility: hidden; opacity: 0;">
				<div class="parameters"></div>
				<div class="remove" onClick="editmode.remove();">Remove</div>
				<div class="exit" onClick="$(this.parentNode).fade();">X</div>
			</div>
            <div class="menu">
				<?php
					end($layout);
					$last = key($layout);
					foreach($layout as $key => $line)
						if ( $key == $last ) 
							echo '<a class="last" onClick="menu.main(this);" id="'.$key.'">'.$line[0].'</a>';
						else
							echo '<a onClick="menu.main(this);" id="'.$key.'">'.$line[0].'</a>';
				?>
            </div>
			<div class="editmode editmenu">
				<a onClick="editmode.addButton(this);">New button</a>
				<a>New switch</a>
				<a>New slider</a>
				<a onClick="editmode.addRoom();">New room</a>
				<a onClick="editmode.removeRoom();">Remove room</a>
				<a onClick="editmode.exit();" class="last">Exit</a>
			</div>
			<div class="editmode portrait">
				<h1>Error</h1>
				Edit mode is not available in portrait orientation, please rotate to landscape orientation!
			</div>
			<div class="embeddederror">
				<h1>Error</h1>
				To use this remote panel, add a link to your homescreen and start it from there.
			</div>
            <div id="submenu"></div>
        </div>
    </body>
</html>
