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

        <script type="text/javascript" src="js/mootools-core-1.3-full-compat-yc.js"></script>
        <link href="css/base.css" rel="stylesheet" />
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

            var menu = {
                curSub:'',
				showPage:function(page){
					$$('.page').removeClass('active');
					$('page_'+page).addClass('active');
				},
                sub:function(obj) {
                    $$('#submenu a').removeClass("active");
                    obj.addClass("active");
                    $('submenu').fade('out');

					menu.showPage(obj.id);
                },
                main:function(obj) {
                    $$('.menu a').removeClass("active");
                    obj.addClass("active");


                    if ( menu.layout[obj.id].length == 1 ) {
                        show = false;
						menu.showPage(obj.id);
                    } else {
                        show = true;

                        if ( menu.curSub != obj.id ) {
                            menu.curSub = obj.id;
                            $('submenu').innerHTML = '';
                            for( node in menu.layout[obj.id] ) {
								if ( node == 0 ) {
									continue;
								}
								if( window.Touch ) {
									$('submenu').innerHTML += '<a ontouchstart="menu.sub(this)" id="'+node+'">'+menu.layout[obj.id][node]+'</a>';
								} else {
									$('submenu').innerHTML += '<a onClick="menu.sub(this)" id="'+node+'">'+menu.layout[obj.id][node]+'</a>';
								}
                            }
                        }
                    }

                    if ( show ) {
                        //$('submenu').tween('bottom',62);
                        $('submenu').fade('in');
                    } else {
                       // $('submenu').tween('bottom',22);
                        $('submenu').fade('out');
                    }
                },
				layout: <?php echo json_encode($layout); ?>
            }

            update = function() {
                setTimeout('update()',1000);
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
				update();
				makeFastOnClick();
				$('iframe').src="incoming.php";

				sendJSON("type=hello");
			}

			sendJSON = function(url) {
				new Request({
					url: "send.php?"+url
				}).send();
			}

			incoming = function( json ) {
				$('page_rooms').innerHTML += "<br><br><br>"+json;
				pkt = eval('('+json+')');

				// Coomands
				if ( pkt.cmd != undefined ) {
					switch( pkt.cmd ) {
						case 'greetings':
							$('active_nodes').innerHTML += pkt.from+" ("+pkt.class+")<br />";
	
							for (c in pkt.class) {
								if ( pkt.class[c] == 'video.player' ) {
									video.addPlayer(pkt.from);
								}
							}

							break;
						case 'ack':
							switch( pkt.pkt.cmd ) {
								case 'state':
								 	if ( pkt.ret.paused != undefined ) {
										video.setState(pkt.from,pkt.ret);
									}
									break;
								case 'media':
									video.addMedia(pkt.from,pkt.ret.result.movies);
									break;
							}
							break;
						case 'nak':
							alert(pkt.pkt.cmd);
							
					}
				}
				// Types
				if ( pkt.type != undefined ) {
					switch( pkt.type ) {
						case 'event':
							switch(pkt.event) {
								case 'state':
									video.setState(pkt.from,pkt.data);
								break;
							}
							break;
					}
				}
			}

			video = {
				players:new Array(),
				addPlayer:function(name) {
					video.removePlayer(name);
					video.players[video.players.length] = name;

					$('videoplayers').innerHTML += 
						'<div id="videoplayer_'+name+'">'+
						'<div class="radio play" onclick="sendJSON(\'to='+name+'&cmd=play\');">Play</div>'+
						'<div class="radio pause" onclick="sendJSON(\'to='+name+'&cmd=pause\');">Pause</div>'+
						'<div class="radio stop" onclick="sendJSON(\'to='+name+'&cmd=stop\');">Stop</div>'+
						'</div>';
					
					
					setTimeout(function(){sendJSON("to="+name+"&cmd=state");},100);
					setTimeout(function(){sendJSON("to="+name+"&cmd=media");},1000);
					//sendJSON("to="+name+"&cmd=list");
				},
				removePlayer:function(name) {
					if ( $('videoplayer_'+name) != undefined ) {
						$('videoplayer_'+name).dispose();
					}
				},
				setState:function(name,state) {
					$$('#videoplayer_'+name+' .radio').removeClass('true');

					if ( state.playing ) {
						if ( state.paused ) {
							$$('#videoplayer_'+name+' .pause').addClass('true');
						} else {
							$$('#videoplayer_'+name+' .play').addClass('true');
						}
					} else {
						$$('#videoplayer_'+name+' .stop').addClass('true');
					}
				},
				addMedia:function(name,movies){
					list = '';
					len = movies.length;
					$('movie_files').innerHTML = '';
					for(var a=0;a<len;a++) {
						list += '<a onClick="sendJSON(\'to='+name+'&cmd=PlayMovie&file='+movies[a].movieid+'\');" style="background-image:url(http://loke:8080/vfs/'+movies[a].thumbnail+');" class="movie"><div>'+movies[a].label+'</div></a>';
					}
					$('movie_files').innerHTML = list;
				}
			}
        </script>
    </head>
    <body onload="pageLoad();">
        <script language="javascript">
            var isiPad = navigator.userAgent.match(/iPad/i) != null;
            if (isiPad) {
                document.body.addClass('iPad');
            }

            var isiPhone = navigator.userAgent.match(/iPhone/i) != null;
            if (isiPhone) {
                document.body.addClass('iPhone');
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
            <div id="submenu"></div>
        </div>
        <iframe id="iframe" style="display:none;"></iframe>
    </body>
</html>
