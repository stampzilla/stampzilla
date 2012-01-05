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

        <script type="text/javascript" src="js/mootools-core-1.3-full-compat-yc.js"></script>
        <script type="text/javascript" src="js/swipe.js"></script>
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

			var editmode = {
				longpress: function() {
					// Only enter editmode on room pages
					pages = $$('.page.active');
					if ( pages[0] != undefined && pages[0].hasClass('room') ) {
						if ( !$(document.body).hasClass('editmodeactive') && confirm("You are about to enter edit mode, is this ok?") ) {
							$(document.body).addClass('editmodeactive');
    	                    $('submenu').fade('out');
						}
					}
				},
				exit: function() {
					$(document.body).removeClass('editmodeactive');
				},
				addRoom: function() {
					name = prompt("What is the name of the new room?");

					if ( name != null && name != "" && name != "null" ) {
              			sendJSON("to=logic&cmd=room&name="+name);
					}
				},
				removeRoom: function() {
					pages = $$('.page.active');

					if ( pages[0] != undefined && pages[0].hasClass('room') ) {
						id = pages[0].id.substring(5,pages[0].id.length);
						if ( confirm("You are about to remove the room named '"+id+"', is this ok?") ) {
							sendJSON("to=logic&cmd=deroom&uuid="+id);
						}
					} else {
						alert("Unknown room, exiting edit mode");
						editmode.exit();
					}
				}
			}

            var menu = {
                curSub:'',
				showPage:function(page){
					$$('.page').removeClass('active');
					if ( $('page_'+page) != undefined ) {
						$('page_'+page).addClass('active');
					}
					location.hash = page;
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

                    if ( menu.curSub != obj.id ) {
                        show = false;
                        menu.curSub = obj.id;
                        $('submenu').innerHTML = '';
                        for( node in menu.layout[obj.id] ) {
                            if ( node == 0 ) {
                                continue;
                            }
                            show = true;
                            if( window.Touch ) {
                                $('submenu').innerHTML += '<a ontouchstart="menu.sub(this)" id="'+node+'">'+menu.layout[obj.id][node][0]+'</a>';
                            } else {
                                $('submenu').innerHTML += '<a onClick="menu.sub(this)" id="'+node+'">'+menu.layout[obj.id][node][0]+'</a>';
                            }
                        }
                    }

                    if ( show ) {
                        //$('submenu').tween('bottom',62);
                        $('submenu').fade('in');
                    } else {
					    menu.showPage(obj.id);
                       // $('submenu').tween('bottom',22);
                        $('submenu').fade('out');
                    }
                },
				layout: <?php echo json_encode($layout,JSON_FORCE_OBJECT); ?>
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

				if ( location.hash > '' ) {
					menu.showPage(location.hash.substring(1,location.hash.length));
				}

                window.addEvent('mouseup',function(){
                  clearTimeout(pressTimer);
                  // Clear timeout
                  return false;
                });

                window.addEvent('mousedown',function(){
                  // Set timeout
                  pressTimer = window.setTimeout(editmode.longpress,1000);
                  return false; 
                });
                window.addEvent('touchend',function(){
                  clearTimeout(pressTimer);
                  // Clear timeout
                  return false;
                });

                window.addEvent('touchstart',function(){
                  // Set timeout
                  pressTimer = window.setTimeout(editmode.longpress,1000);
                  return false; 
                });

			}
            
            communicationReady = function(){
				sendJSON("type=hello");
                //Fetch a list of all rooms from logic deamon
                sendJSON("to=logic&cmd=rooms");
            }

			sendJSON = function(url) {
				new Request({
					url: "send.php?"+url
				}).send();
			}

			incoming = function( json ) {
				//$('page_rooms').innerHTML += "<br><br><br>"+json;
				pkt = eval('('+json+')');

				// Coomands
				if ( pkt.cmd != undefined ) {
					switch( pkt.cmd ) {
						case 'greetings':
							settings.addComponent(pkt.from,pkt.class,pkt.settings);

							for (c in pkt.class) {
								if ( pkt.class[c] == 'video.player' ) {
									video.addPlayer(pkt.from);
								}
							}

							break;
						case 'ack':
							switch( pkt.pkt.cmd ) {
                                case 'rooms':
                                    for(var prop in pkt.ret) {
										room.add(prop,pkt.ret[prop]);
                                    }
                                    //menu.main($('rooms'));
                                    //alert(JSON.stringify(menu.layout.rooms));
                                    break;
								case 'state':
								 	if ( pkt.ret.paused != undefined ) {
										video.setState(pkt.from,pkt.ret);
									}
									break;
								case 'media':
									video.addMedia(pkt.from,pkt.ret.result.movies);
									break;
								case 'save_setting':
									settings.save_success(
										pkt.from,
										pkt.pkt.key,
										pkt.ret.value
									);
									break;
								default:
									//alert('ACK from '+pkt.from+' - '+pkt.pkt.cmd);
									break;
							}
							break;
						case 'nak':
							switch( pkt.pkt.cmd ) {
								case 'save_setting':
									settings.save_failed(
										pkt.from,
										pkt.pkt.key,
										pkt.ret.value,
										pkt.ret.msg
									);
									break;
								default:
									alert('NAK from '+pkt.from+' - '+pkt.pkt.cmd);
									break;
							}
							break;
						case 'bye':
							settings.removeComponent(pkt.from);
							video.removePlayer(pkt.from);
							break;
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
								case 'addRoom':
									editmode.exit();

									room.add(pkt.uuid,pkt.data);

									menu.sub($('page_'+pkt.uuid));
									menu.curSub = '';

									//menu.showPage(pkt.uuid);
									break;
								case 'removeRoom':
									editmode.exit();
									room.remove(pkt.uuid);
									break;
							}
							break;
					}
				}
			}

			room = {
				rooms: new Object(),
				add: function(uuid,data) {
					var temp = new Object();
					temp["0"] = data.name;
					menu.layout.rooms[uuid] = temp;

					el = new Element('div', {id: 'page_'+uuid,class: 'page room'});
					$('main').adopt(el);

					room.rooms[uuid] = data;
					room.render(uuid);
				},
				remove:function(uuid) {
					$('page_'+uuid).dispose();
					delete menu.layout.rooms[uuid];
					if ( $(uuid) != undefined ) {
						$(uuid).dispose();
					}
				},
				render:function(uuid) {
					$('page_'+uuid).innerHTML = '<div class="title">'+room.rooms[uuid].name+'</div>';
				}
			}

	
			settings = {
				addComponent:function(name,classes,settings) {
					if ( $('component_'+name) == undefined ) {
						s = '';

						for( key in settings ) {
							row = settings[key];
							switch( row['type'] ) {
								case 'text':
									value = '';
									if ( row['value'] != undefined ) {
										value = row['value'];
									}
									s += '<div><label for="'+name+'_'+key+'">'+row['name']+'</label><input type="text" id="setting_'+name+'_'+key+'" name="'+name+'_'+key+'" onChange="settings.save(this,\''+name+'\',\''+key+'\');" value="'+value+'"></div>';
									break;
							}
						}
						el = new Element('div', {id: 'component_'+name});
						el.innerHTML = '<h2>'+name+" <span>("+classes+") <a href=\"javascript:sendJSON('to="+name+"&cmd=kill');\">[Kill]</a></span></h2>"+s;
					    $('active_nodes').adopt(el);
                    }
				},
				removeComponent:function(name) {
					if ( $('component_'+name) != undefined ) {
						$('component_'+name).dispose();
					}
				},
				save:function(obj,name,key) {
					$(obj).addClass('saving');
					$(obj).disabled = true;
					sendJSON('to='+name+'&cmd=save_setting&key='+key+'&value='+obj.value);
					return true;
				},
				save_success: function(name,key,value) {
					$('setting_'+name+'_'+key).removeClass('saving');
					$('setting_'+name+'_'+key).value = value;
					$('setting_'+name+'_'+key).disabled = false;
				},
				save_failed: function(name,key,value,msg) {
					$('setting_'+name+'_'+key).removeClass('saving');
					$('setting_'+name+'_'+key).value = value;
					$('setting_'+name+'_'+key).disabled = false;
					
					alert('Failed to save: '+msg);
				}

			};

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
					
					
					sendJSON("to="+name+"&cmd=state");
					sendJSON("to="+name+"&cmd=media");
				},
				removePlayer:function(name) {
					if ( $('videoplayer_'+name) != undefined ) {
						$('videoplayer_'+name).dispose();
						$$('.videoplayer_'+name).dispose();
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
						list += '<a onClick="sendJSON(\'to='+name+'&cmd=PlayMovie&file='+movies[a].movieid+'\');"';
						
						if ( movies[a].thumbnail != undefined ) {
							list += ' style="background-image:url(resize.php?url='+movies[a].thumbnail+');"';
						}

						list += ' class="movie videoplayer_'+name;

						if ( movies[a].lastplayed != undefined ) {
							list += ' played';
						}						

						list += '"><div class="new">New</div>';
						for( field in movies[a] ) {
							value = eval('movies[a].'+field);

							if ( field == 'rating' )
								value = Math.round(value);

							list += '<div class="'+field+'">'+value+'</div>';
						}

						list += '</a>';
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
				<a>New button</a>
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
            <div id="submenu"></div>
        </div>
    </body>
</html>
