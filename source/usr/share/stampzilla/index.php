<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
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

        <script type="text/javascript" src="js/mootools-core-1.3-full-compat-yc.js">
	</script>
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
                $('iframe').src="send.php?"+cmd;
            }
            radio = function(obj, on) {

		for(var i=0;i<obj.parentNode.childNodes.length;i++) {
			obj.parentNode.childNodes[i].className="radio";
		};

                obj.addClass('true');
                $('iframe').src="send.php?"+on;
            }

            var menu = {
                curSub:'',
		load:function(url) {
                    $('main').load(url);
		},
                sub:function(obj) {
                    $$('#submenu a').removeClass("active");
                    obj.addClass("active");
                    $('submenu').fade('out');

                    menu.load('page.php?m='+menu.curSub+'&s='+obj.id);
                },
                main:function(obj) {
                    $$('.menu a').removeClass("active");
                    obj.addClass("active");

                    if ( menu.s[obj.id] == undefined ) {
                        show = false;
                        menu.load('page.php?m='+obj.id);
                    } else {
                        show = true;

                        if ( menu.curSub != obj.id ) {
                            menu.curSub = obj.id;
                            $('submenu').innerHTML = '';
                            for( node in menu.s[obj.id] ) {
				if( window.Touch ) {
                                    $('submenu').innerHTML += '<a ontouchstart="menu.sub(this)" id="'+node+'">'+menu.s[obj.id][node]+'</a>';
				} else {
                                    $('submenu').innerHTML += '<a onClick="menu.sub(this)" id="'+node+'">'+menu.s[obj.id][node]+'</a>';
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
                s: {
                    media: {
                        movie: 'Movies',
                        series: 'Series',
                        sound: 'Music/Radio',
                        pictures: 'Pictures'
                    },
                    climate: {
                        weather: 'Weather',
                        heat: 'Heat',
                    },
                    alarm: {
                        status: 'Status',
                        users: 'Users',
                        log: 'Log'
                    }
                }
            }

            update = function() {
                setTimeout('update()',1000);
                now = new Date();
                months = new Array('Januari','Februari','Mars','April','Maj','Juni','Juli','Augusti','September','Oktober','November','December');
                $('time').innerHTML = (now.getHours()<10?'0':'')+now.getHours()+':'+(now.getMinutes()<10?'0':'')+now.getMinutes()+'<span>'+(now.getSeconds()<10?'0':'')+now.getSeconds()+'</span>';
                $('date').innerHTML = now.getDate()+':e '+months[now.getMonth()]+' '+now.getFullYear();
                //$('temp').load('temp.php');
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
                   //     elem[i].style.border = '1px solid #f00';
                    }
                }

		return true;
            }

	    pageLoad = function() {
	    	Request.prototype.addEvents({
			'onComplete': makeFastOnClick,
	    	});
		setTimeout(function() { window.scrollTo(0, 1) }, 100);
		$('main').load('page.php');
		update();
		makeFastOnClick();
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
                <div id="temp"><?php $data = json_decode(file_get_contents('net1.json')); echo $data->Totalt->total; ?></div>
                <div id="time" onClick="location.reload();"></div>
                <div id="date"></div>
                <div id="larm"></div>
            </div>
            <div class="main" id="main">
            </div>
            <div class="menu">
                <a onClick="menu.main(this);" id="rooms">Rum</a>
                <a onClick="menu.main(this);" id="media">Media</a>
                <a onClick="menu.main(this);" id="climate">Klimat</a>
                <a onClick="menu.main(this);" id="alarm">Larm</a>
                <a onClick="menu.main(this);" id="">Förbrukning</a>
                <a class="last" onClick="menu.main(this);" id="settings">Inställningar</a>
            </div>
            <div id="submenu">
                <a href="">Bumblebeeo</a>
                <a href="">Yoda</a>
                <a href="" class="active">Prime</a>
                <a style="margin-right:0px;width:164px;" href="">Köket</a>
            </div>
        </div>
        <iframe id="iframe" style="display:none;"></iframe>
    </body>
</html>
